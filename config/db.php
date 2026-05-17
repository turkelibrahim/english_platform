<?php
// Single source of truth for DB connection.
// Some pages expect a global $pdo variable (legacy style), so we provide it here.

require_once __DIR__ . '/../includes/db.php';

$pdo = db();
