<?php
// Avatar helpers: normalize, safe URL checks, and optional server-side caching to avoid hotlink issues.
// Cached files are stored under /public/uploads/avatars/ and returned as relative paths like "uploads/avatars/<file>".

function avatar_public_url(string $avatarUrl): string {
  $avatarUrl = trim($avatarUrl);
  if ($avatarUrl === '') return '';
  if (preg_match('#^(https?://|data:)#i', $avatarUrl)) return $avatarUrl;
  if (strpos($avatarUrl, '//') === 0) return 'https:' . $avatarUrl; // protocol-relative
  if ($avatarUrl[0] === '/') return $avatarUrl; // absolute path
  return rtrim(BASE_URL, '/') . '/public/' . ltrim($avatarUrl, '/'); // relative to /public/
}

function avatar_url_is_safe(string $url): bool {
  $url = trim($url);
  if (!preg_match('#^https?://#i', $url)) return false;

  $p = @parse_url($url);
  if (!$p || empty($p['host'])) return false;
  $host = strtolower($p['host']);

  // Block obvious local targets
  if ($host === 'localhost' || $host === '127.0.0.1' || $host === '0.0.0.0') return false;
  if (preg_match('#\.local$#', $host)) return false;

  // Resolve host to IP and block private ranges (basic SSRF guard)
  $ip = @gethostbyname($host);
  if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
    $long = ip2long($ip);
    if ($long !== false) {
      $private =
        ($long >= ip2long('10.0.0.0')    && $long <= ip2long('10.255.255.255')) ||
        ($long >= ip2long('172.16.0.0')  && $long <= ip2long('172.31.255.255')) ||
        ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) ||
        ($long >= ip2long('127.0.0.0')   && $long <= ip2long('127.255.255.255'));
      if ($private) return false;
    }
  }
  return true;
}

// Accept nullable/empty URL safely (older rows may have NULL avatar_url)
function avatar_try_cache(int $userId, ?string $url): string {
  $url = trim((string)$url);
  if ($url === '') return '';
  // Only cache remote http(s) links; keep data: URLs or local paths as-is.
  if (!preg_match('#^https?://#i', $url)) return $url;
  if (!avatar_url_is_safe($url)) return $url;

  $cached = avatar_download_to_public($userId, $url);
  return $cached ?: $url;
}

function avatar_download_to_public(int $userId, string $url): string {
  $maxBytes = 2 * 1024 * 1024; // 2MB
  if (!function_exists('curl_init')) return ''; // curl not available

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 3,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_USERAGENT => 'EnglishPlatform/1.0 AvatarFetcher',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  $data = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $ctype= (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);

  if ($data === false || $code < 200 || $code >= 300) return '';
  if (strlen($data) > $maxBytes) return '';

  $ctype = strtolower(trim(explode(';', $ctype)[0]));
  $ext = '';
  if (in_array($ctype, ['image/jpeg','image/jpg'])) $ext = 'jpg';
  elseif ($ctype === 'image/png') $ext = 'png';
  elseif ($ctype === 'image/gif') $ext = 'gif';
  elseif ($ctype === 'image/webp') $ext = 'webp';
  else {
    // Sniff a few common formats
    $sig = substr($data, 0, 12);
    if (substr($sig,0,2) === "\xFF\xD8") $ext = 'jpg';
    elseif (substr($sig,0,4) === "\x89PNG") $ext = 'png';
    elseif (substr($sig,0,3) === "GIF") $ext = 'gif';
    elseif (substr($sig,0,4) === "RIFF" && substr($sig,8,4) === "WEBP") $ext = 'webp';
    else return '';
  }

  $root = dirname(__DIR__) . '/public/uploads/avatars';
  if (!is_dir($root)) @mkdir($root, 0775, true);
  if (!is_dir($root) || !is_writable($root)) return '';

  $name = 'u' . $userId . '_' . time() . '_' . substr(sha1($url), 0, 8) . '.' . $ext;
  $path = $root . '/' . $name;

  $ok = @file_put_contents($path, $data);
  if ($ok === false) return '';

  return 'uploads/avatars/' . $name; // relative to /public/
}

// Save an uploaded avatar image (from <input type="file" name="avatar_file">).
// Returns a relative path like "uploads/avatars/u<ID>_<ts>.<ext>" (relative to /public/)
// or '' on failure.
function avatar_save_upload(int $userId, array $file, ?string $oldRelative = null): string {
  if (empty($file) || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) return '';
  if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return '';

  // Size limit: 2MB
  $maxBytes = 2 * 1024 * 1024;
  $size = (int)($file['size'] ?? 0);
  if ($size <= 0 || $size > $maxBytes) return '';

  // Validate as image
  $info = @getimagesize($file['tmp_name']);
  if (!$info || empty($info['mime'])) return '';
  $mime = strtolower((string)$info['mime']);
  $ext = '';
  if (in_array($mime, ['image/jpeg','image/jpg'])) $ext = 'jpg';
  elseif ($mime === 'image/png') $ext = 'png';
  elseif ($mime === 'image/gif') $ext = 'gif';
  elseif ($mime === 'image/webp') $ext = 'webp';
  else return '';

  $root = dirname(__DIR__) . '/public/uploads/avatars';
  if (!is_dir($root)) @mkdir($root, 0775, true);
  if (!is_dir($root) || !is_writable($root)) return '';

  // Clean old avatar file if it was a local cached/uploaded file
  if ($oldRelative) {
    $oldRelative = trim((string)$oldRelative);
    if (strpos($oldRelative, 'uploads/avatars/') === 0) {
      $oldPath = dirname(__DIR__) . '/public/' . $oldRelative;
      if (is_file($oldPath)) @unlink($oldPath);
    }
  }

  $name = 'u' . $userId . '_' . time() . '_' . substr(sha1((string)($file['name'] ?? '')), 0, 8) . '.' . $ext;
  $dest = $root . '/' . $name;
  if (!@move_uploaded_file($file['tmp_name'], $dest)) return '';

  return 'uploads/avatars/' . $name;
}
