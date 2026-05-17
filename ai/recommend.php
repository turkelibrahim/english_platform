<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_GET['user_id'] ?? 0);
if(!$userId){ echo json_encode(['ok'=>false,'msg'=>'user_id missing']); exit; }

// 1) Hangi skill'de zayıf? (son 50 attempt)
$st = db()->prepare("
  SELECT q.skill,
         AVG(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS acc
  FROM question_attempts qa
  JOIN questions q ON q.id=qa.question_id
  WHERE qa.user_id=? AND qa.attempt_type IN ('placement','practice')
  GROUP BY q.skill
");
$st->execute([$userId]);
$acc = $st->fetchAll();

$skillAcc = ['vocab'=>1,'grammar'=>1,'reading'=>1,'listening'=>1,'writing'=>1];
foreach($acc as $r){ $skillAcc[$r['skill']] = (float)$r['acc']; }

// en zayıf 2 skill seç
asort($skillAcc);
$weakSkills = array_slice(array_keys($skillAcc), 0, 2);

// 2) Zorluk: kullanıcı level'a göre baz zorluk
$u = db()->prepare("SELECT level FROM users WHERE id=?");
$u->execute([$userId]);
$level = $u->fetchColumn() ?: 'A1';
$baseDiff = 5;
if ($level === 'A1') $baseDiff = 1;
else if ($level === 'A2') $baseDiff = 2;
else if ($level === 'B1') $baseDiff = 3;
else if ($level === 'B2') $baseDiff = 4;

// 3) Önerilecek soruları seç (yakın zorluk + weakSkills)
$pick = db()->prepare("
  SELECT id, skill, difficulty, prompt, choices_json, media_url
  FROM questions
  WHERE is_active=1 AND is_placement=0
    AND skill IN (?,?)
    AND difficulty BETWEEN ? AND ?
  ORDER BY RAND()
  LIMIT 6
");
$pick->execute([$weakSkills[0], $weakSkills[1], max(1,$baseDiff-1), min(5,$baseDiff+1)]);
$qs = $pick->fetchAll();

echo json_encode([
  'ok'=>true,
  'title'=>'Recommended practice for today',
  'subtitle'=>'Adjusted based on your recent performance.',
  'weak_skills'=>$weakSkills,
  'questions'=>$qs
], JSON_UNESCAPED_UNICODE);
