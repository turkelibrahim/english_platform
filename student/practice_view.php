<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');
$qid = (int)($_GET['qid'] ?? ($_GET['id'] ?? 0));
$taskId = (int)($_GET['task_id'] ?? 0);
$lessonId = (int)($_GET['lesson_id'] ?? 0);
if(!$qid){ http_response_code(400); exit('Missing question id'); }

$st = db()->prepare("
  SELECT q.*,
    EXISTS(
      SELECT 1 FROM favorites f
      WHERE f.user_id=? AND f.fav_type='question' AND f.ref_id=q.id
    ) AS is_fav
  FROM questions q
  WHERE q.id=? AND q.is_active=1
  LIMIT 1
");
$st->execute([$u['id'], $qid]);
$q = $st->fetch();
if(!$q) exit("Question not found");

$choices = $q['choices_json'] ? json_decode($q['choices_json'], true) : null;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Question</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>

  <style>
    .word-tap{
      cursor:pointer;
      border-bottom:1px dashed rgba(141,169,196,.55);
      padding:0 2px;
      border-radius:6px;
      transition: background .15s ease;
    }
    .word-tap:hover{ background: rgba(141,169,196,.18); }
    .mini-muted{ opacity:.75; font-size:13px; margin-top:6px; }
  </style>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <?php $navPage="Practice"; $navActive="practice"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <?php if($taskId > 0): ?>
    <div class="card" style="margin-top:18px">
      <div class="row" style="justify-content:space-between; align-items:center">
        <div>
          <div style="font-weight:900">Task mode</div>
</div>
        <a class="btn" href="<?=BASE_URL?>/student/dashboard.php">Back to tasks</a>
      </div>
    </div>
  <?php endif; ?>

  <div class="card" style="margin-top:18px">
    <div class="muted"><?=htmlspecialchars($q['skill'])?> · diff <?= (int)$q['difficulty'] ?></div>
    <div class="h1" id="qPrompt" style="font-size:20px; margin-top:10px"><?=htmlspecialchars($q['prompt'])?></div>

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
          <button class="btn choiceBtn" data-choice="<?= (string)$i ?>" onclick="submitAnswer('<?= (string)$i ?>')"><?=htmlspecialchars($c)?></button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <textarea class="input" id="w" rows="4" placeholder="Type your answer..."></textarea>
      <div style="height:10px"></div>
      <button class="btn primary" onclick="submitAnswer(document.getElementById('w').value)">Check Answer</button>
    <?php endif; ?>

    <div class="hr"></div>
    <div class="row">
      <button class="btn" onclick="toggleHint()">Hint</button>
      <button class="btn" id="favBtn" onclick="toggleFav()"><?= ((int)$q['is_fav']===1) ? '★ Saved' : '☆ Save' ?></button>
      <button class="btn" onclick="toggleNote()">📒 Notebook</button>
    </div>

    <div id="hint" class="muted" style="margin-top:10px; display:none">
      <div><b>Hint:</b> <span id="hintText"><?=htmlspecialchars($q['hint'] ?: 'No hint available for this question.')?></span></div>

      <div id="exRow" style="margin-top:6px; <?=(empty($q['example_sentence'])?'display:none':'')?>"><b>Example:</b>
        <span id="exText"><?=htmlspecialchars($q['example_sentence'] ?? '')?></span>
      </div>

      <?php if(empty($q['hint']) || empty($q['example_sentence'])): ?>
        <div style="margin-top:10px">
          <button class="btn" id="aiHintBtn" onclick="generateHint()">✨ AI: generate hint & example</button>
          <span class="muted" id="aiHintStatus" style="margin-left:8px"></span>
        </div>
      <?php endif; ?>
    </div>

    <div id="feedback" style="margin-top:12px"></div>
  </div>

  <div class="card" id="noteBox" style="margin-top:18px; display:none">
    <div class="h1" style="font-size:18px">Add to Notebook</div>
<div class="hr"></div>

    <input class="input" id="term" placeholder="Word / term"><br><br>
    <input class="input" id="meaning" placeholder="Meaning (optional)"><br><br>
    <input class="input" id="example" placeholder="Example sentence (optional)"><br><br>
    <textarea class="input" id="note" rows="3" placeholder="Note (optional)"></textarea><br><br>
    <button class="btn primary" onclick="saveNote()" style="width:100%">Save</button>
  </div>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<!-- Mini Dictionary Modal (word tap) -->
<div id="dictBackdrop" class="modal-backdrop" onclick="closeDict(event)" style="display:none">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="dictWord">Word</div>
        <div class="mini-muted" id="dictMeta">Loading…</div>
      </div>
      <button class="xbtn" onclick="closeDict()">×</button>
    </div>
    <div class="modal-body">
      <div id="dictContent" class="mini-muted">Loading…</div>

      <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
        <button class="btn primary" onclick="addToNotebookFromDict(event)">📒 Add to Notebook</button>
        <button class="btn" onclick="copyWord(event)">Copy</button>
      </div>
      <div id="dictToast" class="mini-muted" style="margin-top:10px"></div>
    </div>
  </div>
</div>

<script>
let currentDict = { word:'', meaning:'', example:'' };

function toggleHint(){
  const el = document.getElementById('hint');
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

// --- Mini dictionary for question words ---
function closeDict(e){
  if(e && e.type === 'click'){} // ok
  document.getElementById('dictBackdrop').style.display = 'none';
}

function openDict(word){
  currentDict = { word, meaning:'', example:'' };
  document.getElementById('dictBackdrop').style.display = 'flex';
  document.getElementById('dictWord').textContent = word;
  document.getElementById('dictMeta').textContent = 'Loading…';
  document.getElementById('dictContent').textContent = 'Loading…';
  document.getElementById('dictToast').textContent = '';

  fetch(`https://api.dictionaryapi.dev/api/v2/entries/en/${encodeURIComponent(word)}`)
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(data => {
      const entry = data?.[0];
      const phon = entry?.phonetic || entry?.phonetics?.find(p=>p.text)?.text || '';
      const part = entry?.meanings?.[0]?.partOfSpeech || '';
      const def  = entry?.meanings?.[0]?.definitions?.[0]?.definition || '';
      const ex   = entry?.meanings?.[0]?.definitions?.[0]?.example || '';
      const syn  = entry?.meanings?.[0]?.definitions?.[0]?.synonyms?.slice(0,6) || [];

      currentDict.meaning = def || '';
      currentDict.example = ex || '';

      document.getElementById('dictMeta').textContent = [phon, part].filter(Boolean).join(' · ') || 'Definition';
      let html = '';
      html += def ? `<div><b>Meaning:</b> ${escapeHtml(def)}</div>` : `<div>No definition found.</div>`;
      if(ex) html += `<div class="mini-muted" style="margin-top:8px"><b>Example:</b> ${escapeHtml(ex)}</div>`;
      if(syn.length){
        html += `<div style="margin-top:10px"><b>Synonyms:</b></div>`;
        html += syn.map(s=>`<span class="pill">${escapeHtml(s)}</span>`).join('');
      }
      document.getElementById('dictContent').innerHTML = html;
    })
    .catch(()=>{
      document.getElementById('dictMeta').textContent = 'No online definition';
      document.getElementById('dictContent').innerHTML =
        `<div class="mini-muted">No definition found. You can still add this word to your Notebook.</div>`;
    });
}

function copyWord(e){
  if(e) e.preventDefault();
  navigator.clipboard?.writeText(currentDict.word);
  document.getElementById('dictToast').textContent = 'Copied ✅';
}

async function addToNotebookFromDict(e){
  if(e) e.preventDefault();
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const term = currentDict.word;
  if(!term) return;

  const noteCtx = `Question: <?=htmlspecialchars(addslashes($q['prompt']))?>`;
  const res = await fetch(`${BASE_URL}/student/api/notebook_add_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&term=${encodeURIComponent(term)}&meaning=${encodeURIComponent(currentDict.meaning)}&example=${encodeURIComponent(currentDict.example)}&note=${encodeURIComponent(noteCtx)}`
  });

  let ok = false;
  try { ok = (await res.json()).ok === true; } catch(e) {}
  document.getElementById('dictToast').textContent = ok ? 'Added to Notebook ✅' : 'Could not add';
}

function wrapTextToWordSpans(text){
  // Keep punctuation; wrap only words (4+ chars)
  return (text ?? '').replace(/\b([A-Za-z]{4,})\b/g, (m)=>`<span class="word-tap" data-word="${m.toLowerCase()}">${m}</span>`);
}

function makeWordTaps(){
  const prompt = document.getElementById('qPrompt');
  if(prompt){
    prompt.innerHTML = wrapTextToWordSpans(prompt.textContent);
  }

  document.querySelectorAll('.choiceBtn').forEach(btn=>{
    // preserve the click-to-answer on the button, but make word taps stop propagation
    const raw = btn.textContent;
    btn.innerHTML = wrapTextToWordSpans(raw);
  });

  // Event delegation
  document.addEventListener('click', (ev)=>{
    const t = ev.target;
    if(t && t.classList && t.classList.contains('word-tap')){
      ev.preventDefault();
      ev.stopPropagation();
      openDict(t.getAttribute('data-word'));
    }
  }, true);
}


function generateHint(){
  const btn = document.getElementById('aiHintBtn');
  const status = document.getElementById('aiHintStatus');
  if(btn) btn.disabled = true;
  if(status) status.textContent = 'Generating...';

  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  fetch(`${BASE_URL}/student/api/ai_hint.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&question_id=<?= (int)$q['id'] ?>`
  }).then(r=>r.json()).then(data=>{
    if(!data.ok){
      if(status) status.textContent = 'AI failed.';
      if(btn) btn.disabled = false;
      return;
    }
    if(data.hint){
      const ht = document.getElementById('hintText');
      if(ht) ht.textContent = data.hint;
    }
    if(data.example){
      const exRow = document.getElementById('exRow');
      const exText = document.getElementById('exText');
      if(exText) exText.textContent = data.example;
      if(exRow) exRow.style.display = 'block';
    }
    if(status) status.textContent = 'Done ✓';
    if(btn) btn.style.display = 'none';
  }).catch(()=>{
    if(status) status.textContent = 'AI error.';
    if(btn) btn.disabled = false;
  });
}

