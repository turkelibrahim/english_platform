<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

start_session();
$u = current_user();
if ($u) {
  if ($u['role'] === 'admin') header("Location: ".BASE_URL."/admin/dashboard.php");
  else {
    if ((int)$u['placement_completed'] === 0) header("Location: ".BASE_URL."/student/placement.php");
    else header("Location: ".BASE_URL."/student/dashboard.php");
  }
  exit;
}

$mode = $_GET['m'] ?? 'login';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? 'login';

  if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $user = login($email, $pass);
    if (!$user) $err = 'Invalid email or password.';
    else {
      // Students can only log in from the Student portal.
      // Admin accounts must use the Admin portal.
      if (($user['role'] ?? '') !== 'student') {
        logout();
        $err = 'This account is not a student. Please use the Admin portal.';
      } else {
        if ((int)($user['placement_completed'] ?? 0) === 0) {
          header("Location: ".BASE_URL."/student/placement.php");
        } else {
          header("Location: ".BASE_URL."/student/dashboard.php");
        }
        exit;
      }
    }
  }

  if ($action === 'register') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    // Avatar is now an optional file upload (URL field removed).
    if (strlen($pass) < 6) $err = 'Password must be at least 6 characters.';
    else {
      try{
        $user = register_user($name, $username, $email, $pass, '');

        // Save uploaded avatar (optional)
        if (!empty($_FILES['avatar_file']) && isset($user['id'])) {
          require_once __DIR__ . '/../includes/avatar.php';
          $rel = avatar_save_upload((int)$user['id'], $_FILES['avatar_file'], null);
          if ($rel !== '') {
            db()->prepare("UPDATE users SET avatar_url=? WHERE id=?")->execute([$rel, (int)$user['id']]);
          }
        }
        header("Location: ".BASE_URL."/student/placement.php");
        exit;
      }catch(Exception $e){
        // Provide accurate feedback instead of showing the same message for all DB errors
        $msg = $e->getMessage();
        if ($msg === 'EMAIL_EXISTS') {
          $err = 'This email is already registered.';
        } elseif ($msg === 'USERNAME_EXISTS') {
          $err = 'This username is already taken.';
        } elseif ($msg === 'INVALID_USERNAME') {
          $err = 'Username must be 3-60 chars and contain only letters, numbers, underscore, or dot.';
        } elseif ($msg === 'DB_SCHEMA_MISMATCH') {
          $err = 'Database schema mismatch. Please import schema.sql into your MySQL database and try again.';
        } else {
          // Keep UI safe, but log details for debugging.
          error_log('Register error: '.$e->getMessage());
          $err = 'Registration failed due to a database error. Please try again.';
        }
      }
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>English Learning Platform</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="dark">
  <div class="container">
    <div class="nav">
      <div class="brand"><div class="logo"></div> English Learning Platform</div>
      <div class="nav-right">
        <button type="button" class="btn" onclick="showAbout()">About Us</button>
      </div>
    </div>

    <div class="grid single">
      <div class="card">
        <div class="h1">Welcome</div>
        <div class="muted">Login or create an account to start your adaptive learning path.</div>
        <div class="hr"></div>

        <div class="tabs">
          <a class="tab <?=($mode==='login'?'active':'')?>" href="?m=login">Login</a>
          <a class="tab <?=($mode==='register'?'active':'')?>" href="?m=register">Register</a>
        </div>

        <?php if($err): ?>
          <div class="toast"><?=htmlspecialchars($err)?></div>
          <div class="hr"></div>
        <?php endif; ?>

        <?php if($mode==='register'): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="register">
            <div class="row">
              <input class="input" name="full_name" placeholder="Full name" required>
            </div><br>
            <input class="input" name="username" placeholder="Username (unique, e.g. elif_k)" required><br><br>
            <input class="input" type="email" name="email" placeholder="Email" required><br><br>
            <input class="input" type="password" name="password" placeholder="Password (min 6)" required><br><br>
          <label class="muted">Profile photo (optional)</label>
          <input class="input" type="file" name="avatar_file" accept="image/*"><br><br>
            <button class="btn primary" style="width:100%">Create account</button>
          </form>
          <div class="muted" style="margin-top:12px">A short placement test will be shown on first login.</div>
        <?php else: ?>
          <div class="row admin-portal-row" style="justify-content:flex-end">
            <a class="btn" href="<?=BASE_URL?>/public/admin.php">Admin portal</a>
          </div>
          <form method="post">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="action" value="login">
            <input class="input" type="email" name="email" placeholder="Email" required><br><br>
            <input class="input" type="password" name="password" placeholder="Password" required><br><br>
            <button class="btn primary" style="width:100%">Login</button>
          </form>
          <div class="muted" style="margin-top:12px">
            <a href="<?=BASE_URL?>/public/forgot_password.php" style="text-decoration:underline">Forgot your password?</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- About Us Modal -->
  <div id="aboutBackdrop" class="modal-backdrop" onclick="closeAbout(event)">
    <div class="modal" onclick="event.stopPropagation()">
      <div class="modal-header">
        <div>
          <div class="modal-title">About Us</div>
          <div class="muted" style="margin-top:6px">Learn English with Adaptive Intelligence</div>
        </div>
        <button class="xbtn" type="button" onclick="hideAbout()">×</button>
      </div>
      <div class="modal-body">
        <div class="muted" style="line-height:1.65">
          This platform does not give the same content to everyone. It observes your performance,
          analyzes your mistakes, and dynamically shapes your learning path.
        </div>

        <div class="hr"></div>

        <div class="h1" style="font-size:18px;margin:0 0 10px">AI-Driven Learning Core</div>
        <div class="row">
          <span class="pill">Personalized learning paths</span>
          <span class="pill">Adaptive difficulty control</span>
          <span class="pill">Real-time feedback &amp; hints</span>
          <span class="pill">Progress analytics &amp; gamification</span>
          <span class="pill">Human-centered AI logic</span>
        </div>
      </div>
    </div>
  </div>

  <script>
    function showAbout(){
      document.getElementById('aboutBackdrop').style.display = 'flex';
    }
    function hideAbout(){
      document.getElementById('aboutBackdrop').style.display = 'none';
    }
    function closeAbout(e){
      if(e.target && e.target.id === 'aboutBackdrop') hideAbout();
    }
  </script>
</body>
</html>
