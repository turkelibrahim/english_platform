<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Student kontrolü
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    header("Location: /english_platform/auth/login.php");
    exit;
}

$userId = $_SESSION["user_id"];

// PUAN KAYDI VAR MI?
$stmt = $pdo->prepare("SELECT * FROM points WHERE user_id = ?");
$stmt->execute([$userId]);
$pointsRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pointsRow) {
    $pdo->prepare("INSERT INTO points (user_id, points) VALUES (?, 0)")
        ->execute([$userId]);
    $points = 0;
} else {
    $points = $pointsRow["points"];
}

// ÖRNEK: PRACTICE TAMAMLANDI → +10 PUAN
if (isset($_GET["earn"])) {
    $points += 10;
    $pdo->prepare("UPDATE points SET points = ? WHERE user_id = ?")
        ->execute([$points, $userId]);

    // BADGE KONTROLÜ
    if ($points >= 50) {
        $check = $pdo->prepare(
            "SELECT * FROM badges WHERE user_id = ? AND badge_name = ?"
        );
        $check->execute([$userId, "Rising Star"]);

        if (!$check->fetch()) {
            $pdo->prepare(
                "INSERT INTO badges (user_id, badge_name) VALUES (?, ?)"
            )->execute([$userId, "Rising Star"]);
        }
    }
}

// BADGELERI CEK
$badgeStmt = $pdo->prepare("SELECT badge_name FROM badges WHERE user_id = ?");
$badgeStmt->execute([$userId]);
$badges = $badgeStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gamification</title>
    <link rel="stylesheet" href="/english_platform/assets/css/style.css">
</head>
<body>

<h2>Gamification</h2>

<p><strong>Points:</strong> <?php echo $points; ?></p>

<a href="?earn=1">Complete Activity (+10 points)</a>

<h3>Badges</h3>

<?php if (count($badges) === 0): ?>
    <p>No badges yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($badges as $b): ?>
            <li>🏅 <?php echo htmlspecialchars($b); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</body>
</html>
