<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/topic_lists.php';

$u = require_role('admin');
$err = $ok = '';

// Topic options: show a curated list of Grammar topics (avoid Daily Routine / general themes)
$topics = grammar_topics();
sort($topics, SORT_NATURAL | SORT_FLAG_CASE);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'create_test') {
    $skill = $_POST['skill'] ?? 'vocab';
    $difficulty = (int)($_POST['difficulty'] ?? 1);
    $level = strtoupper(trim((string)($_POST['level'] ?? '')));
    $levelMap = ['A1'=>1,'A2'=>2,'B1'=>3,'B2'=>4,'C1'=>5];
    if (!isset($levelMap[$level])) { $level = ''; }
    if ($level !== '') { $difficulty = (int)$levelMap[$level]; }
    $qcount = (int)($_POST['qcount'] ?? 10);
    $topic = trim($_POST['topic'] ?? '');

    if (!in_array($skill, ['vocab','grammar','reading','listening','writing'], true)) $skill = 'vocab';
    if ($difficulty < 1) $difficulty = 1;
    if ($difficulty > 5) $difficulty = 5;
    if (!in_array($qcount, [5,10,20], true)) $qcount = 10;

    // Topic must be chosen from dropdown
    if (!in_array($topic, $topics, true)) {
      $err = 'Please select a topic from the list.';
    } else {
      $payload = trim($_POST['payload_json'] ?? '');
      $data = json_decode($payload, true);

      if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
        $err = 'Please click AI Generate first (no generated questions found).';
      } else {
        $items = $data['questions'];
        if (count($items) !== $qcount) {
          $err = 'Generated question count does not match the selected count.';
        } else {
          // Build title
          $title = $level !== "" ? "{$topic} · {$level} · {$skill} · {$qcount}Q" : "{$topic} · {$skill} · {$qcount}Q";

          db()->beginTransaction();
          try {
            $insT = db()->prepare(
              "INSERT INTO practice_tests (title, topic, skill, difficulty, question_count, is_active, created_by, created_at)
               VALUES (?,?,?,?,?,1,?,NOW())"
            );
            $insT->execute([$title, $topic, $skill, $difficulty, $qcount, $u['id']]);
            $testId = (int)db()->lastInsertId();

            $tags = $level !== "" ? ("level:" . $level) : null;

            $insQ = db()->prepare(
              "INSERT INTO questions (skill, topic, difficulty, is_placement, prompt, choices_json, correct_answer, media_url, hint, explanation, example_sentence, tags)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
            );
            $insIt = db()->prepare(
              "INSERT INTO practice_test_items (test_id, question_id, position, created_at)
               VALUES (?,?,?,NOW())"
            );

            $pos = 1;
            foreach ($items as $it) {
              $p = trim((string)($it['prompt'] ?? ''));
              if ($p === '') throw new Exception('bad_prompt');

              $choicesArr = null;
              if (isset($it['choices']) && is_array($it['choices'])) {
                $choicesArr = array_values(array_map('strval', $it['choices']));
                $choicesArr = array_slice($choicesArr, 0, 4);
                if (count($choicesArr) !== 4) throw new Exception('bad_choices');
              }

              $correct = $it['correct_answer'] ?? null;
              if ($choicesArr !== null) {
                $ci = is_numeric($correct) ? (int)$correct : -1;
                if ($ci < 0 || $ci > 3) throw new Exception('bad_correct');
                $correctNorm = (string)$ci;
              } else {
                $correctNorm = trim((string)$correct);
                if ($correctNorm === '') throw new Exception('bad_correct');
              }

              $hint = trim((string)($it['hint'] ?? ''));
              $exp  = trim((string)($it['explanation'] ?? ''));
              $exs  = trim((string)($it['example_sentence'] ?? ''));

              $choicesJson = ($choicesArr !== null) ? json_encode($choicesArr, JSON_UNESCAPED_UNICODE) : null;

              $insQ->execute([
                $skill,
                $topic,
                $difficulty,
                0,
                $p,
                $choicesJson,
                $correctNorm,
                null,
                $hint ?: null,
                $exp ?: null,
                $exs ?: null,
              $tags,
              ]);
              $qid = (int)db()->lastInsertId();

              $insIt->execute([$testId, $qid, $pos]);
              $pos++;
            }

            db()->commit();
            $ok = 'Test created ✓';
          } catch (Throwable $e) {
            db()->rollBack();
            $err = 'Could not create the test (DB error).';
          }
        }
      }
    }
  }

  if ($action === 'toggle_active') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
      db()->prepare("UPDATE practice_tests SET is_active = CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")
        ->execute([$id]);
      $ok = 'Updated.';
    }
  }
}

