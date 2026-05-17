<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["user_id"])) {
    header("Location: /english_platform/auth/login.php");
    exit;
}

function requireRole($roleId) {
    if ($_SESSION["role_id"] != $roleId) {
        die("Access denied!");
    }
}
