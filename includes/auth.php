<?php
require_once __DIR__ . '/db.php';

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
  }
}

function current_user(): ?array {
  start_session();
  if (empty($_SESSION['uid'])) return null;

  $st = db()->prepare("SELECT * FROM users WHERE id=?");
  $st->execute([$_SESSION['uid']]);
  $u = $st->fetch();
  return $u ?: null;
}

function require_login(?string $redirectTo = null): array {
  $u = current_user();
  if (!$u) {
    $to = $redirectTo ?: (BASE_URL . "/public/index.php");
    header("Location: ".$to);
    exit;
  }
  // touch last active
  $st = db()->prepare("UPDATE users SET last_active_at=NOW() WHERE id=?");
  $st->execute([$u['id']]);
  return $u;
}

function login(string $email, string $password): ?array {
  // Normalize email to reduce case/space related duplicates
  $email = trim(function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email));
  $st = db()->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
  $st->execute([$email]);
  $u = $st->fetch();
  if ($u && password_verify($password, $u['password_hash'])) {
    start_session();
    $_SESSION['uid'] = $u['id'];
    return $u;
  }
  return null;
}

function register_user(string $name, string $username, string $email, string $password, string $avatar_url = ''): array {
  // Normalize inputs
  $name  = trim($name);
  $username = trim($username);
  $email = trim(function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email));

  // Normalize username (lowercase) + basic validation
  $username = trim(function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username));
  if ($username === '' || !preg_match('/^[a-z0-9_\.]{3,60}$/', $username)) {
    throw new Exception('INVALID_USERNAME');
  }

  // Fast pre-check for existing email/username (and a place to catch schema issues early)
  try {
    $check = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
      throw new Exception('EMAIL_EXISTS');
    }

    $check2 = db()->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $check2->execute([$username]);
    if ($check2->fetch()) {
      throw new Exception('USERNAME_EXISTS');
    }
  } catch (PDOException $e) {
    $code = $e->errorInfo[1] ?? null;
    if ((int)$code === 1054 || (int)$code === 1146) {
      throw new Exception('DB_SCHEMA_MISMATCH');
    }
    throw $e;
  }

  $hash = password_hash($password, PASSWORD_DEFAULT);

  $avatar_db = trim((string)$avatar_url);
  if ($avatar_db === '') $avatar_db = null;


  try {
    $st = db()->prepare("INSERT INTO users(role,username,email,password_hash,full_name,avatar_url,last_active_at) VALUES('student',?,?,?,?,?,NOW())");
    $st->execute([$username, $email, $hash, $name, $avatar_db]);
  } catch (PDOException $e) {
    // Differentiate common DB errors so the UI doesn't always show "email exists"
    $code = $e->errorInfo[1] ?? null; // MySQL driver error code

    // 1062: duplicate key (unique email/username)
    if ((int)$code === 1062) {
      // Try to determine whether it's email or username that clashes
      try {
        $c1 = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $c1->execute([$email]);
        if ($c1->fetch()) throw new Exception('EMAIL_EXISTS');

        $c2 = db()->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $c2->execute([$username]);
        if ($c2->fetch()) throw new Exception('USERNAME_EXISTS');
      } catch (Exception $inner) {
        throw $inner;
      }
      throw new Exception('EMAIL_EXISTS');
    }

    // 1054: unknown column, 1146: table doesn't exist => usually wrong / old schema imported
    if ((int)$code === 1054 || (int)$code === 1146) {
      throw new Exception('DB_SCHEMA_MISMATCH');
    }

    // Re-throw original error for debugging/logging
    throw $e;
  }

  $id = (int)db()->lastInsertId();

  // Cache remote avatars to avoid hotlink issues (optional)
  if (!empty($avatar_db) && is_string($avatar_db) && preg_match('#^https?://#i', $avatar_db)) {
    require_once __DIR__ . '/avatar.php';
    $cached = avatar_try_cache($id, $avatar_db);
    if ($cached && $cached !== $avatar_db) {
      $up = db()->prepare("UPDATE users SET avatar_url=? WHERE id=?");
      $up->execute([$cached, $id]);
      $avatar_db = $cached;
    }
  }

  start_session();
  $_SESSION['uid'] = $id;
  $st2 = db()->prepare("SELECT * FROM users WHERE id=?");
  $st2->execute([$id]);
  return $st2->fetch();
}



function admin_count(): int {
  $st = db()->query("SELECT COUNT(*) FROM users WHERE role='admin'");
  return (int)$st->fetchColumn();
}

function register_admin(string $name, string $username, string $email, string $password): array {
  // Reuse the same validation rules as student registration
  $name  = trim($name);
  $username = trim($username);
  $email = trim(function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email));

  $username = trim(function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username));
  if ($username === '' || !preg_match('/^[a-z0-9_\.]{3,60}$/', $username)) {
    throw new Exception('INVALID_USERNAME');
  }

  // Ensure at least 6 chars password
  if (strlen($password) < 6) {
    throw new Exception('WEAK_PASSWORD');
  }

  // Duplicate checks
  $c1 = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
  $c1->execute([$email]);
  if ($c1->fetch()) throw new Exception('EMAIL_EXISTS');

  $c2 = db()->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $c2->execute([$username]);
  if ($c2->fetch()) throw new Exception('USERNAME_EXISTS');

  $hash = password_hash($password, PASSWORD_DEFAULT);
  $avatar_db = null;

  $st = db()->prepare("INSERT INTO users(role,username,email,password_hash,full_name,last_active_at) VALUES('admin',?,?,?,?,NOW())");
  $st->execute([$username, $email, $hash, $name, $avatar_db]);

  $id = (int)db()->lastInsertId();
  start_session();
  $_SESSION['uid'] = $id;

  $st2 = db()->prepare("SELECT * FROM users WHERE id=?");
  $st2->execute([$id]);
  return $st2->fetch();
}
function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}
