<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A credential *record* for a project — the metadata half.
 *
 * WHY THE SPLIT: `project_credentials` holds everything that is safe to read,
 * index and show in a list (label, URL, username, rotation date). The secrets
 * live in `credential_values`, encrypted. Keeping them in separate tables means
 * a query that lists credentials cannot accidentally select a ciphertext, and
 * a backup of the metadata is harmless.
 *
 * The model deliberately has no `password` attribute. Secrets are read through
 * CredentialVault, which decrypts on demand and records an audit entry.
 */
class ProjectCredential extends Model
{
    protected $table = 'project_credentials';

    protected $fillable = [
        'project_id',
        'credential_type_id',
        'label',
        'public_url',
        'username',
        'extra_fields',
        'is_complete',
        'notes',
        'updated_by',
        'rotated_at',
    ];

    protected $casts = [
        'is_complete' => 'boolean',
        'extra_fields' => 'array',
        'rotated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CredentialType::class, 'credential_type_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CredentialValue::class);
    }

    /** True when every field the type declares has a stored value. */
    public function isComplete(): bool
    {
        $required = $this->type?->secretFields() ?? [];

        if ($required === []) {
            return $this->values()->count() > 0;
        }

        $present = $this->values()->pluck('field')->all();

        return array_diff($required, $present) === [];
    }
}
