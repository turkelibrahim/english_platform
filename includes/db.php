<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
  try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
  } catch (PDOException $e) {
    // Most common first-run issue: the database doesn't exist yet (MySQL error 1049).
    $mysqlErr = $e->errorInfo[1] ?? null;
    if ((int)$mysqlErr === 1049) {
      // Try to auto-initialize from install.sql.
      try {
        initialize_database_from_install_sql();
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
      } catch (Throwable $initErr) {
        die(
          "Database '".DB_NAME."' not found and auto-setup failed. " .
          "Import install.sql in phpMyAdmin, then refresh. " .
          "Details: " . $initErr->getMessage()
        );
      }
    }

    // Any other DB errors
    die("Database connection failed: " . $e->getMessage());
  }
}

/**
 * First-run initializer: creates the DB + tables by executing install.sql.
 * This is meant for local/dev environments (XAMPP).
 */
function initialize_database_from_install_sql(): void {
  $installPath = realpath(__DIR__ . '/../install.sql');
  if (!$installPath || !file_exists($installPath)) {
    throw new RuntimeException("install.sql not found at project root.");
  }

  $sql = file_get_contents($installPath);
  if ($sql === false || trim($sql) === '') {
    throw new RuntimeException("install.sql is empty or unreadable.");
  }

  // Connect WITHOUT dbname so we can CREATE DATABASE / USE.
  $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  foreach (split_sql_statements($sql) as $stmt) {
    $s = trim($stmt);
    if ($s === '') continue;
    $pdo->exec($s);
  }
}

/**
 * Splits an SQL file into executable statements.
 * Handles '--' line comments and quoted strings.
 */
function split_sql_statements(string $sql): array {
  // Remove UTF-8 BOM if present
  $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);

  $statements = [];
  $buffer = '';
  $inString = false;
  $stringChar = '';

  $len = strlen($sql);
  for ($i = 0; $i < $len; $i++) {
    $ch = $sql[$i];
    $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

    // Handle -- comments (only when not inside strings)
    if (!$inString && $ch === '-' && $next === '-') {
      // Skip until end of line
      while ($i < $len && $sql[$i] !== "\n") $i++;
      continue;
    }

    // Track quoted strings
    if (($ch === "'" || $ch === '"') ) {
      if ($inString) {
        // If same quote char and not escaped, close string
        if ($ch === $stringChar) {
          // Count backslashes before quote to determine escaping
          $bs = 0;
          $j = $i - 1;
          while ($j >= 0 && $sql[$j] === '\\') { $bs++; $j--; }
          if ($bs % 2 === 0) {
            $inString = false;
            $stringChar = '';
          }
        }
      } else {
        $inString = true;
        $stringChar = $ch;
      }
    }

    if (!$inString && $ch === ';') {
      $statements[] = $buffer;
      $buffer = '';
      continue;
    }

    $buffer .= $ch;
  }

  if (trim($buffer) !== '') $statements[] = $buffer;
  return $statements;
}
