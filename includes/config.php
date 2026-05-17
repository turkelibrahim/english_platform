<?php
// DB config
define('DB_HOST', 'localhost');
define('DB_NAME', 'english_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security
define('SESSION_NAME', 'EPSESS');
define('BASE_URL', '/english_platform'); // klasör adın buysa böyle kalsın

// Optional: Python binary for AI scripts (topic recommender)
define('PYTHON_BIN', 'python');

// Optional: if you want to allow creating additional admins from /public/admin.php
// Set a non-empty code and share it with trusted people.
define('ADMIN_INVITE_CODE', ''); // e.g., 'my_secret_code'

?>