<?php
require_once __DIR__ . '/db.php';

/**
 * Creates password_resets table if it doesn't exist.
 * Safe to call on every request.
 */
function ensure_password_reset_schema(): void {
  try {
    db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token_hash CHAR(64) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX(token_hash),
      INDEX(user_id),
      INDEX(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
  } catch (Throwable $e) {
    // If schema creation fails, later operations will fail; keep silent here.
  }
}

/**
 * Creates a reset token for the given email.
 * Returns: ['token' => string, 'user' => array] or null if user not found.
 */
function create_password_reset_for_email(string $email): ?array {
  ensure_password_reset_schema();

  $email = trim(function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email));
  if ($email === '') return null;

  $st = db()->prepare("SELECT id, email, full_name FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $user = $st->fetch();
  if (!$user) return null;

  $token = bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $token);
  $exp = (new DateTime('+60 minutes'))->format('Y-m-d H:i:s');

  // Invalidate older unused tokens for this user (optional)
  db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL")->execute([(int)$user['id']]);

  $ins = db()->prepare("INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?,?,?)");
  $ins->execute([(int)$user['id'], $tokenHash, $exp]);

  return ['token' => $token, 'user' => $user];
}

/**
 * Verifies a token and returns reset row + user.
 */
function verify_password_reset_token(string $token): ?array {
  ensure_password_reset_schema();

  $token = trim($token);
  if ($token === '') return null;

  $tokenHash = hash('sha256', $token);
  $st = db()->prepare("SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used_at,
                              u.email, u.full_name
                       FROM password_resets pr
                       JOIN users u ON u.id = pr.user_id
                       WHERE pr.token_hash=? AND pr.used_at IS NULL AND pr.expires_at > NOW()
                       ORDER BY pr.id DESC LIMIT 1");
  $st->execute([$tokenHash]);
  $row = $st->fetch();
  return $row ?: null;
}

function consume_password_reset(int $resetId): void {
  ensure_password_reset_schema();
  db()->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")->execute([$resetId]);
}

function update_user_password(int $userId, string $newPassword): void {
  $hash = password_hash($newPassword, PASSWORD_DEFAULT);
  db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);
}
?>
