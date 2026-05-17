<?php
// Legacy header (used by some older pages)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<header class="site-header">
  <div class="site-header__inner">
    <div style="font-weight:900; font-size:18px;">English Learning Platform</div>

    <nav class="site-nav">
      <?php if (isset($_SESSION["user_id"])): ?>
          <?php if ($_SESSION["role_id"] == 1): ?>
              <a href="/english_platform/student/dashboard.php">Dashboard</a>
          <?php else: ?>
              <a href="/english_platform/admin/dashboard.php">Admin Panel</a>
          <?php endif; ?>
          <a class="logout" href="/english_platform/auth/logout.php">Logout</a>
      <?php else: ?>
          <a href="/english_platform/public/index.php">Login</a>
          <a href="/english_platform/public/index.php?m=register">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<!-- Spacer for fixed header -->
<div style="height:86px"></div>
