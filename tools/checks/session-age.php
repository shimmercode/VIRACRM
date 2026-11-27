<?php
/**
 * Age the sessions in a scratch database, so a test can prove that an expired
 * session is actually rejected.
 *
 * WHY THIS EXISTS
 * ───────────────
 * `Flow::exec()` deliberately accepts INSERT only, so a suite cannot UPDATE
 * `sessions.last_activity` through the harness. Without that, "session expiry"
 * could only be asserted from config, which proves nothing — the lifetime is
 * read, but whether the driver acts on it is a different question.
 *
 * SAFETY
 * ──────
 * Same rules as scratch-count.php: it refuses the shared development database
 * and anything outside .arena/ or /tmp/. It is not a general-purpose SQL
 * runner; it sets one column to one computed value.
 *
 * USAGE
 *   php tools/checks/session-age.php <db> <seconds-ago>
 */

if ($argc < 3) {
    fwrite(STDERR, "usage: php session-age.php <db> <seconds-ago>\n");
    exit(2);
}

$dbArg = $argv[1];
$secondsAgo = (int) $argv[2];

$resolved = realpath($dbArg);

if ($resolved === false || ! is_file($resolved)) {
    fwrite(STDERR, "not a file: {$dbArg}\n");
    exit(2);
}

$root = realpath(dirname(__DIR__, 2));
$shared = $root . '/database/database.sqlite';

if ($resolved === $shared) {
    fwrite(STDERR, "refusing to modify the shared database\n");
    exit(3);
}

$allowed = false;
foreach ([$root . '/.arena/', '/tmp/'] as $prefix) {
    if (str_starts_with($resolved, $prefix)) {
        $allowed = true;
        break;
    }
}

if (! $allowed) {
    fwrite(STDERR, "refusing to open {$dbArg} ({$resolved}): only .arena/ or /tmp/ paths are allowed\n");
    exit(3);
}

$pdo = new PDO('sqlite:' . $resolved, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stamp = time() - $secondsAgo;
$stmt = $pdo->prepare('update sessions set last_activity = :at');
$stmt->execute([':at' => $stamp]);

printf("aged %d session(s) to last_activity=%d (%d s ago)\n",
    $stmt->rowCount(), $stamp, $secondsAgo);

exit(0);
