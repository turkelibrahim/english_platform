<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/tasks.php';

$u = require_role('student');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Must complete placement first
if ((int)($u['placement_completed'] ?? 0) !== 1) {
  header('Location: ' . BASE_URL . '/student/placement.php');
  exit;
}

function level_rank(string $lv): int {
  static $map = ['A1'=>1,'A2'=>2,'B1'=>3,'B2'=>4,'C1'=>5];
  return $map[$lv] ?? 0;
}

function level_band(string $lv): array {
  // Return [minDifficulty, maxDifficulty] with a slightly wider max
  // so that level-up is possible.
  switch ($lv) {
    case 'A1': return [1, 3];
    case 'A2': return [2, 4];
    case 'B1': return [3, 5];
    case 'B2': return [4, 5];
    case 'C1': return [5, 5];
    default:   return [1, 3];
  }
}

// START QUIZ
if (isset($_GET['start'])) {
  // Build a fresh quiz
  $level = (string)($u['level'] ?? 'A1');
  [$minD, $maxD] = level_band($level);

  $skills = ['vocab','grammar','reading','listening','writing'];
  $qids = [];

  foreach ($skills as $sk) {
    $st = db()->prepare(
      "SELECT id FROM questions
       WHERE is_active=1 AND is_placement=0 AND skill=?
         AND difficulty BETWEEN ? AND ?
       ORDER BY RAND() LIMIT 2"
    );
    $st->execute([$sk, $minD, $maxD]);
    foreach ($st->fetchAll() as $r) {
      $qids[] = (int)$r['id'];
    }
  }

  // Fill up to 10
  if (count($qids) < 10) {
    $need = 10 - count($qids);
    $st2 = db()->prepare(
      "SELECT id FROM questions
       WHERE is_active=1 AND is_placement=0
         AND difficulty BETWEEN ? AND ?
       ORDER BY RAND() LIMIT {$need}"
    );
    $st2->execute([$minD, $maxD]);
    foreach ($st2->fetchAll() as $r) {
      $qids[] = (int)$r['id'];
    }
  }

  // Ultimate fallback
  if (!$qids) {
    $st3 = db()->query("SELECT id FROM questions WHERE is_active=1 AND is_placement=0 ORDER BY RAND() LIMIT 10");
    $qids = array_map(fn($r) => (int)$r['id'], $st3->fetchAll());
  }

  // De-duplicate + cap
  $qids = array_values(array_unique(array_map('intval', $qids)));
  $qids = array_slice($qids, 0, 10);

  $_SESSION['level_quiz'] = [
    'qids' => $qids,
    'idx' => 0,
    'answers' => [],
    'from_level' => (string)($u['level'] ?? '')
  ];

  if (!$qids) {
    header('Location: ' . BASE_URL . '/student/practice.php');
    exit;
  }

  header('Location: ' . BASE_URL . '/student/level_quiz.php');
  exit;
}

$quiz = $_SESSION['level_quiz'] ?? null;
if (!is_array($quiz) || empty($quiz['qids']) || !is_array($quiz['qids'])) {
  header('Location: ' . BASE_URL . '/student/practice.php');
  exit;
}

// NEXT QUESTION
if (isset($_GET['next'])) {
  $quiz['idx'] = (int)($quiz['idx'] ?? 0) + 1;
  $_SESSION['level_quiz'] = $quiz;
  header('Location: ' . BASE_URL . '/student/level_quiz.php');
  exit;
}

// FINISH QUIZ
if (isset($_GET['finish'])) {
  $answers = $quiz['answers'] ?? [];
  $bySkill = [];
  foreach ($answers as $a) {
    $sk = (string)($a['skill'] ?? '');
    if ($sk === '') continue;
    if (!isset($bySkill[$sk])) $bySkill[$sk] = ['t'=>0,'c'=>0];
    $bySkill[$sk]['t']++;
    $bySkill[$sk]['c'] += ((int)($a['correct'] ?? 0) === 1) ? 1 : 0;
  }

  // Convert to 0..100 for level_from_scores
  $scores = [];
  foreach ($bySkill as $sk => $st) {
    $t = max(1, (int)$st['t']);
    $c = (int)$st['c'];
    $scores[$sk] = (int)round(100 * $c / $t);
  }

  // If for some reason we don't have scores, treat as no level-up.
  $recommended = $scores ? level_from_scores(array_values($scores)) : (string)($u['level'] ?? 'A1');
  $current = (string)($u['level'] ?? 'A1');

  $upgraded = false;
  $to = $current;
  if (level_rank($recommended) > level_rank($current)) {
    $to = $recommended;
    $upgraded = true;

    db()->prepare("UPDATE users SET level=? WHERE id=?")->execute([$to, $u['id']]);

    // Close current active tasks so My Tasks becomes level-consistent.
    db()->prepare("UPDATE user_tasks SET status='done' WHERE user_id=? AND status IN ('open','in_progress')")
      ->execute([$u['id']]);

    // Create a friendly notification
    create_notification((int)$u['id'], 'level', 'Level up! 🎉', "You advanced from {$current} to {$to}.");

    // Recreate tasks based on new level
    refresh_tasks_for_user((int)$u['id'], 3);
  }

  $from = $current;
  unset($_SESSION['level_quiz']);

  if ($upgraded) {
    header('Location: ' . BASE_URL . '/student/dashboard.php?levelup=1&from=' . urlencode($from) . '&to=' . urlencode($to));
    exit;
  }

  header('Location: ' . BASE_URL . '/student/dashboard.php');
  exit;
}

