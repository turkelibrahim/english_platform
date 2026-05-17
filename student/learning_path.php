<?php
require '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login + student kontrolü
if (!isset($_SESSION["user_id"]) || $_SESSION["role_id"] != 1) {
    header("Location: /english_platform/auth/login.php");
    exit;
}

// En son placement test sonucunu al
$sql = "SELECT * FROM placement_results 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION["user_id"]]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die("You must complete the placement test first.");
}

$level = $result["level"];

// Önce eski learning path'i temizle
$pdo->prepare("DELETE FROM learning_path WHERE user_id = ?")
    ->execute([$_SESSION["user_id"]]);

// Level’e göre içerik üret (AI mantığı – basit ama gerçek)
$topics = [];

if ($level === "Beginner") {
    $topics = [
        ["Basic Vocabulary", "easy"],
        ["Simple Grammar", "easy"],
        ["Basic Reading", "easy"]
    ];
} elseif ($level === "Intermediate") {
    $topics = [
        ["Intermediate Vocabulary", "medium"],
        ["Grammar Tenses", "medium"],
        ["Reading Comprehension", "medium"]
    ];
} else {
    $topics = [
        ["Advanced Vocabulary", "hard"],
        ["Complex Grammar", "hard"],
        ["Advanced Reading", "hard"]
    ];
}

// DB’ye yaz
$insert = $pdo->prepare(
    "INSERT INTO learning_path (user_id, topic, difficulty) 
     VALUES (?, ?, ?)"
);

foreach ($topics as $t) {
    $insert->execute([
        $_SESSION["user_id"],
        $t[0],
        $t[1]
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Learning Path</title>
    <link rel="stylesheet" href="/english_platform/assets/css/style.css">
</head>
<body>

<h2>My Personalized Learning Path</h2>

<p>Your level: <strong><?php echo $level; ?></strong></p>

<ul>
<?php foreach ($topics as $t): ?>
    <li>
        <?php echo $t[0]; ?> 
        (Difficulty: <?php echo $t[1]; ?>)
    </li>
<?php endforeach; ?>
</ul>

</body>
</html>
