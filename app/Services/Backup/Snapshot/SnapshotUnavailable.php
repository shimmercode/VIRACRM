<?php

namespace App\Services\Backup\Snapshot;

use RuntimeException;

/**
 * The snapshot could not be taken, and nothing was written.
 *
 * Distinct from a generic RuntimeException so the command can report *why*
 * a backup is impossible — a missing `mysqldump`, a disabled `proc_open`, an
 * unsupported driver — without having to parse a message. The operator needs
 * the cause to fix it; "backup failed" is not actionable.
 */
final class SnapshotUnavailable extends RuntimeException
{
}
