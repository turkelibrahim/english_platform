<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('student');
$id = (int)($_GET['id'] ?? 0);
if(!$id) exit("Missing id");

$st = db()->prepare("
  SELECT l.*,
    EXISTS(
      SELECT 1 FROM favorites f
      WHERE f.user_id=? AND f.fav_type='lesson' AND f.ref_id=l.id
    ) AS is_fav
  FROM lessons l
  WHERE l.id=? AND l.is_active=1
  LIMIT 1
");
$st->execute([$u['id'], $id]);
$l = $st->fetch();
if(!$l) exit("Lesson not found or inactive");
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?=htmlspecialchars($l['title'])?></title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <script>const BASE_URL = "<?=BASE_URL?>";</script>

  <style>
    .word-tap {
      cursor: pointer;
      border-bottom: 1px dashed rgba(141,169,196,.55);
      padding: 0 2px;
      border-radius: 6px;
      transition: background .15s ease;
    }
    .word-tap:hover { background: rgba(141,169,196,.18); }
    .mini-muted{ opacity:.7; font-size:13px; margin-top:6px; }
  </style>
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <?php $navPage="Lessons"; $navActive="lessons"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <div class="card" style="margin-top:18px">
    <div class="muted">
      <?=htmlspecialchars($l['skill'])?> · <?=htmlspecialchars($l['material_type'])?>
      · diff <?= (int)$l['difficulty'] ?> · level <?=htmlspecialchars($l['level'] ?? '-')?>
    </div>
    <div class="h1" style="margin-top:8px"><?=htmlspecialchars($l['title'])?></div>
      <div style="margin-top:10px; display:flex; gap:10px; flex-wrap:wrap">
        <a class="btn primary" href="<?=BASE_URL?>/student/lesson_quiz.php?id=<?=(int)$l['id']?>">Practice this lesson</a>
      </div>

    <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap">
      <button class="btn" id="favBtn" onclick="toggleFav()">
        <?= ((int)$l['is_fav']===1) ? '★ Saved' : '☆ Save' ?>
      </button>
      <a class="btn primary" href="<?=BASE_URL?>/student/practice.php">Practice</a>
    </div>

    <div class="hr"></div>

    <!-- Lesson Content -->
    <div id="lessonBody" style="line-height:1.65">
      <?= $l['body_html'] ?>
    </div>

    <div class="hr"></div>
    <div class="muted" style="font-size:13px">
      Hint: click words in the text to open the mini dictionary.
    </div>
  </div>
</div>

<form id="csrfForm" style="display:none">
  <input type="hidden" name="csrf" value="<?=csrf_token()?>">
</form>

<!-- Dictionary Modal -->
<div id="dictBackdrop" class="modal-backdrop" onclick="closeDict(event)">
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
        <button class="btn primary" onclick="addToNotebook()">📒 Add to Notebook</button>
        <button class="btn" onclick="copyWord()">Copy</button>
      </div>
      <div id="dictToast" class="mini-muted" style="margin-top:10px"></div>
    </div>
  </div>
</div>

<script>
let currentDict = { word:'', meaning:'', example:'' };

function closeDict(e){
  if(e && e.type === 'click') {} // ok
  document.getElementById('dictBackdrop').style.display = 'none';
}

function openDict(word){
  currentDict = { word, meaning:'', example:'' };
  document.getElementById('dictBackdrop').style.display = 'flex';
  document.getElementById('dictWord').textContent = word;
  document.getElementById('dictMeta').textContent = 'Loading…';
  document.getElementById('dictContent').textContent = 'Loading…';
  document.getElementById('dictToast').textContent = '';

  // Free public dictionary API (browser fetch)
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
        `<div class="mini-muted">No definition found. You can still add a note to your Notebook manually.</div>`;
    });
}

function copyWord(){
  navigator.clipboard?.writeText(currentDict.word);
  document.getElementById('dictToast').textContent = 'Copied ✅';
}

async function addToNotebook(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const term = currentDict.word;
  if(!term){ return; }

  const res = await fetch(`${BASE_URL}/student/api/notebook_add_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&term=${encodeURIComponent(term)}&meaning=${encodeURIComponent(currentDict.meaning)}&example=${encodeURIComponent(currentDict.example)}&note=${encodeURIComponent('Lesson: <?=htmlspecialchars(addslashes($l['title']))?>')}`
  });

  let ok = false;
  try { ok = (await res.json()).ok === true; } catch(e) {}

  document.getElementById('dictToast').textContent = ok ? 'Added to Notebook ✅' : 'Could not add (endpoint may not return JSON)';
}

async function toggleFav(){
  const csrf = document.querySelector('#csrfForm input[name="csrf"]').value;
  const res = await fetch(`${BASE_URL}/student/api/favorite_toggle_ajax.php`,{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`csrf=${encodeURIComponent(csrf)}&fav_type=lesson&ref_id=<?= (int)$l['id'] ?>`
  });
  const data = await res.json();
  if(!data.ok){ alert('Action failed'); return; }
  document.getElementById('favBtn').textContent =
    (data.state === 'added') ? '★ Saved' : '☆ Save';
}

// --- Make lesson words clickable (client-side) ---
function escapeHtml(str){
  return (str ?? '').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#039;");
}

function wrapClickableWords(root){
  const stop = new Set([
    'the','and','you','your','with','this','that','from','have','has','had','are','were','was','will','would',
    'can','could','should','to','in','on','at','for','of','a','an','is','it','as','or','but','not','be','we',
    'they','he','she','i','my','me','our','us','their','them','his','her'
  ]);

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode(node){
      if(!node.nodeValue || node.nodeValue.trim().length < 8) return NodeFilter.FILTER_REJECT;
      if(node.parentElement && ['SCRIPT','STYLE','A','CODE','PRE'].includes(node.parentElement.tagName)) return NodeFilter.FILTER_REJECT;
      return NodeFilter.FILTER_ACCEPT;
    }
  });

  let n, wrapped = 0, LIMIT = 140;
  const textNodes = [];
  while((n = walker.nextNode()) && wrapped < LIMIT){
    textNodes.push(n);
  }

  for(const node of textNodes){
    if(wrapped >= LIMIT) break;
    const text = node.nodeValue;

    const regex = /\b[A-Za-z]{4,}\b/g;
    let m, last = 0;
    let frag = document.createDocumentFragment();
    let changed = false;

    while((m = regex.exec(text)) && wrapped < LIMIT){
      const word = m[0];
      const low = word.toLowerCase();
      if(stop.has(low)) continue;

      const before = text.slice(last, m.index);
      if(before) frag.appendChild(document.createTextNode(before));

      const span = document.createElement('span');
      span.className = 'word-tap';
      span.textContent = word;
      span.dataset.word = low;
      span.addEventListener('click', ()=> openDict(low));
      frag.appendChild(span);

      last = m.index + word.length;
      changed = true;
      wrapped++;
    }

    if(!changed) continue;
    const rest = text.slice(last);
    if(rest) frag.appendChild(document.createTextNode(rest));
    node.parentNode.replaceChild(frag, node);
  }
}

wrapClickableWords(document.getElementById('lessonBody'));
</script>
</body>
</html>
