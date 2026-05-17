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

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $vocab     = (int) $_POST["vocabulary"];
    $grammar   = (int) $_POST["grammar"];
    $reading   = (int) $_POST["reading"];
    $listening = (int) $_POST["listening"];
    $writing   = (int) $_POST["writing"];

    $avg = ($vocab + $grammar + $reading + $listening + $writing) / 5;

    if ($avg < 40) {
        $level = "Beginner";
    } elseif ($avg < 70) {
        $level = "Intermediate";
    } else {
        $level = "Advanced";
    }

    $sql = "INSERT INTO placement_results 
            (user_id, vocabulary, grammar, reading, listening, writing, level)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_SESSION["user_id"],
        $vocab,
        $grammar,
        $reading,
        $listening,
        $writing,
        $level
    ]);

    $message = "Placement test completed. Your level: " . $level;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Placement Test</title>
    <link rel="stylesheet" href="/english_platform/assets/css/style.css">
</head>
<body>

<h2>Placement Test</h2>

<form method="POST">
    Vocabulary (0–100): <br>
    <input type="number" name="vocabulary" min="0" max="100" required><br><br>

    Grammar (0–100): <br>
    <input type="number" name="grammar" min="0" max="100" required><br><br>

    Reading (0–100): <br>
    <input type="number" name="reading" min="0" max="100" required><br><br>

    Listening (0–100): <br>
    <input type="number" name="listening" min="0" max="100" required><br><br>

    Writing (0–100): <br>
    <input type="number" name="writing" min="0" max="100" required><br><br>

    <button type="submit">Submit Test</button>
</form>

<p><?php echo $message; ?></p>

</body>
</html>
