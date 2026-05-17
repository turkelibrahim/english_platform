<?php
require_once __DIR__ . '/../includes/rbac.php';
$u = require_role('student');

// Use account creation date as the beginning of analytics.
$createdAtStr = (string)($u['created_at'] ?? 'now');
$createdAt = new DateTime($createdAtStr);
$today = new DateTime('now');
$daysSince = (int)$createdAt->diff($today)->format('%a');

// --- Progress analytics chart series (Total vs Correct) ---
$groupMode = 'day'; // day | week | month
if ($daysSince > 365) $groupMode = 'month';
else if ($daysSince > 90) $groupMode = 'week';

$attemptLabels = [];
$attemptTotals = [];
$attemptCorrect = [];
$attemptPeriodLabel = '';

if ($groupMode === 'day') {
  $attemptPeriodLabel = 'Day';
  $startDate = new DateTime($createdAt->format('Y-m-d'));
  $endDate = new DateTime($today->format('Y-m-d'));

  $map = [];
  $q = db()->prepare("SELECT DATE(created_at) d, COUNT(*) total, SUM(is_correct) correct\n                    FROM question_attempts\n                    WHERE user_id=? AND attempt_type='practice' AND created_at >= ?\n                    GROUP BY DATE(created_at)\n                    ORDER BY d ASC");
  $q->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
  foreach ($q->fetchAll() as $r) {
    $k = (string)$r['d'];
    $map[$k] = ['total'=>(int)$r['total'], 'correct'=>(int)$r['correct']];
  }

  $it = clone $startDate;
  while ($it <= $endDate) {
    $k = $it->format('Y-m-d');
    $attemptLabels[] = $it->format('M j');
    $attemptTotals[] = $map[$k]['total'] ?? 0;
    $attemptCorrect[] = $map[$k]['correct'] ?? 0;
    $it->modify('+1 day');
  }
} elseif ($groupMode === 'week') {
  $attemptPeriodLabel = 'Week';
  $startWeek = new DateTime($createdAt->format('Y-m-d'));
  $startWeek->modify('monday this week');
  $endWeek = new DateTime($today->format('Y-m-d'));
  $endWeek->modify('monday this week');

  $map = [];
  $q = db()->prepare("SELECT YEARWEEK(created_at, 1) yw, COUNT(*) total, SUM(is_correct) correct\n                    FROM question_attempts\n                    WHERE user_id=? AND attempt_type='practice' AND created_at >= ?\n                    GROUP BY YEARWEEK(created_at, 1)\n                    ORDER BY yw ASC");
  $q->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
  foreach ($q->fetchAll() as $r) {
    $yw = (int)$r['yw'];
    $map[$yw] = ['total'=>(int)$r['total'], 'correct'=>(int)$r['correct']];
  }

  $it = clone $startWeek;
  while ($it <= $endWeek) {
    $yw = (int)$it->format('oW'); // ISO week-year + week
    $attemptLabels[] = $it->format('Y') . ' W' . $it->format('W');
    $attemptTotals[] = $map[$yw]['total'] ?? 0;
    $attemptCorrect[] = $map[$yw]['correct'] ?? 0;
    $it->modify('+1 week');
  }
} else { // month
  $attemptPeriodLabel = 'Month';
  $startMonth = new DateTime($createdAt->format('Y-m-01'));
  $endMonth = new DateTime($today->format('Y-m-01'));

  $map = [];
  $q = db()->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m-01') m, COUNT(*) total, SUM(is_correct) correct\n                    FROM question_attempts\n                    WHERE user_id=? AND attempt_type='practice' AND created_at >= ?\n                    GROUP BY DATE_FORMAT(created_at,'%Y-%m')\n                    ORDER BY m ASC");
  $q->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
  foreach ($q->fetchAll() as $r) {
    $k = (string)$r['m'];
    $map[$k] = ['total'=>(int)$r['total'], 'correct'=>(int)$r['correct']];
  }

  $it = clone $startMonth;
  while ($it <= $endMonth) {
    $k = $it->format('Y-m-01');
    $attemptLabels[] = $it->format('M Y');
    $attemptTotals[] = $map[$k]['total'] ?? 0;
    $attemptCorrect[] = $map[$k]['correct'] ?? 0;
    $it->modify('+1 month');
  }
}

