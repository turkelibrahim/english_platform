<?php
require_once __DIR__ . '/auth.php';

function require_role(string $role): array {
  $redirect = ($role === 'admin') ? (BASE_URL . "/public/admin.php") : (BASE_URL . "/public/index.php");
  $u = require_login($redirect);
  if ($u['role'] !== $role) {
    // If a user is logged in but tries to access the other portal,
    // redirect them to their own dashboard.
    if (($u['role'] ?? '') === 'admin') {
      header("Location: ".BASE_URL."/admin/dashboard.php");
    } else {
      if ((int)($u['placement_completed'] ?? 0) === 0) header("Location: ".BASE_URL."/student/placement.php");
      else header("Location: ".BASE_URL."/student/dashboard.php");
    }
    exit;
  }
  return $u;
}
