<?php
require_once __DIR__ . '/db.php';

function quote_of_day(): array {
  // Sequential rotation: each new session shows the next quote in order.
  try {
    $count = (int)db()->query("SELECT COUNT(*) FROM quotes")->fetchColumn();
    if ($count <= 0) {
      return ['quote_text'=>'Welcome back.','author'=>null];
    }

    // Read pointer (defaults to 0)
    $ptrSt = db()->prepare("SELECT value FROM app_state WHERE `key`='quote_pointer' LIMIT 1");
    $ptrSt->execute();
    $ptrRow = $ptrSt->fetch();
    $ptr = (int)($ptrRow['value'] ?? 0);
    $ptr = $ptr % $count;

    $qSt = db()->prepare("SELECT quote_text, author FROM quotes ORDER BY id ASC LIMIT 1 OFFSET ?");
    $qSt->bindValue(1, $ptr, PDO::PARAM_INT);
    $qSt->execute();
    $q = $qSt->fetch();

    // Advance pointer
    $next = ($ptr + 1) % $count;
    $up = db()->prepare("INSERT INTO app_state(`key`,`value`) VALUES ('quote_pointer', ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    $up->execute([(string)$next]);

    return $q ?: ['quote_text'=>'Welcome back.','author'=>null];
  } catch (Throwable $e) {
    // Fallback: random
    $q = db()->query("SELECT quote_text, author FROM quotes ORDER BY RAND() LIMIT 1")->fetch();
    return $q ?: ['quote_text'=>'Welcome back.','author'=>null];
  }
}

function level_from_scores(array $scores): string {
  // scores: 0..100
  $avg = array_sum($scores) / max(1,count($scores));
  if ($avg < 30) return 'A1';
  if ($avg < 50) return 'A2';
  if ($avg < 70) return 'B1';
  if ($avg < 85) return 'B2';
  return 'C1';
}

function create_notification(int $userId, string $type, string $title, string $message): void {
  // Keep messages short (UI cards)
  $title = trim($title);
  $title = function_exists('mb_substr') ? mb_substr($title, 0, 180) : substr($title, 0, 180);
  $message = trim($message);
  $message = function_exists('mb_substr') ? mb_substr($message, 0, 255) : substr($message, 0, 255);
  try {
    db()->prepare("INSERT INTO notifications(user_id,type,title,message) VALUES(?,?,?,?)")
      ->execute([$userId, $type, $title, $message]);
  } catch (Exception $e) {
    // If notifications table is missing (old schema), silently skip.
  }
}

function award_badges_if_needed(int $userId): array {
  $u = db()->prepare("SELECT points FROM users WHERE id=?");
  $u->execute([$userId]);
  $points = (int)$u->fetchColumn();

  $badges = db()->query("SELECT id, title, description, points_required FROM badges ORDER BY points_required ASC")->fetchAll();

  $newBadges = [];
  foreach ($badges as $b) {
    if ($points >= (int)$b['points_required']) {
      $ins = db()->prepare("INSERT IGNORE INTO user_badges(user_id,badge_id) VALUES(?,?)");
      $ins->execute([$userId, $b['id']]);

      // INSERT IGNORE returns 1 when inserted, 0 when already existed
      if ($ins->rowCount() > 0) {
        $newBadges[] = [
          'title' => (string)$b['title'],
          'description' => (string)$b['description'],
          'points_required' => (int)$b['points_required'],
        ];
        create_notification(
          $userId,
          'badge',
          'New badge: '.$b['title'],
          $b['description']
        );
      }
    }
  }

  return $newBadges;
}
