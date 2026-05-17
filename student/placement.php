<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/utils.php';


$u = require_role('student');
if ((int)$u['placement_completed'] === 1) {
  header("Location: ".BASE_URL."/student/dashboard.php");
  exit;
}

$qs = db()->query("SELECT id, skill, prompt, choices_json, media_url, hint, example_sentence FROM questions
                   WHERE is_placement=1 AND is_active=1 ORDER BY RAND() LIMIT 12")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Placement Test</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
  <div class="container">
    <div class="nav">
      <div class="brand"><div class="logo"></div> Placement</div>
      <div class="nav-right">
        <a class="btn" href="<?=BASE_URL?>/student/profile.php">Profile</a>
      <a class="btn" href="<?=BASE_URL?>/public/logout.php">Logout</a>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="h1">Quick Placement Test</div>
      <div class="muted">This will take a few minutes. When you finish, your starting plan will be prepared.</div>
      <div class="hr"></div>

      <div id="app"></div>
    </div>
  </div>

<script>
const questions = <?=json_encode($qs, JSON_UNESCAPED_UNICODE)?>;

let idx = 0;
let answers = []; // {question_id, user_answer}

function render(){
  const q = questions[idx];
  if(!q){
    document.getElementById('app').innerHTML = `
      <div class="toast">Test finished. Calculating your results...</div>
    `;
    submitAll();
    return;
  }

  const choices = q.choices_json ? JSON.parse(q.choices_json) : null;

  let media = '';
  if(q.media_url){
    if(q.media_url.endsWith('.mp3') || q.media_url.endsWith('.wav')){
      media = `<audio controls src="${q.media_url}" style="width:100%; margin:10px 0"></audio>`;
    } else {
      media = `<img src="${q.media_url}" style="max-width:100%; border-radius:14px; margin:10px 0">`;
    }
  }

  let body = `
    <div class="muted">Question ${idx+1} / ${questions.length} · ${q.skill}</div>
    <div class="h1" style="font-size:20px; margin-top:10px">${escapeHtml(q.prompt)}</div>
    ${media}
  `;

  if(choices){
    body += `<div style="margin-top:10px; display:grid; gap:10px">` +
      choices.map((c,i)=>`
        <button class="btn answer-btn" onclick="answer(${q.id}, '${i}')">${escapeHtml(c)}</button>
      `).join('') +
    `</div>`;
  }else{
    body += `
      <textarea class="input" id="w" rows="4" placeholder="Type your answer..."></textarea>
      <div style="height:10px"></div>
      <button class="btn primary answer-btn" onclick="answer(${q.id}, document.getElementById('w').value)">Continue</button>
    `;
  }

  body += `
    <div class="hr"></div>
    <button class="btn" onclick="showHint()">Hint</button>
    <div id="hint" class="muted" style="margin-top:10px; display:none">
      ${q.hint ? `<div><b>Hint:</b> ${escapeHtml(q.hint)}</div>` : `<div><b>Hint:</b> No hint available for this question.</div>`}
      ${q.example_sentence ? `<div style="margin-top:6px"><b>Example:</b> ${escapeHtml(q.example_sentence)}</div>` : ``}
    </div>
    <div id="feedback" style="margin-top:12px"></div>
  `;

  document.getElementById('app').innerHTML = body;
}

function showHint(){
  const el = document.getElementById('hint');
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}

async function answer(qid, val){
  // anlık feedback için server’a tek soruluk gönder
  const res = await fetch(`${BASE_URL}/student/api/placement_submit.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`question_id=${encodeURIComponent(qid)}&user_answer=${encodeURIComponent(val)}`
  });
  const data = await res.json();

  const fb = document.getElementById('feedback');
  fb.innerHTML = `
    <div class="toast">
      <b>${data.correct ? 'Correct!' : 'Incorrect.'}</b><br>
      <span class="muted">${escapeHtml(data.explanation || '')}</span>
      ${data.example ? `<div class="muted" style="margin-top:8px">Example: ${escapeHtml(data.example)}</div>`:''}
    </div>
    <div style="height:10px"></div>
    <button class="btn primary" onclick="next()">Next</button>
  `;

  // lock buttons
  document.querySelectorAll('#app button.answer-btn').forEach(b=> b.disabled = true);
  const nextBtn = document.querySelector('#feedback button.btn');
  if (nextBtn) nextBtn.disabled = false;


  answers.push({question_id: qid, user_answer: val, correct: data.correct});
}

function next(){ idx++; render(); }

async function submitAll(){
  // bittiğinde sonuç hesapla
  const res = await fetch(`${BASE_URL}/student/api/placement_submit.php`,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({finish: true})
  });
  const data = await res.json();
  window.location.href = `${BASE_URL}/student/dashboard.php?welcome=1`;
}

function escapeHtml(str){
  return (str ?? '').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

render();
</script>
</body>
</html>
