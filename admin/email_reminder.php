<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// SADECE ADMIN GIREBILSIN
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 2) {
    die("Access denied: Admin only");
}

echo "<h2>Email Reminder Debug Page</h2>";

// TUM STUDENT'LARI GOSTER (DEBUG)
echo "<h3>All students (debug):</h3>";

$all = $pdo->query(
    "SELECT id, email, last_login FROM users WHERE role_id = 1"
)->fetchAll(PDO::FETCH_ASSOC);

if (count($all) === 0) {
    echo "<p>No student users found.</p>";
} else {
    echo "<ul>";
    foreach ($all as $u) {
        echo "<li>{$u['email']} | last_login: {$u['last_login']}</li>";
    }
    echo "</ul>";
}

// ASIL EMAIL REMINDER LOGIC
echo "<hr>";
echo "<h3>Users who should receive reminder (60+ days inactive):</h3>";

$stmt = $pdo->query(
    "SELECT email, last_login FROM users
     WHERE role_id = 1
     AND last_login IS NOT NULL
     AND last_login < NOW() - INTERVAL 60 DAY"
);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($users) === 0) {
    echo "<p><strong>No inactive users found.</strong></p>";
    echo "<p>This means the system works correctly.</p>";
} else {
    foreach ($users as $u) {
        echo "<p>Reminder would be sent to: <strong>{$u['email']}</strong></p>";

        // GERCEK MAIL (localhost'ta gitmeyebilir)
        /*
        mail(
            $u['email'],
            "We miss you!",
            "You haven't logged in for a long time.",
            "From: noreply@englishplatform.com"
        );
        */
    }
}
