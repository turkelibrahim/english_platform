<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');

// --- Practice Tests (admin-created) filters (GET)
$t_topic = trim($_GET['t_topic'] ?? '');
$t_skill = trim($_GET['t_skill'] ?? '');
$t_qcount = (int)($_GET['t_qcount'] ?? 0); // 5/10/20

$testErr = '';

// Start a selected test (creates a user_task from the test template)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_test') {
  csrf_check();

  $testId = (int)($_POST['test_id'] ?? 0);
  if ($testId <= 0) {
    $testErr = 'Invalid test.';
  } else {
    $st = db()->prepare("SELECT * FROM practice_tests WHERE id=? AND is_active=1");
    $st->execute([$testId]);
    $test = $st->fetch();

    if (!$test) {
      $testErr = 'This test is not available.';
    } else {
      $it = db()->prepare("SELECT question_id FROM practice_test_items WHERE test_id=? ORDER BY position ASC");
      $it->execute([$testId]);
      $qids = array_map(fn($r) => (int)$r['question_id'], $it->fetchAll());

      if (!$qids) {
        $testErr = 'This test has no questions.';
      } else {
        db()->beginTransaction();
        try {
          $ins = db()->prepare("INSERT INTO user_tasks (user_id, title, topic, status, due_at) VALUES (?,?,?,?,NULL)");
          $ins->execute([
            $u['id'],
            (string)$test['title'],
            (string)$test['topic'],
            'open'
          ]);
          $taskId = (int)db()->lastInsertId();

          $insIt = db()->prepare("INSERT INTO user_task_items (task_id, question_id, position) VALUES (?,?,?)");
          $pos = 1;
          foreach ($qids as $qid) {
            $insIt->execute([$taskId, $qid, $pos]);
            $pos++;
          }

          db()->commit();
          header('Location: '.BASE_URL.'/student/task_start.php?task_id='.$taskId);
          exit;
        } catch (Throwable $e) {
          db()->rollBack();
          $testErr = 'Could not start the test. Please try again.';
        }
      }
    }
  }
}

// Dropdown data for Tests
$testTopics = db()->query("SELECT DISTINCT topic FROM practice_tests WHERE is_active=1 ORDER BY topic")->fetchAll();
$testSkills = db()->query("SELECT DISTINCT skill FROM practice_tests WHERE is_active=1 ORDER BY skill")->fetchAll();
$testCounts = db()->query("SELECT DISTINCT question_count FROM practice_tests WHERE is_active=1 ORDER BY question_count")->fetchAll();

// Fetch tests list
$whereT = ["is_active=1"]; $paramsT = [];
if ($t_topic !== '') { $whereT[] = "topic=?"; $paramsT[] = $t_topic; }
if ($t_skill !== '') { $whereT[] = "skill=?"; $paramsT[] = $t_skill; }
if (in_array($t_qcount, [5,10,20], true)) { $whereT[] = "question_count=?"; $paramsT[] = $t_qcount; }

$stT = db()->prepare("SELECT id,title,topic,skill,difficulty,question_count,created_at FROM practice_tests WHERE ".implode(' AND ', $whereT)." ORDER BY created_at DESC, id DESC LIMIT 60");
$stT->execute($paramsT);
$tests = $stT->fetchAll();

// --- Optional Question Bank (kept, but now secondary)
$qb_skill = trim($_GET['qb_skill'] ?? '');
$qb_topic = trim($_GET['qb_topic'] ?? '');
$qb_difficulty = (int)($_GET['qb_difficulty'] ?? 0);
$qb_q = trim($_GET['qb_q'] ?? '');

$skills = db()->query("SELECT DISTINCT skill FROM questions WHERE is_active=1 ORDER BY skill")->fetchAll();
$topics = db()->query("SELECT DISTINCT topic FROM questions WHERE is_active=1 ORDER BY topic")->fetchAll();

