<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One encrypted secret belonging to a ProjectCredential.
 *
 * `ciphertext` is produced by EnvelopeEncrypter and is never rendered. The
 * `fingerprint` lets support answer "is this still the same password?" without
 * decrypting anything — see CredentialVault::sameAsPrevious().
 */
class CredentialValue extends Model
{
    protected $table = 'credential_values';

    /**
     * The table has no `updated_at`: a secret is replaced, never edited in
     * place. Rotating writes a new row so the old ciphertext is gone from the
     * current row rather than lingering as a stale value.
     */
    public $timestamps = false;

    protected $fillable = [
        'project_credential_id',
        'field',
        'ciphertext',
        'fingerprint',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** Ciphertext must never reach a JSON response or a log by accident. */
    protected $hidden = ['ciphertext'];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(ProjectCredential::class, 'project_credential_id');
    }
}
