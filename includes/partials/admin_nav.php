<?php
// Admin top navigation bar.
// Expects:
//   $navPage   (string) e.g., 'Dashboard'
//   $navActive (string) one of: dashboard, questions, lessons, students, content, report, profile
$navPage   = $navPage   ?? 'Dashboard';
$navActive = $navActive ?? '';
?>
<div class="nav">
  <div class="brand">
    <div class="logo"></div>
    Admin · <?=htmlspecialchars($navPage)?>
  </div>
  <div class="nav-right">
    <a class="btn <?=($navActive==='dashboard'?'primary':'')?>" href="<?=BASE_URL?>/admin/dashboard.php">Dashboard</a>
    <a class="btn <?=($navActive==='tests'?'primary':'')?>"     href="<?=BASE_URL?>/admin/tests.php">Tests</a>
    <a class="btn <?=($navActive==='lessons'?'primary':'')?>"   href="<?=BASE_URL?>/admin/lessons.php">Lessons</a>
    <a class="btn <?=($navActive==='students'?'primary':'')?>"  href="<?=BASE_URL?>/admin/students.php">Students</a>
    <a class="btn <?=($navActive==='content'?'primary':'')?>"   href="<?=BASE_URL?>/admin/content.php">Content</a>
    <a class="btn <?=($navActive==='report'?'primary':'')?>"    href="<?=BASE_URL?>/admin/report.php">Report</a>
    <a class="btn <?=($navActive==='profile'?'primary':'')?>"   href="<?=BASE_URL?>/admin/profile.php">Profile</a>
    <a class="btn danger" href="<?=BASE_URL?>/public/logout.php">Logout</a>
  </div>
</div>
