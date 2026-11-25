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

];
