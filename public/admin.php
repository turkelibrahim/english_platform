<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/config.php';

start_session();
$u = current_user();
if ($u) {
  if ($u['role'] === 'admin') {
    header("Location: ".BASE_URL."/admin/dashboard.php");
  } else {
    if ((int)$u['placement_completed'] === 0) header("Location: ".BASE_URL."/student/placement.php");
    else header("Location: ".BASE_URL."/student/dashboard.php");
  }
  exit;
}

$err = '';
$adminExists = (admin_count() > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  try {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';
    $user = login($email, $pass);
    if (!$user) throw new Exception('BAD_LOGIN');
    if ($user['role'] !== 'admin') {
      logout();
      throw new Exception('NOT_ADMIN');
    }
    header("Location: ".BASE_URL."/admin/dashboard.php");
    exit;
  } catch (Exception $e) {
    $code = $e->getMessage();
    if ($code === 'BAD_LOGIN') $err = "Wrong email or password.";
    else if ($code === 'NOT_ADMIN') $err = "This account is not an admin. Please use the Student portal.";
    else $err = "Error: ".$code;
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Portal</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="dark">
<div class="container" style="max-width:900px">
  <div class="card" style="margin-top:24px">
    <div class="row" style="justify-content:space-between; align-items:center">
      <div>
        <div class="h1">Admin Portal</div>
        <div class="muted" style="margin-top:6px">Admins can log in to manage content, students, and questions.</div>
      </div>
      <a class="btn" href="<?=BASE_URL?>/public/index.php">Student portal</a>
    </div>
    <div class="hr"></div>

    <?php if($err): ?>
      <div class="toast"><?=htmlspecialchars($err)?></div>
      <div class="hr"></div>
    <?php endif; ?>

    <?php if(!$adminExists): ?>
      <div class="toast">
        No admin account exists yet. Admin registration is disabled on the website.<br>
        Create an admin directly in the database (see README_ADMIN.md), then refresh and log in.
      </div>
      <div class="hr"></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?=csrf_token()?>">
      <input class="input" type="email" name="email" placeholder="Admin email" required><br><br>
      <input class="input" type="password" name="password" placeholder="Password" required><br><br>
      <button class="btn primary" style="width:100%">Login</button>
    </form>
  </div>
</div>
</body>
</html>
