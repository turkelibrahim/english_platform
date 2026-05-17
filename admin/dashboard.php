<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/avatar.php';

$u = require_role('admin');

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


// Toplam öğrenciler
$st = db()->query("SELECT COUNT(*) FROM users WHERE role='student'");
$totalStudents = (int)$st->fetchColumn();

// Son 7 gün aktif öğrenciler
$st = db()->query("SELECT COUNT(*) FROM users WHERE role='student' AND last_active_at >= (NOW() - INTERVAL 7 DAY)");
$active7 = (int)$st->fetchColumn();


// Seviye dağılımı
$levels = db()->query("
  SELECT COALESCE(level,'-') AS level_label, COUNT(*) AS cnt
  FROM users
  WHERE role='student'
  GROUP BY COALESCE(level,'-')
  ORDER BY level_label
")->fetchAll();


// Skill performans özeti (son 30 gün, öğrenciler)
$skillStats = db()->query("
  SELECT q.skill,
         COUNT(*) AS total,
         SUM(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS correct
  FROM question_attempts qa
  JOIN questions q ON q.id=qa.question_id
  JOIN users u ON u.id=qa.user_id
  WHERE u.role='student'
    AND qa.created_at >= (NOW() - INTERVAL 30 DAY)
  GROUP BY q.skill
  ORDER BY q.skill
")->fetchAll();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Dashboard</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/daily_quote.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Dashboard"; $navActive="dashboard"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <?php if($showWelcome && $welcomeQuote): ?>
    <div id="dqOverlay" class="dq-overlay" hidden>
      <div class="dq-card" id="dqCard" role="dialog" aria-modal="true" aria-label="Quote of the Day">
        <div class="dq-top">
          <div>
            <div class="dq-badge">Welcome 👋</div>
            <div class="dq-hello">Hi, <?=htmlspecialchars($u['full_name'] ?? 'Admin')?>!</div>
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
          const ems = ['💖','⭐','😊','✨','🎉'];
          const b = okBtn.getBoundingClientRect();
          const c = card.getBoundingClientRect();
          const x0 = (b.left + b.width/2) - c.left;
          const y0 = (b.top + b.height/2) - c.top;

          for(let i=0;i<18;i++){
            const s = document.createElement('span');
            s.className = 'dq-emoji';
            s.textContent = ems[Math.floor(Math.random()*ems.length)];
            s.style.left = x0 + 'px';
            s.style.top  = y0 + 'px';
            s.style.setProperty('--dx', ((Math.random()*220)-110).toFixed(1)+'px');
            s.style.setProperty('--dy', (-(Math.random()*180)-40).toFixed(1)+'px');
            s.style.setProperty('--rot', ((Math.random()*240)-120).toFixed(0)+'deg');
            emojiLayer.appendChild(s);
            setTimeout(()=> s.remove(), 980);
          }
        }

        overlay.hidden = false;
        requestAnimationFrame(() => card.classList.add('dq-show'));
        if(okBtn) okBtn.addEventListener('click', () => { burst(); setTimeout(hide, 420); });
        overlay.addEventListener('click', (e) => { if(e.target === overlay) hide(); });
        setTimeout(hide, 5000);
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
          <div class="h1" style="font-size:22px">Welcome, <?=htmlspecialchars($u['full_name'] ?? 'Admin')?> 👋</div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid single" style="margin-top:18px">
    <div class="card">
      <div class="h1">Overview</div>
<div class="hr"></div>

      <div class="row">
        <div class="badge">👩‍🎓 Students: <b><?=$totalStudents?></b></div>
        <div class="badge">🟢 Active (7d): <b><?=$active7?></b></div>
      </div>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Level Distribution</div>

      <?php if(!$levels): ?>
        <div class="toast">No students yet.</div>
      <?php else: ?>
        <?php foreach($levels as $lv): ?>
          <div class="card" style="box-shadow:none; margin-top:10px">
            <div style="display:flex; justify-content:space-between; gap:10px">
              <div><b><?=htmlspecialchars($lv['level_label'])?></b></div>
              <div class="muted"><?= (int)$lv['cnt'] ?> students</div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="hr"></div>
      <div class="h1" style="font-size:18px">Skill accuracy (last 30 days)</div>
      <?php if(!$skillStats): ?>
        <div class="toast">No attempt data yet.</div>
      <?php else: ?>
        <?php foreach($skillStats as $s):
          $t=(int)$s['total']; $c=(int)$s['correct']; $p = $t? round(100*$c/$t):0;
        ?>
          <div class="card" style="box-shadow:none; margin-top:10px">
            <div style="display:flex; justify-content:space-between; gap:10px">
              <div><b><?=htmlspecialchars($s['skill'])?></b></div>
              <div class="muted"><?=$p?>% · <?=$c?>/<?=$t?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
