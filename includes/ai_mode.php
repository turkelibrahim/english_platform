<?php
require_once __DIR__ . '/db.php';

/**
 * Computes reading vs audio performance for a user based on:
 *  - Task questions (task_id not null OR context_source='task')
 *  - Lesson quiz questions (lesson_id not null OR context_source='lesson')
 *
 * Mode rule:
 *  - If the question has an .mp3 media_url => audio
 *  - Otherwise => reading
 */
function mode_stats(int $userId, int $days = 60): array {
  $st = db()->prepare("
    SELECT
      CASE
        WHEN qa.context_mode IS NOT NULL THEN qa.context_mode
        WHEN q.media_url IS NOT NULL AND q.media_url <> '' AND (LOWER(q.media_url) LIKE '%.mp3' OR LOWER(q.media_url) LIKE '%.mp3?%' OR LOWER(q.media_url) LIKE '%mp3%') THEN 'audio'
        ELSE 'reading'
      END AS mode,
      COUNT(*) AS total,
      SUM(CASE WHEN qa.is_correct=1 THEN 1 ELSE 0 END) AS correct
    FROM question_attempts qa
    JOIN questions q ON q.id = qa.question_id
    WHERE qa.user_id=?
      AND qa.created_at >= (NOW() - INTERVAL {$days} DAY)
      AND (
        qa.context_source IN ('task','lesson')
        OR qa.task_id IS NOT NULL
        OR qa.lesson_id IS NOT NULL
      )
    GROUP BY mode
  ");
  $st->execute([$userId]);
  $rows = $st->fetchAll();

  $out = [
    'reading' => ['total'=>0, 'correct'=>0, 'acc'=>null],
    'audio'   => ['total'=>0, 'correct'=>0, 'acc'=>null],
  ];

  foreach ($rows as $r) {
    $mode = (string)$r['mode'];
    $t = (int)$r['total'];
    $c = (int)$r['correct'];
    $acc = $t ? ($c / $t) : null;

    if ($mode === 'reading') {
      $out['reading'] = ['total'=>$t, 'correct'=>$c, 'acc'=>$acc];
    }
    if ($mode === 'audio') {
      $out['audio'] = ['total'=>$t, 'correct'=>$c, 'acc'=>$acc];
    }
  }

  return $out;
}

/**
 * Decide preferred mode based on stats.
 * - If both have enough data: choose the higher accuracy if difference >= $delta
 * - If only one has enough data: choose that
 * - Else: balanced
 */
function decide_preferred_mode(array $stats, int $minAttempts = 5, float $delta = 0.10): string {
  $rT = (int)($stats['reading']['total'] ?? 0);
  $aT = (int)($stats['audio']['total'] ?? 0);
  $rA = $stats['reading']['acc'];
  $aA = $stats['audio']['acc'];

  $hasR = $rT >= $minAttempts && $rA !== null;
  $hasA = $aT >= $minAttempts && $aA !== null;

  if ($hasR && $hasA) {
    if (abs($rA - $aA) >= $delta) {
      return ($rA > $aA) ? 'reading' : 'audio';
    }
    return 'balanced';
  }

  if ($hasR && !$hasA) return 'reading';
  if ($hasA && !$hasR) return 'audio';
  return 'balanced';
}

/**
 * Recompute and persist preferred_mode on users table.
 */
function update_user_preferred_mode(int $userId): string {
  $stats = mode_stats($userId, 60);
  $mode = decide_preferred_mode($stats, 5, 0.10);

  db()->prepare("UPDATE users SET preferred_mode=?, preferred_mode_updated_at=NOW() WHERE id=?")
    ->execute([$mode, $userId]);

  return $mode;
}

function get_user_preferred_mode(int $userId): string {
  $st = db()->prepare("SELECT preferred_mode FROM users WHERE id=?");
  $st->execute([$userId]);
  $mode = (string)($st->fetchColumn() ?: 'balanced');
  if (!in_array($mode, ['reading','audio','balanced'], true)) return 'balanced';
  return $mode;
}
