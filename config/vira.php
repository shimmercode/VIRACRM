<?php

/**
 * Application-specific configuration that is not part of a framework default.
 *
 * The credential key is deliberately the *only* secret read here. It must never
 * be committed, and it must live outside the document root on a real host —
 * see docs/phase-1/17-key-management.md.
 */
return [

    /*
    | Credential encryption key.
    |
    | AES-256-GCM master key, base64-encoded 32 bytes. Generate with:
    |     node docs/tools/php-cli.mjs -r 'echo App\Services\Security\EnvelopeEncrypter::generateKey();'
    | or on a host with PHP: php artisan vira:key
    |
    | Losing this key loses every stored credential (accepted risk, OD-29).
    | Leaving it unset disables credential storage rather than storing plaintext.
    */
    'credential_key' => env('VIRA_CREDENTIAL_KEY'),

    /*
    | Backup encryption key.
    |
    | Deliberately SEPARATE from `credential_key`. The two secrets have opposite
    | exposure profiles: this one travels with the backup file to off-host
    | storage, while the credential key must never leave the server. Sharing one
    | key would mean that leaking a backup — the more portable of the two —
    | handed over every stored password as well, and rotating either would
    | invalidate the other.
    |
    | Same shape as the credential key: base64-encoded 32 bytes, generated with
    |     php artisan vira:backup-key
    |
    | Leaving it unset disables backup rather than writing an unencrypted file.
    */
    'backup_key' => env('VIRA_BACKUP_KEY'),

    /*
    | Where backup containers are written.
    |
    | storage/app/backups is gitignored (storage/app/.gitignore allows only
    | private/ and public/) and is outside the document root, so a container is
    | neither committed nor downloadable. Verified, not assumed —
    | tools/checks/env-capabilities.php checks both properties.
    */
    'backup_path' => env('VIRA_BACKUP_PATH', 'backups'),

    /*
    | Retention: how many containers of each age class to keep. Pruning only
    | ever runs after a backup succeeded, so a failed backup cannot be the
    | reason the last good one was deleted.
    */
    'backup_retention' => [
        'daily' => (int) env('VIRA_BACKUP_KEEP_DAILY', 7),
        'weekly' => (int) env('VIRA_BACKUP_KEEP_WEEKLY', 4),
        'monthly' => (int) env('VIRA_BACKUP_KEEP_MONTHLY', 6),
    ],

];
