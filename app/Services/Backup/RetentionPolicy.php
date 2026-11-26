<?php

namespace App\Services\Backup;

/**
 * Which backups to keep, as a pure function of (file, mtime) pairs.
 *
 * WHY THIS IS A SEPARATE CLASS
 * ----------------------------
 * Retention deletes files. Logic that deletes files belongs somewhere it can be
 * tested without booting a console application, staging twenty containers and
 * reading the output — which is how the first implementation shipped a bug in
 * production-facing code: every class protected *all* of its newest N periods
 * rather than one file per period, so the classes overlapped completely and
 * pruning removed nothing, ever. The arithmetic is easy to get wrong and easy to
 * get wrong silently, so it lives here and BackupRestoreTest pins it down.
 *
 * THE POLICY: grandfather-father-son
 * ----------------------------------
 * Each age class keeps at most ONE container per period — the newest in that
 * period — and the classes cover progressively coarser periods:
 *
 *   daily    keep the newest container from each of the last N days
 *   weekly   keep the newest container from each of the last N ISO weeks
 *   monthly  keep the newest container from each of the last N months
 *
 * A container survives if ANY class keeps it. With 7/4/6 the steady state is
 * roughly 7 daily + 4 weekly + 6 monthly, minus the overlaps where a weekly or
 * monthly pick is the same file a daily pick already chose.
 *
 * A limit of 0 disables that class — it then keeps nothing rather than keeping
 * everything, which is the safe reading of "keep zero".
 */
final class RetentionPolicy
{
    /**
     * @param  array<int,array{path:string,mtime:int}>  $files
     * @param  array{daily?:int,weekly?:int,monthly?:int}  $limits
     * @return array{keep:string[],remove:string[]} both sorted by path
     */
    public static function select(array $files, array $limits): array
    {
        $keep = [];

        foreach (['daily' => 'Y-m-d', 'weekly' => 'o-\WW', 'monthly' => 'Y-m'] as $class => $format) {
            $limit = (int) ($limits[$class] ?? 0);
            if ($limit <= 0) {
                continue;
            }

            /* Group by period, remembering the newest file in each. */
            $newest = [];
            foreach ($files as $file) {
                $period = date($format, (int) $file['mtime']);
                if (! isset($newest[$period]) || $file['mtime'] > $newest[$period]['mtime']) {
                    $newest[$period] = ['path' => (string) $file['path'], 'mtime' => (int) $file['mtime']];
                }
            }

            /* Newest period first, then take $limit of them. */
            uasort($newest, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

            $taken = 0;
            foreach ($newest as $entry) {
                if ($taken++ >= $limit) {
                    break;
                }
                $keep[$entry['path']] = true;
            }
        }

        $keepList = [];
        $remove = [];

        foreach ($files as $file) {
            $path = (string) $file['path'];
            if (isset($keep[$path])) {
                $keepList[] = $path;
            } else {
                $remove[] = $path;
            }
        }

        sort($keepList);
        sort($remove);

        return ['keep' => $keepList, 'remove' => $remove];
    }
}
