<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login kontrolü
if (!isset($_SESSION["user_id"])) {
    header("Location: /english_platform/auth/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// Tema güncelle
if (isset($_GET["set"])) {
    $theme = $_GET["set"] === "dark" ? "dark" : "light";

    $stmt = $pdo->prepare(
        "UPDATE users SET theme = ? WHERE id = ?"
    );
    $stmt->execute([$theme, $userId]);

    $_SESSION["theme"] = $theme;
}

// Mevcut tema
$stmt = $pdo->prepare("SELECT theme FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentTheme = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Theme Settings</title>

    <?php if ($currentTheme === "dark"): ?>
        <style>
            body { background:#0B2545; color:#EEF4ED; }
            a { color:#8DA9C4; }
        </style>
    <?php else: ?>
        <style>
            body { background:#EEF4ED; color:#0B2545; }
        </style>
    <?php endif; ?>
</head>
<body>

<h2>Theme Settings</h2>

<p>Current theme: <strong><?php echo $currentTheme; ?></strong></p>

<a href="?set=light">🌞 Light Mode</a> |
<a href="?set=dark">🌙 Dark Mode</a>

</body>
</html>
