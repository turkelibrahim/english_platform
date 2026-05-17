<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

/**
 * Get recent attempts with topics.
 */
function ai_attempts_by_topic(int $userId, int $limit = 200): array {
  $st = db()->prepare(
    "SELECT q.topic, qa.is_correct
     FROM question_attempts qa
     JOIN questions q ON q.id = qa.question_id
     WHERE qa.user_id = ?
       AND q.topic IS NOT NULL AND q.topic <> ''
     ORDER BY qa.id DESC
     LIMIT {$limit}"
  );
  $st->execute([$userId]);
  $rows = $st->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $topic = trim((string)$r['topic']);
    if ($topic === '') continue;
    $out[] = [
      'topic' => $topic,
      'is_correct' => (int)$r['is_correct']
    ];
  }
  return $out;
}

function php_recommend_weak_topics(array $attempts, int $minAttemptsPerTopic = 3, int $maxTopics = 3): array {
  $total = [];
  $correct = [];
  foreach ($attempts as $a) {
    $topic = trim((string)($a['topic'] ?? ''));
    if ($topic === '') continue;
    $total[$topic] = ($total[$topic] ?? 0) + 1;
    $correct[$topic] = ($correct[$topic] ?? 0) + ((int)($a['is_correct'] ?? 0) === 1 ? 1 : 0);
  }

  $stats = [];
  foreach ($total as $topic => $t) {
    if ($t < $minAttemptsPerTopic) continue;
    $c = $correct[$topic] ?? 0;
    $acc = $t ? ($c / $t) : 0.0;
    $stats[] = ['topic' => $topic, 'acc' => round($acc, 4), 'total' => $t];
  }

  usort($stats, function($a, $b) {
    if ($a['acc'] == $b['acc']) {
      if ($a['total'] == $b['total']) {
        return strcmp(mb_strtolower($a['topic']), mb_strtolower($b['topic']));
      }
      return ($a['total'] > $b['total']) ? -1 : 1;
    }
    return ($a['acc'] < $b['acc']) ? -1 : 1;
  });

  return array_slice($stats, 0, $maxTopics);
}

function python_recommend_weak_topics(array $attempts, int $minAttemptsPerTopic = 3, int $maxTopics = 3): ?array {
  $script = __DIR__ . '/../ai/topic_recommender.py';
  if (!file_exists($script)) return null;

  $python = defined('PYTHON_BIN') ? PYTHON_BIN : 'python';
  $cmd = escapeshellcmd($python) . ' ' . escapeshellarg($script);

  $payload = json_encode([
    'attempts' => $attempts,
    'min_attempts_per_topic' => $minAttemptsPerTopic,
    'max_topics' => $maxTopics
  ], JSON_UNESCAPED_UNICODE);

  $descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
  ];

  $proc = @proc_open($cmd, $descriptors, $pipes);
  if (!is_resource($proc)) return null;

  fwrite($pipes[0], $payload);
  fclose($pipes[0]);

  $out = stream_get_contents($pipes[1]);
  fclose($pipes[1]);
  $err = stream_get_contents($pipes[2]);
  fclose($pipes[2]);

  $code = proc_close($proc);
  if ($code !== 0) return null;

  $json = json_decode($out, true);
  if (!is_array($json) || empty($json['ok'])) return null;

  $weak = $json['weak_topics'] ?? null;
  if (!is_array($weak)) return null;

  return $weak;
}

/**
 * Returns weak topics (topic + acc + total)
 */
function recommend_weak_topics(array $attempts, int $minAttemptsPerTopic = 3, int $maxTopics = 3): array {
  $py = python_recommend_weak_topics($attempts, $minAttemptsPerTopic, $maxTopics);
  if (is_array($py)) return $py;
  return php_recommend_weak_topics($attempts, $minAttemptsPerTopic, $maxTopics);
}

function task_progress(int $userId, int $taskId): array {
  $stT = db()->prepare("SELECT COUNT(*) FROM user_task_items WHERE task_id=?");
  $stT->execute([$taskId]);
  $total = (int)$stT->fetchColumn();

  $stA = db()->prepare(
    "SELECT COUNT(DISTINCT qa.question_id)
     FROM question_attempts qa
     WHERE qa.user_id=? AND qa.task_id=?"
  );
  $stA->execute([$userId, $taskId]);
  $done = (int)$stA->fetchColumn();

  return ['done' => $done, 'total' => $total];
}