// Practice accuracy + Skill accuracy (from account creation)
$st = db()->prepare("SELECT COUNT(*) total, SUM(is_correct) correct FROM question_attempts WHERE user_id=? AND attempt_type='practice' AND created_at >= ?");
$st->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
$r = $st->fetch();
$practiceTotal = (int)($r['total'] ?? 0);
$practiceCorrect = (int)($r['correct'] ?? 0);
$practiceWrong = max(0, $practiceTotal - $practiceCorrect);
$practiceAccuracy = $practiceTotal>0 ? round(($practiceCorrect/$practiceTotal)*100) : 0;

$skillLabels = [];
$skillAcc = [];
$skillQ = db()->prepare("SELECT q.skill, COUNT(*) total, SUM(qa.is_correct) correct\n                       FROM question_attempts qa\n                       JOIN questions q ON q.id=qa.question_id\n                       WHERE qa.user_id=? AND qa.attempt_type='practice' AND qa.created_at >= ?\n                       GROUP BY q.skill\n                       ORDER BY q.skill");
$skillQ->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
foreach ($skillQ->fetchAll() as $row) {
  $total = (int)($row['total'] ?? 0);
  $correct = (int)($row['correct'] ?? 0);
  $acc = $total>0 ? round(($correct/$total)*100) : 0;
  $skillLabels[] = $row['skill'];
  $skillAcc[] = $acc;
}

// Achievements: badges + notifications
$badgesQ = db()->prepare("SELECT b.title, b.description, ub.earned_at\n                         FROM user_badges ub\n                         JOIN badges b ON b.id=ub.badge_id\n                         WHERE ub.user_id=?\n                         ORDER BY ub.earned_at DESC\n                         LIMIT 12");
$badgesQ->execute([$u['id']]);
$earnedBadges = $badgesQ->fetchAll();

$notifQ = db()->prepare("SELECT title, message, is_read, created_at\n                        FROM notifications\n                        WHERE user_id=?\n                        ORDER BY created_at DESC\n                        LIMIT 10");
$notifQ->execute([$u['id']]);
$notifications = $notifQ->fetchAll();

// Existing progress page stats (also keep within account lifetime)
$st = db()->prepare("SELECT COUNT(*) total, SUM(is_correct) correct FROM question_attempts WHERE user_id=? AND created_at >= ?");
$st->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
$row = $st->fetch();
$total = (int)($row['total'] ?? 0);
$correct = (int)($row['correct'] ?? 0);
$wrong = max(0, $total - $correct);
$accuracy = $total>0 ? round(($correct/$total)*100) : 0;

$topicQ = db()->prepare("SELECT q.topic, COUNT(*) total, SUM(qa.is_correct) correct\n                       FROM question_attempts qa\n                       JOIN questions q ON q.id=qa.question_id\n                       WHERE qa.user_id=? AND qa.created_at >= ?\n                       GROUP BY q.topic\n                       ORDER BY total DESC");
$topicQ->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
$topicRows = $topicQ->fetchAll();

$recentQ = db()->prepare("SELECT q.topic, q.skill, qa.is_correct, qa.created_at\n                        FROM question_attempts qa\n                        JOIN questions q ON q.id=qa.question_id\n                        WHERE qa.user_id=? AND qa.created_at >= ?\n                        ORDER BY qa.created_at DESC\n                        LIMIT 12");
$recentQ->execute([$u['id'], $createdAt->format('Y-m-d H:i:s')]);
$recentRows = $recentQ->fetchAll();

