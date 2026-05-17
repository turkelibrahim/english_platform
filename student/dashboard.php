<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/tasks.php';
require_once __DIR__ . '/../includes/avatar.php';

$u = require_role('student');

$avatarRel = is_string($u['avatar_url'] ?? null) ? trim((string)$u['avatar_url']) : '';
$avatarSrc = $avatarRel !== '' ? avatar_public_url($avatarRel) : '';

// Welcome popup + sequential quote (once per session)
$showWelcome = false;
$welcomeQuote = null;
if (empty($_SESSION['welcome_shown'])) {
  $_SESSION['welcome_shown'] = 1;
  $showWelcome = true;
  $welcomeQuote = quote_of_day();
}


// If the user has no active tasks yet, try generating some.
$activeSt = db()->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id=? AND status IN ('open','in_progress')");
$activeSt->execute([$u['id']]);
$active = (int)$activeSt->fetchColumn();
if ($active === 0) {
  refresh_tasks_for_user((int)$u['id'], 3);
}

$st = db()->prepare("SELECT * FROM user_tasks WHERE user_id=? ORDER BY id DESC LIMIT 12");
$st->execute([$u['id']]);
$tasks = $st->fetchAll();

?><!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Dashboard</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/daily_quote.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'])?>">
<div class="container">
  <?php $navPage="Dashboard"; $navActive="dashboard"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <?php if($showWelcome && $welcomeQuote): ?>
    <div id="dqOverlay" class="dq-overlay" hidden>
      <div class="dq-card" id="dqCard" role="dialog" aria-modal="true" aria-label="Quote of the Day">
        <div class="dq-top">
          <div>
            <div class="dq-badge">Welcome 👋</div>
            <div class="dq-hello">Hi, <?=htmlspecialchars($u['full_name'] ?? 'Student')?>!</div>
            <div class="dq-title">Quote of the Day</div>
          </div>
        </div>
        <div class="dq-content">
          <p class="dq-quote">“<?=htmlspecialchars($welcomeQuote['quote_text'] ?? '')?>”</p>
          <?php if(!empty($welcomeQuote['author'])): ?>
            <div class="dq-author">— <?=htmlspecialchars($welcomeQuote['author'])?></div>
          <?php endif; ?>
          <div class="dq-divider"></div>
          <div class="dq-actions">
            <button type="button" class="btn primary dq-ok-btn" id="dqOk">Ok</button>
          </div>
          </div>
        <div class="dq-emoji-layer" id="dqEmojiLayer" aria-hidden="true"></div>
      </div>
    </div>
    <script>
      (function(){
        const overlay = document.getElementById('dqOverlay');
        const card = document.getElementById('dqCard');
        const okBtn = document.getElementById('dqOk');
        const emojiLayer = document.getElementById('dqEmojiLayer');
        if(!overlay || !card) return;

        const hide = () => {
          card.classList.remove('dq-show');
          setTimeout(() => { overlay.hidden = true; }, 230);
        };

        function burst(){
          if(!okBtn || !emojiLayer) return;
          const ems = ['💖','⭐','😊'];
          const b = okBtn.getBoundingClientRect();
          const c = card.getBoundingClientRect();
          const x0 = (b.left + b.width/2) - c.left;
          const y0 = (b.top + b.height/2) - c.top;

          for(let i=0;i<18;i++){
            const s = document.createElement('span');
            s.className = 'dq-emoji';
            s.textContent = ems[Math.floor(Math.random()*ems.length)];
            const dx = (Math.random()*2-1) * 220;
            const dy = (Math.random()*2-1) * 160 - 40;
            const rot = (Math.random()*2-1) * 220;
            s.style.left = x0 + 'px';
            s.style.top = y0 + 'px';
            s.style.setProperty('--dx', dx + 'px');
            s.style.setProperty('--dy', dy + 'px');
            s.style.setProperty('--rot', rot + 'deg');
            emojiLayer.appendChild(s);
            s.addEventListener('animationend', () => s.remove());
          }
        }

        overlay.hidden = false;
        requestAnimationFrame(() => card.classList.add('dq-show'));

        if(okBtn) okBtn.addEventListener('click', () => {
          burst();
          setTimeout(hide, 520);
        });
      })();
    </script>
  <?php endif; ?>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:flex-start">
      <div class="row" style="gap:14px; align-items:center">
        <div class="avatar-rect">
          <?php if($avatarSrc): ?>
            <img src="<?=htmlspecialchars($avatarSrc, ENT_QUOTES)?>" alt="Avatar">
          <?php endif; ?>
        </div>
        <div>
          <div class="h1" style="font-size:22px">Welcome, <?=htmlspecialchars($u['full_name'] ?? 'Student')?> 👋</div>
          <div class="muted" style="margin-top:6px">
            Level: <b><?=htmlspecialchars($u['level'] ?? '—')?></b> · Points: <b><?= (int)($u['points'] ?? 0) ?></b>
          </div>
        </div>
      </div>
      <div>
        <a class="btn" href="<?=BASE_URL?>/student/refresh_tasks.php">Refresh tasks</a>
      </div>
    </div>
  </div>

  <?php if(($_GET['levelup'] ?? '') === '1'): ?>
    <div class="card" style="margin-top:18px">
      <div class="toast" style="position:relative; overflow:hidden">
        <b>🎉 Level Up!</b>
        <div class="muted" style="margin-top:6px"><?=htmlspecialchars((string)($_GET['from'] ?? ''))?> → <b><?=htmlspecialchars((string)($_GET['to'] ?? ''))?></b></div>
        <div class="muted" style="margin-top:6px">New tasks and lessons are now based on your new level.</div>
      </div>
    </div>

    <script>
      (function(){
        const emojis = ['🎉','✨','💖','🌟','🔥','🚀','🫶','😻'];
        const burstCount = 40;

        function pop(){
          for(let i=0;i<burstCount;i++){
            const s = document.createElement('span');
            s.className = 'emoji-particle';
            s.textContent = emojis[Math.floor(Math.random()*emojis.length)];

            const left = Math.random()*100;
            const size = 16 + Math.random()*18;
            const delay = Math.random()*0.18;
            const dur = 1.1 + Math.random()*0.9;

            s.style.left = left + 'vw';
            s.style.fontSize = size + 'px';
            s.style.animationDelay = delay + 's';
            s.style.animationDuration = dur + 's';

            document.body.appendChild(s);
            setTimeout(()=>s.remove(), (dur+delay)*1000 + 200);
          }
        }

        // Two bursts for extra cute effect
        pop();
        setTimeout(pop, 420);
      })();
    </script>
  <?php endif; ?>

  <div class="card" style="margin-top:18px">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div>
        <div class="h1" style="font-size:18px">My Tasks</div>