function create_task_for_topic(int $userId, string $topic, int $questionCount = 5): ?int {
  $topic = trim($topic);
  if ($topic === '') return null;

  // Don't create duplicate open/in_progress tasks for the same topic
  $dup = db()->prepare("SELECT id FROM user_tasks WHERE user_id=? AND topic=? AND status IN ('open','in_progress') LIMIT 1");
  $dup->execute([$userId, $topic]);
  if ($dup->fetchColumn()) return null;

  // Pick questions for this topic, biased to the user's current level.
  $lvSt = db()->prepare("SELECT level FROM users WHERE id=? LIMIT 1");
  $lvSt->execute([$userId]);
  $level = (string)($lvSt->fetchColumn() ?: '');

  // Map CEFR -> difficulty band (1..5). Keep it wide to avoid empty pools.
  $minD = 1; $maxD = 5;
  switch ($level) {
    case 'A1': $minD = 1; $maxD = 2; break;
    case 'A2': $minD = 2; $maxD = 3; break;
    case 'B1': $minD = 3; $maxD = 4; break;
    case 'B2': $minD = 4; $maxD = 5; break;
    case 'C1': $minD = 5; $maxD = 5; break;
  }

  $qs = db()->prepare(
    "SELECT id
     FROM questions
     WHERE is_active=1 AND is_placement=0 AND topic=?
       AND difficulty BETWEEN ? AND ?
     ORDER BY RAND()
     LIMIT {$questionCount}"
  );
  $qs->execute([$topic, $minD, $maxD]);
  $qids = array_map(fn($r) => (int)$r['id'], $qs->fetchAll());

  // Fallback: if band is too strict, pick from any difficulty.
  if (!$qids) {
    $qs2 = db()->prepare(
      "SELECT id
       FROM questions
       WHERE is_active=1 AND is_placement=0 AND topic=?
       ORDER BY RAND()
       LIMIT {$questionCount}"
    );
    $qs2->execute([$topic]);
    $qids = array_map(fn($r) => (int)$r['id'], $qs2->fetchAll());
  }
  if (!$qids) return null;

  $title = "Focus: {$topic}";
  $ins = db()->prepare("INSERT INTO user_tasks(user_id,title,topic,status) VALUES(?,?,?,'open')");
  $ins->execute([$userId, $title, $topic]);
  $taskId = (int)db()->lastInsertId();

  $item = db()->prepare("INSERT INTO user_task_items(task_id,question_id,position) VALUES(?,?,?)");
  $pos = 1;
  foreach ($qids as $qid) {
    $item->execute([$taskId, $qid, $pos++]);
  }

  return $taskId;
}

/**
 * Refresh tasks for a student.
 * - Uses last attempts to find weak topics.
 * - Creates up to $maxNew tasks if needed.
 */
function refresh_tasks_for_user(int $userId, int $maxNew = 3): array {
  // Count active tasks
  $st = db()->prepare("SELECT COUNT(*) FROM user_tasks WHERE user_id=? AND status IN ('open','in_progress')");
  $st->execute([$userId]);
  $active = (int)$st->fetchColumn();

  $created = [];
  if ($active >= 3) return ['created' => $created, 'active' => $active];

  $attempts = ai_attempts_by_topic($userId, 250);
  $weak = recommend_weak_topics($attempts, 3, 5);

  foreach ($weak as $w) {
    if (count($created) >= $maxNew) break;
    $topic = (string)($w['topic'] ?? '');
    if ($topic === '') continue;
    $tid = create_task_for_topic($userId, $topic, 5);
    if ($tid) $created[] = ['task_id' => $tid, 'topic' => $topic];
  }

  // Fallback: if nothing created and user has no attempts yet, create a random task
  if (!$created && $active === 0) {
    $pick = db()->prepare("SELECT DISTINCT topic FROM questions WHERE is_active=1 AND is_placement=0 AND topic IS NOT NULL AND topic<>'' ORDER BY RAND() LIMIT 1");
    $pick->execute();
    $topic = (string)$pick->fetchColumn();
    if ($topic) {
      $tid = create_task_for_topic($userId, $topic, 5);
      if ($tid) $created[] = ['task_id' => $tid, 'topic' => $topic];
    }
  }

  return ['created' => $created, 'active' => $active];
}
