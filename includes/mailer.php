<?php
require_once __DIR__ . '/../config/mail.php';

/**
 * Build an absolute URL to a path under BASE_URL (for email links).
 */
function absolute_url(string $relativePath): string {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base = rtrim(BASE_URL, '/');
  $rel  = '/' . ltrim($relativePath, '/');
  return $scheme . '://' . $host . $base . $rel;
}

/**
 * Send an email.
 * Returns true if sent, false otherwise.
 * In MAIL_MODE='dev', this returns false but you can display the link on screen.
 */
function send_email(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
  $to = trim($to);
  if ($to === '') return false;

  if (defined('MAIL_MODE') && MAIL_MODE === 'smtp') {
    return smtp_send_email($to, $subject, $htmlBody, $textBody);
  }

  if (defined('MAIL_MODE') && MAIL_MODE === 'mail') {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
  }

  // dev mode: don't send
  return false;
}

/**
 * Minimal SMTP client (STARTTLS + AUTH LOGIN).
 * Works with most SMTP providers. If your provider needs OAuth2, use another method.
 */
function smtp_send_email(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
  $host = SMTP_HOST ?? '';
  $port = (int)(SMTP_PORT ?? 587);
  $user = SMTP_USER ?? '';
  $pass = SMTP_PASS ?? '';
  $enc  = SMTP_ENCRYPTION ?? 'tls';

  if ($host === '' || $user === '' || $pass === '' || MAIL_FROM === '') {
    return false;
  }

  $transport = '';
  if ($enc === 'ssl') $transport = 'ssl://';
  $fp = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 12);
  if (!$fp) return false;

  stream_set_timeout($fp, 12);

  $ok = smtp_expect($fp, [220]);
  if (!$ok) { fclose($fp); return false; }

  $local = 'localhost';
  smtp_write($fp, "EHLO {$local}\r\n");
  $ok = smtp_expect($fp, [250]);
  if (!$ok) { fclose($fp); return false; }

  if ($enc === 'tls') {
    smtp_write($fp, "STARTTLS\r\n");
    $ok = smtp_expect($fp, [220]);
    if (!$ok) { fclose($fp); return false; }

    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
      fclose($fp); return false;
    }

    smtp_write($fp, "EHLO {$local}\r\n");
    $ok = smtp_expect($fp, [250]);
    if (!$ok) { fclose($fp); return false; }
  }

  smtp_write($fp, "AUTH LOGIN\r\n");
  $ok = smtp_expect($fp, [334]);
  if (!$ok) { fclose($fp); return false; }

  smtp_write($fp, base64_encode($user) . "\r\n");
  $ok = smtp_expect($fp, [334]);
  if (!$ok) { fclose($fp); return false; }

  smtp_write($fp, base64_encode($pass) . "\r\n");
  $ok = smtp_expect($fp, [235]);
  if (!$ok) { fclose($fp); return false; }

  smtp_write($fp, "MAIL FROM:<" . MAIL_FROM . ">\r\n");
  $ok = smtp_expect($fp, [250]);
  if (!$ok) { fclose($fp); return false; }

  smtp_write($fp, "RCPT TO:<" . $to . ">\r\n");
  $ok = smtp_expect($fp, [250, 251]);
  if (!$ok) { fclose($fp); return false; }

  smtp_write($fp, "DATA\r\n");
  $ok = smtp_expect($fp, [354]);
  if (!$ok) { fclose($fp); return false; }

  $boundary = 'b' . bin2hex(random_bytes(8));
  if ($textBody === '') $textBody = strip_tags($htmlBody);

  $headers = [];
  $headers[] = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">";
  $headers[] = "To: <" . $to . ">";
  $headers[] = "Subject: " . $subject;
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
  $headersStr = implode("\r\n", $headers);

  $msg  = $headersStr . "\r\n\r\n";
  $msg .= "--{$boundary}\r\n";
  $msg .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
  $msg .= $textBody . "\r\n\r\n";
  $msg .= "--{$boundary}\r\n";
  $msg .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
  $msg .= $htmlBody . "\r\n\r\n";
  $msg .= "--{$boundary}--\r\n";
  $msg .= "\r\n.\r\n";

  smtp_write($fp, $msg);
  $ok = smtp_expect($fp, [250]);
  smtp_write($fp, "QUIT\r\n");
  fclose($fp);
  return $ok;
}

/** Reads a response and checks status code (handles multi-line 250-... responses). */
function smtp_expect($fp, array $codes): bool {
  $line = '';
  $code = 0;
  do {
    $line = fgets($fp, 515);
    if ($line === false) return false;
    $code = (int)substr($line, 0, 3);
    $more = (isset($line[3]) && $line[3] === '-');
  } while ($more);
  return in_array($code, $codes, true);
}

function smtp_write($fp, string $data): void {
  fwrite($fp, $data);
}
?>