</div>
    </div>
    <div class="hr"></div>

    <?php if(!$tasks): ?>
      <div class="toast">No tasks yet. Try refreshing.</div>
    <?php else: ?>
      <div style="display:grid; gap:12px">
        <?php foreach($tasks as $t):
          $p = task_progress((int)$u['id'], (int)$t['id']);
          $done = (int)$p['done'];
          $total = max(1, (int)$p['total']);
          $pct = (int)round(100 * $done / $total);
          $status = (string)$t['status'];
        ?>
          <div class="card" style="margin:0">
            <div class="row" style="justify-content:space-between; align-items:flex-start">
              <div>
                <div style="font-weight:900; font-size:16px"><?=htmlspecialchars($t['title'])?></div>
                <div class="muted" style="margin-top:4px">Topic: <b><?=htmlspecialchars($t['topic'])?></b> · Status: <?=htmlspecialchars(ucfirst($status))?></div>
              </div>
              <div>
                <?php if($status !== 'done'): ?>
                  <a class="btn primary" href="<?=BASE_URL?>/student/task_start.php?task_id=<?= (int)$t['id'] ?>">Start</a>
                <?php else: ?>
                  <span class="pill">Completed</span>
                <?php endif; ?>
              </div>
            </div>

            <div style="height:10px"></div>
            <div class="progress" aria-label="progress"><div class="progress-bar" style="width:<?=$pct?>%"></div></div>
            <div class="muted" style="margin-top:8px"><?=$done?> / <?=$total?> questions</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