function toggleNote(){
  const el = document.getElementById('noteBox');
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

async function submitAnswer(val){
  const res = await fetch(`${BASE_URL}/student/api/practice_submit.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`question_id=<?= (int)$q['id'] ?>&user_answer=${encodeURIComponent(val)}&task_id=<?= (int)$taskId ?>&lesson_id=<?= (int)$lessonId ?>`
  });
  const data = await res.json();

  document.getElementById('feedback').innerHTML = (() => {
    const isTask = (<?= (int)$taskId ?> > 0);
    let action = '';
    if(isTask){
      if(data.task_can_finish){
        action = `<div style="margin-top:12px"><a class="btn primary" href="${data.finish_url || (BASE_URL + '/student/task_result.php?task_id=<?= (int)$taskId ?>')}">Finish Test ✓</a></div>`;
      } else {
        action = `<div style="margin-top:12px"><a class="btn primary" href="${data.next_url || (BASE_URL + '/student/task_next.php?task_id=<?= (int)$taskId ?>')}">Next question →</a></div>`;
      }
    }

    const remainingLine = (isTask && typeof data.task_remaining === 'number')
      ? `<div class="muted" style="margin-top:6px">Remaining: <b>${data.task_remaining}</b></div>`
      : '';

    return `
    <div class="toast">
      <b>${data.correct ? 'Correct!' : 'Incorrect.'}</b>
      ${data.points_added ? `<div class="muted" style="margin-top:6px">+${data.points_added} points</div>`:''}
      ${remainingLine}
      ${(data.new_badges && data.new_badges.length) ? `<div style="margin-top:10px">` + data.new_badges.map(b => `<span class="pill" style="margin-right:6px">🏅 ${escapeHtml(b.title)}</span>`).join('') + `</div>` : ''}
      ${data.explanation ? `<div class="muted" style="margin-top:8px">${escapeHtml(data.explanation)}</div>`:''}
      ${data.example ? `<div class="muted" style="margin-top:8px">Example: ${escapeHtml(data.example)}</div>`:''}
      ${action}
    </div>
  `;
  })();

  document.querySelectorAll('button.btn').forEach(b=>{
    // navbar butonlarını kilitleme: sadece soru seçeneklerini kilitlemek istersen sınıf ekleyebilirsin
  });
}

async function toggleFav(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=question&ref_id=<?= (int)$q['id'] ?>`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }

  document.getElementById('favBtn').textContent =
    (data.state === 'added') ? '★ Saved' : '☆ Save';
}

async function saveNote(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const term = document.getElementById('term').value.trim();
  const meaning = document.getElementById('meaning').value.trim();
  const example = document.getElementById('example').value.trim();
  const note = document.getElementById('note').value.trim();
  if(!term){ alert('Word cannot be empty'); return; }

  const res = await fetch(`${BASE_URL}/student/api/notebook_add_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&term=${encodeURIComponent(term)}&meaning=${encodeURIComponent(meaning)}&example=${encodeURIComponent(example)}&note=${encodeURIComponent(note)}`
  });
  const data = await res.json();
  alert(data.ok ? 'Added to Notebook ✅' : 'Could not save');
}

function escapeHtml(str){
  return (str ?? '').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

// init word taps
makeWordTaps();
</script>
</body>
</html>
