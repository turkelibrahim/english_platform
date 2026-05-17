<?php
require_once __DIR__ . '/../includes/db.php';

// 3 gün inaktif olan öğrenciler
$st = db()->query("
  SELECT id, email, full_name
  FROM users
  WHERE role='student'
    AND (last_active_at IS NULL OR last_active_at < (NOW() - INTERVAL 3 DAY))
");
$users = $st->fetchAll();

foreach($users as $u){
  // aynı kişiye çok sık gitmesin diye son 3 günde log var mı?
  $chk = db()->prepare("SELECT COUNT(*) FROM reminder_log WHERE user_id=? AND sent_at > (NOW() - INTERVAL 3 DAY)");
  $chk->execute([$u['id']]);
  if ((int)$chk->fetchColumn() > 0) continue;

  $to = $u['email'];
  $subject = "A quick review could help";
  $msg = "Hi {$u['full_name']},\n\nA 5-minute review today can help you get back on track quickly.\n\nSee you soon!";
  @mail($to, $subject, $msg);

  $ins = db()->prepare("INSERT INTO reminder_log(user_id, reason) VALUES(?,?)");
  $ins->execute([$u['id'], 'inactive_3_days']);
}

echo "done\n";