// CURRENT QUESTION
$idx = max(0, (int)($quiz['idx'] ?? 0));
$qids = array_values(array_map('intval', $quiz['qids']));
if ($idx >= count($qids)) {
  header('Location: ' . BASE_URL . '/student/level_quiz.php?finish=1');
  exit;
}

$qid = (int)$qids[$idx];
$st = db()->prepare("SELECT * FROM questions WHERE id=? AND is_active=1 LIMIT 1");
$st->execute([$qid]);
$q = $st->fetch();
if (!$q) {
  header('Location: ' . BASE_URL . '/student/level_quiz.php?next=1');
  exit;
}

$choices = $q['choices_json'] ? json_decode($q['choices_json'], true) : null;
$total = count($qids);
$pos = $idx + 1;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Level Check Quiz</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Practice"; $navActive="practice"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div>
        <div style="font-weight:900; font-size:18px">🚀 Level Check Quiz</div>
        <div class="muted" style="margin-top:6px">Question <b><?= (int)$pos ?></b> / <?= (int)$total ?> · Current level: <b><?=htmlspecialchars($u['level'] ?? '—')?></b></div>
      </div>
      <a class="btn" href="<?=BASE_URL?>/student/practice.php">Exit</a>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="muted"><?=htmlspecialchars($q['skill'])?> · diff <?= (int)$q['difficulty'] ?></div>
    <div class="h1" style="font-size:20px; margin-top:10px"><?=htmlspecialchars($q['prompt'])?></div>

    <?php if($q['media_url']): ?>
      <?php if(preg_match('/\.(mp3|wav)$/i', $q['media_url'])): ?>
        <audio controls src="<?=htmlspecialchars($q['media_url'])?>" style="width:100%; margin:10px 0"></audio>
      <?php else: ?>
        <img src="<?=htmlspecialchars($q['media_url'])?>" style="max-width:100%; border-radius:14px; margin:10px 0">
      <?php endif; ?>
    <?php endif; ?>

    <?php if($choices): ?>
      <div style="margin-top:10px; display:grid; gap:10px">
        <?php foreach($choices as $i=>$c): ?>
          <button class="btn" onclick="submitAnswer('<?= (string)$i ?>')"><?=htmlspecialchars($c)?></button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <textarea class="input" id="w" rows="4" placeholder="Type your answer..."></textarea>
      <div style="height:10px"></div>
      <button class="btn primary" onclick="submitAnswer(document.getElementById('w').value)">Submit</button>
    <?php endif; ?>

    <div id="feedback" style="margin-top:12px"></div>
  </div>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<script>
async function submitAnswer(val){
  // Lock all choice buttons
  document.querySelectorAll('button.btn').forEach(b => { if(!b.classList.contains('nav-btn')) b.disabled = true; });

  const res = await fetch(`${BASE_URL}/student/api/level_check_submit.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`question_id=<?= (int)$qid ?>&user_answer=${encodeURIComponent(val)}`
  });
  const data = await res.json();
  if(!data.ok){
    document.getElementById('feedback').innerHTML = `<div class="toast">⚠️ Could not submit. Please try again.</div>`;
    return;
  }

  const action = data.can_finish
    ? `<div style="margin-top:12px"><a class="btn primary" href="${data.finish_url}">Finish quiz ✓</a></div>`
    : `<div style="margin-top:12px"><a class="btn primary" href="${data.next_url}">Next question →</a></div>`;

  const remainingLine = (typeof data.quiz_remaining === 'number')
    ? `<div class="muted" style="margin-top:6px">Remaining: <b>${data.quiz_remaining}</b></div>`
    : '';

  document.getElementById('feedback').innerHTML = `
    <div class="toast">
      <b>${data.correct ? 'Correct!' : 'Incorrect.'}</b>
      ${remainingLine}
      ${action}
    </div>
  `;
}
</script>
</body>
</html>
