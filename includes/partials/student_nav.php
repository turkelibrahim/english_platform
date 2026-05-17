<?php
// Student top navigation bar.
// Expects:
//   $navPage   (string) e.g., 'Dashboard'
//   $navActive (string) one of: dashboard, lessons, practice, progress, favorites, notebook, report, profile
$navPage   = $navPage   ?? 'Dashboard';
$navActive = $navActive ?? '';
?>
<div class="nav">
  <div class="brand">
    <div class="logo"></div>
    Student · <?=htmlspecialchars($navPage)?>
  </div>
  <div class="nav-right">
    <a class="btn <?=($navActive==='dashboard'?'primary':'')?>" href="<?=BASE_URL?>/student/dashboard.php">Dashboard</a>
    <a class="btn <?=($navActive==='lessons'?'primary':'')?>"   href="<?=BASE_URL?>/student/lessons.php">Lessons</a>
    <a class="btn <?=($navActive==='practice'?'primary':'')?>"  href="<?=BASE_URL?>/student/practice.php">Practice</a>
    <a class="btn <?=($navActive==='progress'?'primary':'')?>"  href="<?=BASE_URL?>/student/progress.php">Progress</a>
    <a class="btn <?=($navActive==='favorites'?'primary':'')?>" href="<?=BASE_URL?>/student/favorites.php">Favorites</a>
    <a class="btn <?=($navActive==='notebook'?'primary':'')?>"  href="<?=BASE_URL?>/student/notebook.php">Notebook</a>
    <a class="btn <?=($navActive==='report'?'primary':'')?>"    href="<?=BASE_URL?>/student/report.php">Report</a>
    <a class="btn <?=($navActive==='profile'?'primary':'')?>"   href="<?=BASE_URL?>/student/profile.php">Profile</a>
    <a class="btn danger" href="<?=BASE_URL?>/public/logout.php">Logout</a>
  </div>
</div>