function pct2($n){ return (string)round($n,0).'%'; }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Student · Progress</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Progress"; $navActive="progress"; include __DIR__ . '/../includes/partials/student_nav.php'; ?>

  <!-- Moved from Profile: Progress analytics + Achievements -->
  <div class="grid" style="margin-top:18px; grid-template-columns:1fr; gap:14px">
    <div class="card">
      <div class="row" style="justify-content:space-between; align-items:baseline">
        <div>
          <div class="h1" style="font-size:18px">Progress analytics</div>
          <div class="muted" style="margin-top:6px">From account creation (<?=htmlspecialchars((new DateTime($createdAtStr))->format('Y-m-d'))?>) · grouped by <?=htmlspecialchars($attemptPeriodLabel)?></div>
        </div>
        <div class="pill">Practice accuracy: <?= (int)$practiceAccuracy ?>%</div>
      </div>
      <div class="hr"></div>

      <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px">
        <div class="card" style="margin:0; box-shadow:none">
          <div class="muted">Attempts (Total vs Correct)</div>
          <div style="height:10px"></div>
          <canvas id="chartAttempts" height="160" style="width:100%; height:160px; display:block;"></canvas>
          <div class="muted" style="margin-top:10px"><?= (int)$practiceCorrect ?> correct · <?= (int)$practiceWrong ?> wrong · <?= (int)$practiceTotal ?> total</div>
        </div>
        <div class="card" style="margin:0; box-shadow:none">
          <div class="muted">Accuracy by skill</div>
          <div style="height:10px"></div>
          <canvas id="chartSkill" height="160" style="width:100%; height:160px; display:block;"></canvas>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row" style="justify-content:space-between; align-items:baseline">
        <div>
          <div class="h1" style="font-size:18px">Achievements</div>
        </div>
        <div class="pill">Points: <?= (int)($u['points'] ?? 0) ?></div>
      </div>
      <div class="hr"></div>

      <div class="grid" style="grid-template-columns:1fr 1fr; gap:14px">
        <div class="card" style="margin:0; box-shadow:none">
          <div style="font-weight:900">Badges</div>
          <div class="hr"></div>

          <?php if(!$earnedBadges): ?>
            <div class="toast">No badges yet. Keep practicing!</div>
          <?php else: ?>
            <div style="display:grid; gap:10px">
              <?php foreach($earnedBadges as $b): ?>
                <div class="card" style="margin:0; box-shadow:none">
                  <div style="font-weight:900">🏅 <?=htmlspecialchars($b['title'])?></div>
                  <div class="muted" style="margin-top:6px"><?=htmlspecialchars($b['description'])?></div>
                  <div class="muted" style="margin-top:6px; font-size:12px">Earned: <?=htmlspecialchars($b['earned_at'])?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin:0; box-shadow:none">
          <div style="font-weight:900">Notifications</div>
          <div class="hr"></div>

          <?php if(!$notifications): ?>
            <div class="toast">No notifications yet.</div>
          <?php else: ?>
            <div style="display:grid; gap:10px">
              <?php foreach($notifications as $n): ?>
                <div class="card" style="margin:0; box-shadow:none">
                  <div class="row" style="justify-content:space-between; align-items:baseline">
                    <div style="font-weight:900"><?=htmlspecialchars($n['title'])?></div>
                    <div class="pill"><?= ((int)$n['is_read']===1) ? 'Read' : 'New' ?></div>
                  </div>
                  <div class="muted" style="margin-top:6px"><?=htmlspecialchars($n['message'])?></div>
                  <div class="muted" style="margin-top:6px; font-size:12px"><?=htmlspecialchars($n['created_at'])?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Existing Progress page content -->
  <div class="grid" style="margin-top:18px">
    <div class="card">
      <div class="h1">Overall</div>
      <div class="hr"></div>
      <div style="line-height:1.9">
        Total attempts: <b><?= $total ?></b><br>
        Correct: <b><?= $correct ?></b><br>
        Wrong: <b><?= $wrong ?></b><br>
        Accuracy: <b><?= $accuracy ?>%</b>
      </div>
    </div>

    <div class="card">
      <div class="h1">Top topics</div>
      <div class="hr"></div>
      <?php if(!$topicRows): ?>
        <div class="toast">No data yet. Start practicing!</div>
      <?php else: ?>
        <table class="tbl">
          <tr><th>Topic</th><th>Total</th><th>Accuracy</th></tr>
          <?php foreach($topicRows as $t):
            $tTotal = (int)$t['total'];
            $tCorrect = (int)$t['correct'];
            $tAcc = $tTotal>0 ? round(($tCorrect/$tTotal)*100) : 0;
          ?>
            <tr>
              <td><?=htmlspecialchars($t['topic'])?></td>
              <td><?= $tTotal ?></td>
              <td><?= $tAcc ?>%</td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="h1">Recent</div>
      <div class="hr"></div>
      <?php if(!$recentRows): ?>
        <div class="toast">No recent activity.</div>
      <?php else: ?>
        <table class="tbl">
          <tr><th>Topic</th><th>Skill</th><th>Result</th><th>Time</th></tr>
          <?php foreach($recentRows as $r): ?>
            <tr>
              <td><?=htmlspecialchars($r['topic'])?></td>
              <td><?=htmlspecialchars($r['skill'])?></td>
              <td><?= ((int)$r['is_correct']===1) ? '✅' : '❌' ?></td>
              <td><?=htmlspecialchars($r['created_at'])?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
  // Data from PHP
  // NOTE: these arrays are built above in PHP based on the user's account creation date.
  const attemptLabels = <?=json_encode($attemptLabels, JSON_UNESCAPED_UNICODE)?>;
  const attemptTotals = <?=json_encode($attemptTotals, JSON_UNESCAPED_UNICODE)?>;
  const attemptCorrect = <?=json_encode($attemptCorrect, JSON_UNESCAPED_UNICODE)?>;

  const skillLabels = <?=json_encode($skillLabels, JSON_UNESCAPED_UNICODE)?>;
  const skillAcc = <?=json_encode($skillAcc, JSON_UNESCAPED_UNICODE)?>;

  // --- Helpers ---
  function cssVar(name, fallback){
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }
  function clamp(v, a, b){ return Math.max(a, Math.min(b, v)); }
  function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }

  function fitCanvas(canvas){
    const dpr = window.devicePixelRatio || 1;
    const cssW = canvas.clientWidth || canvas.width;
    const cssH = canvas.clientHeight || canvas.height;
    canvas.width = Math.max(1, Math.round(cssW * dpr));
    canvas.height = Math.max(1, Math.round(cssH * dpr));
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return {ctx, w: cssW, h: cssH};
  }

  function roundRect(ctx, x, y, w, h, r){
    const rr = Math.min(r, w/2, h/2);
    ctx.beginPath();
    ctx.moveTo(x+rr, y);
    ctx.arcTo(x+w, y, x+w, y+h, rr);
    ctx.arcTo(x+w, y+h, x, y+h, rr);
    ctx.arcTo(x, y+h, x, y, rr);
    ctx.arcTo(x, y, x+w, y, rr);
    ctx.closePath();
  }

  // Tooltip
  const tip = document.createElement('div');
  tip.className = 'chart-tip';
  tip.style.display = 'none';
  document.body.appendChild(tip);

  const style = document.createElement('style');
  style.textContent = `
    .chart-tip{position:fixed; z-index:9999; padding:10px 12px; border-radius:12px; 
      background: ${cssVar('--card','#0f172a')}; color: ${cssVar('--text','#e5e7eb')};
      border: 1px solid ${cssVar('--border','rgba(255,255,255,0.12)')};
      box-shadow: 0 12px 30px rgba(0,0,0,.25);
      font-size: 12px; line-height:1.35; min-width: 170px; pointer-events:none;
    }
    .chart-tip b{font-size:13px}
    .chart-tip .row{display:flex; justify-content:space-between; gap:10px; margin-top:6px}
    .chart-tip .muted{opacity:.75}
  `;
  document.head.appendChild(style);

  function showTip(html, x, y){
    tip.innerHTML = html;
    tip.style.display = 'block';
    const pad = 14;
    const w = tip.offsetWidth;
    const h = tip.offsetHeight;
    let tx = x + pad;
    let ty = y + pad;
    if (tx + w > window.innerWidth - 8) tx = x - w - pad;
    if (ty + h > window.innerHeight - 8) ty = y - h - pad;
    tip.style.left = tx + 'px';
    tip.style.top = ty + 'px';
  }
  function hideTip(){ tip.style.display = 'none'; }

  // --- Chart primitives ---
  function drawFrame(ctx, w, h, pad, yMax){
    ctx.clearRect(0,0,w,h);

    // Soft background
    ctx.save();
    const bg = ctx.createLinearGradient(0,0,0,h);
    bg.addColorStop(0, 'rgba(255,255,255,0.04)');
    bg.addColorStop(1, 'rgba(0,0,0,0.04)');
    ctx.fillStyle = bg;
    roundRect(ctx, 0.5, 0.5, w-1, h-1, 14);
    ctx.fill();
    ctx.restore();

    const grid = cssVar('--border','rgba(255,255,255,0.12)');
    const text = cssVar('--text','#e5e7eb');

    // Horizontal grid lines + y labels
    const ticks = 5;
    ctx.strokeStyle = grid;
    ctx.lineWidth = 1;
    ctx.setLineDash([4,4]);
    ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.fillStyle = text;
    for(let i=0;i<=ticks;i++){
      const t = i / ticks;
      const y = pad.t + (1 - t) * (h - pad.t - pad.b);
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(w - pad.r, y);
      ctx.stroke();
      const v = Math.round(t * yMax);
      ctx.setLineDash([]);
      ctx.fillStyle = 'rgba(255,255,255,0.75)';
      ctx.fillText(String(v), 10, y + 4);
      ctx.setLineDash([4,4]);
    }
    ctx.setLineDash([]);

    // Axes
    ctx.strokeStyle = grid;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(pad.l, pad.t);
    ctx.lineTo(pad.l, h - pad.b);
    ctx.lineTo(w - pad.r, h - pad.b);
    ctx.stroke();
  }

  function drawGroupedBars(canvas, labels, totals, corrects, progress, hoverIdx){
    const {ctx, w, h} = fitCanvas(canvas);
    const pad = {l: 44, r: 18, t: 18, b: 40};

    const maxV = Math.max(1, ...totals, ...corrects);
    const yMax = Math.ceil(maxV / 5) * 5;

    drawFrame(ctx, w, h, pad, yMax);

    const plotW = w - pad.l - pad.r;
    const plotH = h - pad.t - pad.b;
    const n = Math.max(1, labels.length);
    const groupW = plotW / n;

    const cTotalA = cssVar('--primary-grad-a', '#8DA9C4');
    const cTotalB = cssVar('--primary-grad-b', '#134074');
    const cCorrectA = cssVar('--powder', '#8DA9C4');
    const cCorrectB = cssVar('--mint', '#EEF4ED');

    // Hover highlight
    if(hoverIdx != null && hoverIdx >= 0 && hoverIdx < labels.length){
      const gx = pad.l + hoverIdx * groupW;
      ctx.save();
      ctx.fillStyle = 'rgba(255,255,255,0.06)';
      roundRect(ctx, gx + 4, pad.t + 4, groupW - 8, plotH - 8, 12);
      ctx.fill();
      ctx.restore();
    }

    // Bars
    const barGap = Math.max(6, groupW * 0.12);
    const barW = (groupW - barGap * 3) / 2;

    ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    for(let i=0;i<labels.length;i++){
      const gx = pad.l + i * groupW;
      const total = totals[i] || 0;
      const corr  = corrects[i] || 0;

      const totalH = (total / yMax) * plotH * progress;
      const corrH  = (corr  / yMax) * plotH * progress;

      const x1 = gx + barGap;
      const x2 = gx + barGap*2 + barW;
      const y0 = pad.t + plotH;

      // Total gradient
      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,0.20)';
      ctx.shadowBlur = 10;
      ctx.shadowOffsetY = 4;
      let g1 = ctx.createLinearGradient(0, y0-totalH, 0, y0);
      g1.addColorStop(0, cTotalA);
      g1.addColorStop(1, cTotalB);
      ctx.fillStyle = g1;
      roundRect(ctx, x1, y0-totalH, barW, totalH, 10);
      ctx.fill();

      let g2 = ctx.createLinearGradient(0, y0-corrH, 0, y0);
      g2.addColorStop(0, cCorrectB);
      g2.addColorStop(1, cCorrectA);
      ctx.fillStyle = g2;
      roundRect(ctx, x2, y0-corrH, barW, corrH, 10);
      ctx.fill();
      ctx.restore();

      // Label
      const lbl = String(labels[i] || '');
      const short = lbl.length > 10 ? (lbl.slice(0,9) + '…') : lbl;
      ctx.fillStyle = 'rgba(255,255,255,0.80)';
      ctx.fillText(short, gx + groupW/2, y0 + 10);

      // Value on top (only when hovered)
      if(i === hoverIdx){
        ctx.save();
        ctx.textBaseline = 'bottom';
        ctx.fillStyle = 'rgba(255,255,255,0.90)';
        ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
        ctx.fillText(String(total), x1 + barW/2, y0 - totalH - 6);
        ctx.fillText(String(corr), x2 + barW/2, y0 - corrH - 6);
        ctx.restore();
      }
    }

    // Legend
    ctx.save();
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';

    const ly = 14;
    const lx = pad.l;
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    roundRect(ctx, lx, ly, 10, 10, 3);
    ctx.fill();

    const gg = ctx.createLinearGradient(0,0,10,10);
    gg.addColorStop(0, cTotalA); gg.addColorStop(1, cTotalB);
    ctx.fillStyle = gg;
    roundRect(ctx, lx, ly, 10, 10, 3);
    ctx.fill();

    ctx.fillStyle = 'rgba(255,255,255,0.78)';
    ctx.fillText('Total', lx + 16, ly + 5);

    const lx2 = lx + 64;
    const gg2 = ctx.createLinearGradient(0,0,10,10);
    gg2.addColorStop(0, cCorrectB); gg2.addColorStop(1, cCorrectA);
    ctx.fillStyle = gg2;
    roundRect(ctx, lx2, ly, 10, 10, 3);
    ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.78)';
    ctx.fillText('Correct', lx2 + 16, ly + 5);
    ctx.restore();
  }

  function drawBars(canvas, labels, values, yMax, progress, hoverIdx){
    const {ctx, w, h} = fitCanvas(canvas);
    const pad = {l: 44, r: 18, t: 18, b: 40};

    const maxV = Math.max(1, ...values, yMax || 0);
    const maxY = yMax || Math.ceil(maxV / 10) * 10;

    drawFrame(ctx, w, h, pad, maxY);

    const plotW = w - pad.l - pad.r;
    const plotH = h - pad.t - pad.b;
    const n = Math.max(1, labels.length);
    const groupW = plotW / n;

    const cA = cssVar('--primary-grad-a', '#8DA9C4');
    const cB = cssVar('--primary-grad-b', '#134074');

    if(hoverIdx != null && hoverIdx >= 0 && hoverIdx < labels.length){
      const gx = pad.l + hoverIdx * groupW;
      ctx.save();
      ctx.fillStyle = 'rgba(255,255,255,0.06)';
      roundRect(ctx, gx + 4, pad.t + 4, groupW - 8, plotH - 8, 12);
      ctx.fill();
      ctx.restore();
    }

    const barW = Math.min(42, Math.max(18, groupW * 0.55));
    const xPad = (groupW - barW) / 2;

    ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';

    for(let i=0;i<labels.length;i++){
      const v = values[i] || 0;
      const bh = (v / maxY) * plotH * progress;
      const gx = pad.l + i * groupW;
      const x = gx + xPad;
      const y0 = pad.t + plotH;

      ctx.save();
      ctx.shadowColor = 'rgba(0,0,0,0.20)';
      ctx.shadowBlur = 10;
      ctx.shadowOffsetY = 4;
      const g = ctx.createLinearGradient(0, y0-bh, 0, y0);
      g.addColorStop(0, cA);
      g.addColorStop(1, cB);
      ctx.fillStyle = g;
      roundRect(ctx, x, y0-bh, barW, bh, 12);
      ctx.fill();
      ctx.restore();

      const lbl = String(labels[i] || '');
      ctx.fillStyle = 'rgba(255,255,255,0.80)';
      ctx.fillText(lbl, gx + groupW/2, y0 + 10);

      if(i === hoverIdx){
        ctx.save();
        ctx.textBaseline = 'bottom';
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        ctx.fillText(String(Math.round(v)), gx + groupW/2, y0 - bh - 6);
        ctx.restore();
      }
    }
  }

  // --- Wiring ---
  const attemptCanvas = document.getElementById('chartAttempts');
  const skillCanvas = document.getElementById('chartSkill');

  const STATE = {hoverAttempt: -1, hoverSkill: -1, animated: false};

  function renderAll(progress=1){
    drawGroupedBars(attemptCanvas, attemptLabels, attemptTotals, attemptCorrect, progress, STATE.hoverAttempt);
    drawBars(skillCanvas, skillLabels, skillAcc, 100, progress, STATE.hoverSkill);
  }

  function animateIn(){
    if(STATE.animated) { renderAll(1); return; }
    STATE.animated = true;
    const dur = 700;
    const start = performance.now();
    function frame(now){
      const t = clamp((now - start)/dur, 0, 1);
      renderAll(easeOutCubic(t));
      if(t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function idxFromEvent(canvas, labels, padL=44, padR=18){
    const rect = canvas.getBoundingClientRect();
    const x = (event.clientX - rect.left);
    const w = rect.width;
    const plotW = w - padL - padR;
    if(x < padL || x > w - padR) return -1;
    const g = plotW / Math.max(1, labels.length);
    return clamp(Math.floor((x - padL)/g), 0, labels.length-1);
  }

  function bindHover(){
    if(attemptCanvas){
      attemptCanvas.addEventListener('mouseleave', () => { STATE.hoverAttempt = -1; hideTip(); renderAll(1); });
      attemptCanvas.addEventListener('mousemove', (e) => {
        const rect = attemptCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const w = rect.width;
        const padL = 44, padR = 18;
        const plotW = w - padL - padR;
        const idx = (x < padL || x > w - padR) ? -1 : Math.floor((x - padL) / (plotW / Math.max(1, attemptLabels.length)));
        const i = (idx >= 0 && idx < attemptLabels.length) ? idx : -1;
        if(i !== STATE.hoverAttempt){ STATE.hoverAttempt = i; renderAll(1); }
        if(i >= 0){
          const total = attemptTotals[i] || 0;
          const corr = attemptCorrect[i] || 0;
          const acc = total ? Math.round((corr/total)*100) : 0;
          showTip(
            `<b>${attemptLabels[i]}</b>`+
            `<div class="row"><span class="muted">Total</span><span>${total}</span></div>`+
            `<div class="row"><span class="muted">Correct</span><span>${corr}</span></div>`+
            `<div class="row"><span class="muted">Accuracy</span><span>${acc}%</span></div>`,
            e.clientX, e.clientY
          );
        } else hideTip();
      });
    }

    if(skillCanvas){
      skillCanvas.addEventListener('mouseleave', () => { STATE.hoverSkill = -1; hideTip(); renderAll(1); });
      skillCanvas.addEventListener('mousemove', (e) => {
        const rect = skillCanvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const w = rect.width;
        const padL = 44, padR = 18;
        const plotW = w - padL - padR;
        const idx = (x < padL || x > w - padR) ? -1 : Math.floor((x - padL) / (plotW / Math.max(1, skillLabels.length)));
        const i = (idx >= 0 && idx < skillLabels.length) ? idx : -1;
        if(i !== STATE.hoverSkill){ STATE.hoverSkill = i; renderAll(1); }
        if(i >= 0){
          const v = skillAcc[i] || 0;
          showTip(
            `<b>${skillLabels[i]}</b>`+
            `<div class="row"><span class="muted">Accuracy</span><span>${Math.round(v)}%</span></div>`,
            e.clientX, e.clientY
          );
        } else hideTip();
      });
    }
  }

  function onResize(){
    renderAll(1);
  }

  window.addEventListener('resize', () => { clearTimeout(window.__chartResizeT); window.__chartResizeT = setTimeout(onResize, 140); });

  // Init
  animateIn();
  bindHover();
</script>
</body>
</html>
