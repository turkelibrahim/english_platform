<?php
require_once __DIR__ . '/../includes/rbac.php';
require_once __DIR__ . '/../includes/csrf.php';

$u = require_role('admin');
$ok = $err = '';

// List filters (GET)
$q = trim($_GET['q'] ?? '');
$fLevel = trim($_GET['level'] ?? '');
$fPlacement = (string)($_GET['placement'] ?? '');

if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $action = $_POST['action'] ?? '';

  if($action==='create_student'){
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email= trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $level= trim($_POST['level'] ?? '');
    $placementDone = (int)($_POST['placement_completed'] ?? 0);

    if($name==='' || $email==='' || $username==='' || strlen($pass)<6){
      $err = "Name, username and email are required. Password must be at least 6 characters.";
    } else {
      try{
        // Normalize + pre-check
        $email = trim(function_exists('mb_strtolower') ? mb_strtolower($email) : strtolower($email));
        $username = trim(function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username));
        if ($username === '' || !preg_match('/^[a-z0-9_\.]{3,60}$/', $username)) {
          throw new Exception('INVALID_USERNAME');
        }
        $chk = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $chk->execute([$email]);
        if($chk->fetch()){
          throw new Exception('EMAIL_EXISTS');
        }

        $chk2 = db()->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $chk2->execute([$username]);
        if($chk2->fetch()){
          throw new Exception('USERNAME_EXISTS');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = db()->prepare("
          INSERT INTO users(role,username,email,password_hash,full_name,level,placement_completed,last_active_at)
          VALUES('student',?,?,?,?,?,?,NOW())
        ");
        $st->execute([$username,$email,$hash,$name, $level?:null, $placementDone]);
        $ok = "Student account created.";
      }catch(Exception $e){
        $msg = $e->getMessage();
        if ($msg === 'EMAIL_EXISTS') {
          $err = "This email is already registered.";
        } elseif ($msg === 'USERNAME_EXISTS') {
          $err = "This username is already taken.";
        } elseif ($msg === 'INVALID_USERNAME') {
          $err = "Username must be 3-60 chars and contain only letters, numbers, underscore, or dot.";
        } elseif ($msg === 'DB_SCHEMA_MISMATCH') {
          $err = 'Database schema mismatch. Please import schema.sql into your MySQL database and try again.';
        } else {
          // If this is a PDOException, try to map common MySQL errors
          if ($e instanceof PDOException) {
            $code = $e->errorInfo[1] ?? null;
            if ((int)$code === 1062) $err = "This email is already registered.";
            elseif ((int)$code === 1054 || (int)$code === 1146) $err = 'Database schema mismatch. Please import schema.sql into your MySQL database and try again.';
            else $err = "Failed to create student due to a database error.";
          } else {
            $err = "Failed to create student.";
          }
          error_log('Admin create student error: '.$e->getMessage());
        }
      }
    }
  }

}

$where = ["role='student'"];
$params = [];

if ($q !== '') {
  $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
  $like = "%".$q."%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

if ($fLevel !== '') {
  $where[] = "level=?";
  $params[] = $fLevel;
}

if ($fPlacement === '1' || $fPlacement === '0') {
  $where[] = "placement_completed=?";
  $params[] = (int)$fPlacement;
}

$sql = "SELECT id, username, full_name, email, level, placement_completed, points, last_active_at, created_at
        FROM users
        WHERE ".implode(' AND ', $where)."
        ORDER BY full_name ASC
        LIMIT 200";
$stList = db()->prepare($sql);
$stList->execute($params);
$students = $stList->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin · Students</title>
  <link rel="stylesheet" href="<?=BASE_URL?>/public/assets/css/app.css">
</head>
<body data-theme="<?=htmlspecialchars($u['theme'] ?? 'light')?>">
<div class="container">
  <?php $navPage="Students"; $navActive="students"; include __DIR__ . '/../includes/partials/admin_nav.php'; ?>

  <div class="grid">
    <div class="card">
      <div class="h1">Find student by username</div>
<div class="hr"></div>
      <form method="get" action="<?=BASE_URL?>/admin/student_view.php">
        <input class="input" name="username" placeholder="username (e.g. elif_k)" required><br><br>
        <button class="btn primary" style="width:100%">Open profile</button>
      </form>
      <div class="hr"></div>

      <div class="h1">Create student account</div>
<div class="hr"></div>

      <?php if($err): ?><div class="toast"><?=htmlspecialchars($err)?></div><div class="hr"></div><?php endif; ?>
      <?php if($ok): ?><div class="toast"><?=htmlspecialchars($ok)?></div><div class="hr"></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="action" value="create_student">

        <input class="input" name="full_name" placeholder="Full name" required><br><br>
        <input class="input" name="username" placeholder="Username (unique)" required><br><br>
        <input class="input" type="email" name="email" placeholder="Email" required><br><br>
        <input class="input" type="password" name="password" placeholder="Password (min 6)" required><br><br>

        <div class="row">
          <select class="input" name="level">
            <option value="">(no level)</option>
            <option value="A1">A1</option><option value="A2">A2</option>
            <option value="B1">B1</option><option value="B2">B2</option>
            <option value="C1">C1</option>
          </select>
          <select class="input" name="placement_completed">
            <option value="0">Placement required</option>
            <option value="1">Mark placement as completed</option>
          </select>
        </div><br>

        <button class="btn primary" style="width:100%">Create account</button>
      </form>
    </div>

    <div class="card">
      <div class="h1">Students</div>
<div class="hr"></div>

      <form method="get" class="row" style="gap:10px; align-items:center">
        <input class="input" name="q" placeholder="Search by name / username / email" value="<?=htmlspecialchars($q)?>">
        <select class="input" name="level" style="max-width:160px">
          <option value="">All levels</option>
          <?php foreach(['A1','A2','B1','B2','C1'] as $lv): ?>
            <option value="<?=$lv?>" <?=($fLevel===$lv?'selected':'')?>><?=$lv?></option>
          <?php endforeach; ?>
        </select>
        <select class="input" name="placement" style="max-width:220px">
          <option value="">All placement statuses</option>
          <option value="1" <?=($fPlacement==='1'?'selected':'')?>>Placement completed</option>
          <option value="0" <?=($fPlacement==='0'?'selected':'')?>>Placement required</option>
        </select>
        <button class="btn">Filter</button>
        <a class="btn" href="<?=BASE_URL?>/admin/students.php">Reset</a>
      </form>

      <div class="muted" style="margin-top:10px">Showing <b><?=count($students)?></b> students</div>
      <div class="hr"></div>

      <div style="display:grid; gap:10px">
        <?php foreach($students as $s): ?>
          <a href="<?=BASE_URL?>/admin/student_view.php?id=<?=$s['id']?>"
             class="card"
             style="display:block; box-shadow:none; padding:14px 16px; text-decoration:none"
             title="@<?=htmlspecialchars($s['username'] ?? '')?>">
            <div style="font-weight:900; font-size:16px; color:inherit">
              <?=htmlspecialchars($s['full_name'])?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
