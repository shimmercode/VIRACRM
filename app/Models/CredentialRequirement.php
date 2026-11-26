<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * §6 — which credential types a project of a given type must have before it can
 * be finalised.
 *
 * The table has existed since the credential schema was written, with a
 * `blocks_finalize` column and a comment naming §6. Until now nothing seeded it
 * and nothing read it, so the gate the client document calls for could never
 * fire: two SEO projects were sitting at status=active with no accesses at all.
 * A column that models a rule is not the rule.
 *
 * The list is DATA, not code, on purpose. §6 says the system must let the client
 * define which accesses are mandatory — so which types are required is a
 * decision the client changes in one table, not a release.
 */
class CredentialRequirement extends Model
{
    protected $fillable = ['project_type', 'credential_type_id', 'is_required', 'blocks_finalize'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'blocks_finalize' => 'boolean'];
    }

    public function credentialType(): BelongsTo
    {
        return $this->belongsTo(CredentialType::class);
    }

    /**
     * The types that block finalisation for one project type.
     *
     * @return \Illuminate\Support\Collection<int, CredentialType>
     */
    public static function blockingFor(string $projectType)
    {
        return CredentialType::query()
            ->whereHas('requirements', function ($q) use ($projectType) {
                $q->where('project_type', $projectType)
                    ->where('is_required', true)
                    ->where('blocks_finalize', true);
            })
            ->orderBy('sort_order')
            ->get();
    }
}