$recent = db()->query(
  "SELECT pt.*, u.full_name AS admin_name
   FROM practice_tests pt
   LEFT JOIN users u ON u.id = pt.created_by
   ORDER BY pt.id DESC
   LIMIT 30"
)->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Tests</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Tests"; $navActive="tests"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="grid">
    <div class="card">
      <div class="h1">Create Practice Test</div>
      <div class="muted" style="margin-top:6px">Admin creates tests as a set of <b>5 / 10 / 20</b> questions. Students solve tests (not single questions).</div>
      <div class="hr"></div>

      <?php if($err): ?><div class="toast">⚠️ <?=htmlspecialchars($err)?></div><div class="hr"></div><?php endif; ?>
      <?php if($ok): ?><div class="toast">✅ <?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

      <form method="post" id="testForm">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="create_test">
        <input type="hidden" name="payload_json" id="payloadJson" value="">
        <input type="hidden" name="difficulty" id="diffHidden" value="1">

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap:12px; margin-top:0">
          <div>
            <label class="muted">Topic</label>
            <select class="input" name="topic" id="topicSel" required>
              <?php foreach($topics as $t): ?>
                <option value="<?=htmlspecialchars($t)?>"><?=htmlspecialchars($t)?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="muted">Questions</label>
            <select class="input" name="qcount" id="qcountSel">
              <?php foreach([5,10,20] as $n): ?>
                <option value="<?=$n?>" <?=$n===10?'selected':''?>><?=$n?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="muted">Skill</label>
            <select class="input" name="skill" id="skillSel" required>
              <?php foreach(['vocab','grammar','reading','listening','writing'] as $s): ?>
                <option value="<?=$s?>"><?=$s?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="muted">Level</label>
            <select class="input" name="level" id="levelSel" required>
              <option value="A1" selected>A1</option>
              <option value="A2">A2</option>
              <option value="B1">B1</option>
              <option value="B2">B2</option>
              <option value="C1">C1</option>
            </select>
            <div class="muted" id="diffInfo" style="margin-top:6px; font-size:12px">Mapped difficulty: 1</div>
          </div>
        </div>

        <div class="hr"></div>

        <div class="row" style="justify-content:space-between; align-items:center">
          <div>
            <div style="font-weight:900">✨ AI Generate (Gemini)</div>
            <div class="muted" style="margin-top:4px">Generates exactly the selected number of questions for the chosen topic.</div>
          </div>
          <button type="button" class="btn" id="aiBtn" onclick="aiGenerateTest()">AI Generate</button>
        </div>
        <div class="muted" id="aiStatus" style="margin-top:8px"></div>

        <div id="preview" style="margin-top:12px; display:none"></div>

        <div class="hr"></div>
        <button class="btn primary" style="width:100%" type="submit">Create Test</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Recent Tests</div>
      <div class="hr"></div>

      <?php if(!$recent): ?>
        <div class="toast">No tests yet.</div>
      <?php else: ?>
        <?php foreach($recent as $t): ?>
          <div class="card" style="box-shadow:none; margin-bottom:10px">
            <div class="row" style="justify-content:space-between; align-items:flex-start; gap:10px">
              <div>
                <div style="font-weight:900"><?=htmlspecialchars($t['title'])?></div>
                <div class="muted" style="margin-top:6px">
                  Topic: <?=htmlspecialchars($t['topic'])?> · Skill: <?=htmlspecialchars($t['skill'])?> · D<?= (int)$t['difficulty'] ?> · <?= (int)$t['question_count'] ?>Q
                  <?php if(!empty($t['admin_name'])): ?> · by <?=htmlspecialchars($t['admin_name'])?><?php endif; ?>
                </div>
                <div class="muted" style="margin-top:4px">Status: <?=((int)$t['is_active']===1)?'Active':'Inactive'?> · #<?= (int)$t['id'] ?></div>
              </div>
              <form method="post" style="margin:0">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button class="btn" type="submit"><?=((int)$t['is_active']===1)?'Deactivate':'Activate'?></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function setStatus(msg){
  const el = document.getElementById('aiStatus');
  if(el) el.textContent = msg || '';
}

