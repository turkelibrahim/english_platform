<?php
// Mail settings for password reset.
// MAIL_MODE: dev (show link), smtp (send via SMTP), mail (PHP mail())

define('MAIL_MODE', 'dev');
define('MAIL_FROM', 'no-reply@example.com');
define('MAIL_FROM_NAME', 'English Learning Platform');

define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls'); // tls|ssl|none
define('SMTP_USER', '');
define('SMTP_PASS', '');

define('MAIL_DEV_SHOW_LINK', true);
?>
