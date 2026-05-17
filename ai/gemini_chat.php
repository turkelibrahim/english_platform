<?php
require_once __DIR__ . '/../config/gemini.php';

/**
 * Minimal Gemini (Google Generative Language API) client.
 * - Uses v1beta generateContent endpoint.
 * - Auth via x-goog-api-key header (recommended by docs).
 * - Returns plain text, or parsed JSON when requested.
 */

function gemini_key_is_set(): bool {
  if (!defined('GEMINI_API_KEY')) return false;
  $k = trim((string)GEMINI_API_KEY);
  if ($k === '' || $k === 'PASTE_YOUR_KEY_HERE') return false;
  return true;
}

function gemini_http_post(string $url, array $payload, array $headers = []): array {
  $raw = false;
  $code = 0;

  $baseHeaders = ["Content-Type: application/json"];
  $headers = array_merge($baseHeaders, $headers);

  // Prefer cURL when available
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, defined('GEMINI_TIMEOUT_SEC') ? (int)GEMINI_TIMEOUT_SEC : 25);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
      throw new Exception("GEMINI_CURL_ERROR: ".$err);
    }
  } else {
    // Fallback to stream context
    $ctx = stream_context_create([
      'http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", $headers) . "\r\n",
        'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        'timeout' => defined('GEMINI_TIMEOUT_SEC') ? (int)GEMINI_TIMEOUT_SEC : 25,
      ]
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (is_array($http_response_header ?? null)) {
      foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $mm)) { $code = (int)$mm[1]; break; }
      }
    }
    if ($raw === false) throw new Exception("GEMINI_HTTP_ERROR: HTTP ".$code);
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    throw new Exception("GEMINI_BAD_JSON: HTTP ".$code);
  }

  if ($code >= 400) {
    $msg = $json['error']['message'] ?? ('HTTP '.$code);
    throw new Exception("GEMINI_HTTP_ERROR: ".$msg);
  }

  return $json;
}

function askGemini(string $prompt, array $opts = []): ?string {
  if (!gemini_key_is_set()) {
    throw new Exception("GEMINI_KEY_MISSING");
  }

  $model = defined('GEMINI_MODEL') ? (string)GEMINI_MODEL : 'gemini-2.5-flash';
  $url = "https://generativelanguage.googleapis.com/v1beta/models/".$model.":generateContent";

  $system = $opts['system'] ?? null;
  $temperature = isset($opts['temperature']) ? (float)$opts['temperature'] : 0.2;
  $maxTokens = isset($opts['max_tokens']) ? (int)$opts['max_tokens'] : 512;

  $parts = [];
  if ($system) $parts[] = ["text" => (string)$system . "\n\n"];
  $parts[] = ["text" => $prompt];

  $payload = [
    "contents" => [[ "parts" => $parts ]],
    "generationConfig" => [
      "temperature" => $temperature,
      "maxOutputTokens" => $maxTokens
    ]
  ];

  // Optional response mime type (helps JSON)
  if (!empty($opts['response_mime_type'])) {
    $payload["generationConfig"]["responseMimeType"] = (string)$opts['response_mime_type'];
  }

  $headers = ["x-goog-api-key: " . (string)GEMINI_API_KEY];

  $json = gemini_http_post($url, $payload, $headers);
  return $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/**
 * Ask Gemini and parse JSON. Accepts that the model might wrap JSON with extra text.
 */
function askGeminiJson(string $prompt, array $opts = []): array {
  $opts['response_mime_type'] = $opts['response_mime_type'] ?? 'application/json';

  $text = askGemini($prompt, $opts);
  if ($text === null) throw new Exception("GEMINI_EMPTY_RESPONSE");

  // Try direct JSON decode first
  $arr = json_decode($text, true);
  if (is_array($arr)) return $arr;

  // Try to extract the first {...} block
  if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
    $arr2 = json_decode($m[0], true);
    if (is_array($arr2)) return $arr2;
  }

  throw new Exception("GEMINI_JSON_PARSE_FAILED");
}
?>