function renderPreview(list){
  const box = document.getElementById('preview');
  if(!box) return;
  box.style.display = 'block';
  box.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.style.display = 'grid';
  wrap.style.gap = '10px';

  list.forEach((q, idx) => {
    const card = document.createElement('div');
    card.className = 'card';
    card.style.boxShadow = 'none';
    card.style.margin = '0';

    const h = document.createElement('div');
    h.style.fontWeight = '900';
    h.textContent = `Q${idx+1}`;

    const p = document.createElement('div');
    p.className = 'muted';
    p.style.marginTop = '6px';
    p.textContent = q.prompt || '';

    card.appendChild(h);
    card.appendChild(p);

    if(Array.isArray(q.choices) && q.choices.length){
      const ul = document.createElement('ul');
      ul.style.marginTop = '8px';
      ul.style.paddingLeft = '18px';
      q.choices.forEach((c, i) => {
        const li = document.createElement('li');
        li.textContent = c;
        ul.appendChild(li);
      });
      card.appendChild(ul);
    }

    wrap.appendChild(card);
  });

  box.appendChild(wrap);
}

const LEVEL_TO_DIFFICULTY = {A1:1, A2:2, B1:3, B2:4, C1:5};
function syncLevelDifficulty(){
  const lvEl = document.getElementById('levelSel');
  const diffEl = document.getElementById('diffHidden');
  const info = document.getElementById('diffInfo');
  if(!lvEl || !diffEl) return;
  const lv = String(lvEl.value || 'A1').toUpperCase();
  const d = LEVEL_TO_DIFFICULTY[lv] || 1;
  diffEl.value = String(d);
  if(info) info.textContent = 'Mapped difficulty: ' + d;
}
(function(){
  const lvEl = document.getElementById('levelSel');
  if(lvEl) lvEl.addEventListener('change', syncLevelDifficulty);
  syncLevelDifficulty();
})();

async function aiGenerateTest(){
  const btn = document.getElementById('aiBtn');
  if(btn) btn.disabled = true;

  const form = document.getElementById('testForm');
  const csrf = form.querySelector('input[name="csrf"]').value;
  const topic = document.getElementById('topicSel').value;
  const qcount = document.getElementById('qcountSel').value;
  const skill = document.getElementById('skillSel').value;
  const level = document.getElementById('levelSel').value;
  const diff = document.getElementById('diffHidden').value;

  setStatus('Generating...');

  try{
    const res = await fetch(`${BASE_URL}/admin/api/ai_generate_test.php`,{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`csrf=${encodeURIComponent(csrf)}&topic=${encodeURIComponent(topic)}&qcount=${encodeURIComponent(qcount)}&skill=${encodeURIComponent(skill)}&level=${encodeURIComponent(level)}&difficulty=${encodeURIComponent(diff)}`
    });
    const data = await res.json();
    if(!data.ok){
      setStatus('AI failed: ' + (data.msg || 'error'));
      return;
    }

    document.getElementById('payloadJson').value = JSON.stringify(data);
    renderPreview(data.questions || []);
    setStatus('Done ✓ (review preview, then click Create Test)');
  }catch(e){
    setStatus('AI error.');
  }finally{
    if(btn) btn.disabled = false;
  }
}
</script>

</body>
</html>