$whereQ = ["is_active=1", "is_placement=0"]; $paramsQ = [];
if ($qb_skill !== ''){ $whereQ[] = "skill=?"; $paramsQ[] = $qb_skill; }
if ($qb_topic !== ''){ $whereQ[] = "topic=?"; $paramsQ[] = $qb_topic; }
if ($qb_difficulty > 0){ $whereQ[] = "difficulty=?"; $paramsQ[] = $qb_difficulty; }
if ($qb_q !== ''){
  $whereQ[] = "(prompt LIKE ? OR topic LIKE ? OR skill LIKE ?)";
  $paramsQ[] = "%$qb_q%";
  $paramsQ[] = "%$qb_q%";
  $paramsQ[] = "%$qb_q%";
}

$stQ = db()->prepare("SELECT id, topic, skill, difficulty, prompt FROM questions WHERE ".implode(' AND ', $whereQ)." ORDER BY created_at DESC LIMIT 60");
$stQ->execute($paramsQ);
$questions = $stQ->fetchAll();

$favQ = db()->prepare("SELECT ref_id FROM favorites WHERE user_id=? AND fav_type='question'");
$favQ->execute([$u['id']]);
$favs = array_fill_keys(array_map(fn($r)=>(int)$r['ref_id'], $favQ->fetchAll()), true);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Student · Practice</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Practice"; $navActive="practice"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:center; gap:12px">
      <div>
        <div style="font-weight:900; font-size:18px">🚀 Level Check Quiz</div>
        <div class="muted" style="margin-top:6px">Want to level up? Take a short quiz. If you score high enough, your level increases (it will never decrease).</div>
      </div>
      <?php if((int)($u['placement_completed'] ?? 0) !== 1): ?>
        <a class="btn" href="<?=BASE_URL?>/student/placement.php">Go to placement</a>
      <?php else: ?>
        <a class="btn primary" href="<?=BASE_URL?>/student/level_quiz.php?start=1">Start quiz</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:baseline">
      <div>
        <div class="h1">Practice Tests</div>
        <div class="muted" style="margin-top:6px">Tests are created by admins (5 / 10 / 20 questions). Choose a test and solve it in task mode.</div>
      </div>
      <div class="pill"><?=count($tests)?> shown</div>
    </div>
    <div class="hr"></div>

    <?php if($testErr): ?><div class="toast">⚠️ <?=htmlspecialchars($testErr)?></div><div class="hr"></div><?php endif; ?>

    <form method="get" class="grid" style="grid-template-columns: 1fr 1fr 1fr; gap:10px; align-items:end; margin-top:0">
      <div>
        <label class="muted">Topic</label>
        <select class="input" name="t_topic">
          <option value="">All</option>
          <?php foreach($testTopics as $r): $v=(string)$r['topic']; ?>
            <option value="<?=htmlspecialchars($v)?>" <?=($v===$t_topic)?'selected':''?>><?=htmlspecialchars($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="muted">Skill</label>
        <select class="input" name="t_skill">
          <option value="">All</option>
          <?php foreach($testSkills as $r): $v=(string)$r['skill']; ?>
            <option value="<?=htmlspecialchars($v)?>" <?=($v===$t_skill)?'selected':''?>><?=htmlspecialchars($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="muted">Questions</label>
        <select class="input" name="t_qcount">
          <option value="0">All</option>
          <?php foreach($testCounts as $r): $v=(int)$r['question_count']; ?>
            <option value="<?=$v?>" <?=($v===$t_qcount)?'selected':''?>><?=$v?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="grid-column: 1 / -1">
        <button class="btn" style="width:100%">Apply filters</button>
      </div>
    </form>

    <div class="hr"></div>

    <?php if(!$tests): ?>
      <div class="toast">No tests found. Try different filters.</div>
    <?php else: ?>
      <div style="display:grid; gap:12px">
        <?php foreach($tests as $t): ?>
          <div class="card" style="margin:0">
            <div class="row" style="justify-content:space-between; align-items:flex-start; gap:12px">
              <div style="flex:1">
                <div style="font-weight:900"><?=htmlspecialchars($t['title'])?></div>
                <div class="muted" style="margin-top:6px">
                  Topic: <?=htmlspecialchars($t['topic'])?> · Skill: <?=htmlspecialchars($t['skill'])?> · D<?= (int)$t['difficulty'] ?> · <?= (int)$t['question_count'] ?> questions
                </div>
                <div class="muted" style="margin-top:4px">Created: <?=htmlspecialchars($t['created_at'])?></div>
              </div>
              <form method="post" style="margin:0">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="action" value="start_test">
                <input type="hidden" name="test_id" value="<?= (int)$t['id'] ?>">
                <button class="btn primary" type="submit">Start</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <details class="card" style="margin-top:18px">
    <summary style="cursor:pointer; font-weight:900">Question Bank (optional)</summary>
    <div style="height:12px"></div>

    <div class="row" style="justify-content:space-between; align-items:baseline">
      <div>
        <div class="h1" style="font-size:22px">Question Bank</div>
        <div class="muted" style="margin-top:6px">Filter questions, then <b>Solve</b> or <b>Save</b> them.</div>
      </div>
      <div class="pill"><?=count($questions)?> shown</div>
    </div>
    <div class="hr"></div>

    <form method="get" class="grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; gap:10px; align-items:end; margin-top:0">
      <div>
        <label class="muted">Skill</label>
        <select class="input" name="qb_skill">
          <option value="">All</option>
          <?php foreach($skills as $s): $v=(string)$s['skill']; ?>
            <option value="<?=htmlspecialchars($v)?>" <?=($v===$qb_skill)?'selected':''?>><?=htmlspecialchars($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="muted">Topic</label>
        <select class="input" name="qb_topic">
          <option value="">All</option>
          <?php foreach($topics as $t): $v=(string)$t['topic']; ?>
            <option value="<?=htmlspecialchars($v)?>" <?=($v===$qb_topic)?'selected':''?>><?=htmlspecialchars($v)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="muted">Difficulty</label>
        <select class="input" name="qb_difficulty">
          <option value="0">All</option>
          <?php for($d=1;$d<=5;$d++): ?>
            <option value="<?=$d?>" <?=($d===$qb_difficulty)?'selected':''?>><?=$d?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="muted">Search</label>
        <input class="input" type="text" name="qb_q" value="<?=htmlspecialchars($qb_q)?>" placeholder="keyword">
      </div>

      <!-- preserve test filters when using question bank filters -->
      <input type="hidden" name="t_topic" value="<?=htmlspecialchars($t_topic)?>">
      <input type="hidden" name="t_skill" value="<?=htmlspecialchars($t_skill)?>">
      <input type="hidden" name="t_qcount" value="<?= (int)$t_qcount ?>">

      <div style="grid-column: 1 / -1">
        <button class="btn" style="width:100%">Apply question filters</button>
      </div>
    </form>

    <div class="hr"></div>

    <?php if(!$questions): ?>
      <div class="toast">No questions found. Try changing filters.</div>
    <?php else: ?>
      <div style="display:grid; gap:12px">
        <?php foreach($questions as $qq):
          $qid = (int)$qq['id'];
          $isFav = isset($favs[$qid]);
        ?>
          <div class="card" style="margin:0">
            <div class="row" style="justify-content:space-between; align-items:flex-start; gap:12px">
              <div style="flex:1">
                <div style="font-weight:900">
                  <?=htmlspecialchars($qq['topic'] ?? '—')?> · <?=htmlspecialchars($qq['skill'])?>
                  <span class="pill" style="margin-left:8px">D<?= (int)$qq['difficulty'] ?></span>
                </div>
                <div class="muted" style="margin-top:6px"><?=htmlspecialchars(mb_strimwidth($qq['prompt'],0,140,'...'))?></div>
              </div>
              <div class="row" style="gap:8px; justify-content:flex-end">
                <a class="btn" href="<?=BASE_URL?>/student/practice_view.php?qid=<?=$qid?>">Solve</a>
                <button class="btn" type="button" onclick="toggleFav(<?=$qid?>)" data-fav-btn="<?=$qid?>"><?= $isFav ? 'Saved' : 'Save' ?></button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</div>

<script>
async function toggleFav(qid){
  try{
    const res = await fetch(`${BASE_URL}/student/api/toggle_favorite.php`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`ref_id=${encodeURIComponent(qid)}&fav_type=question`
    });
    const data = await res.json();
    const btn = document.querySelector(`[data-fav-btn="${qid}"]`);
    if(btn && data && data.ok){
      btn.textContent = data.favorited ? 'Saved' : 'Save';
    }
  }catch(e){}
}
</script>
</body>
</html>
