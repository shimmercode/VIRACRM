<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The catalogue of credential kinds (WordPress admin, hosting panel, domain
 * registrar, Google Search Console…) and which secret fields each one has.
 *
 * `fields_schema` is a JSON array of field definitions, e.g.:
 *
 *   [{"key":"password","label":"رمز عبور","required":true},
 *    {"key":"sftp_password","label":"رمز SFTP","required":false}]
 *
 * Storing the shape in the database rather than in code means a new hosting
 * provider can be added by a superadmin without a deployment — which is the
 * point of a credential *type* table existing at all.
 */
class CredentialType extends Model
{
    protected $table = 'credential_types';

    protected $fillable = [
        'code',
        'title',
        'fields_schema',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'fields_schema' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** @return list<string> keys of the fields that hold secrets */
    public function secretFields(): array
    {
        $schema = $this->fields_schema ?? [];

        $keys = [];
        foreach ($schema as $field) {
            if (isset($field['key'])) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /**
     * The display label for one field key, from the same schema.
     *
     * The PDF prints "رمز عبور: …" rather than "password: …". If the schema has
     * no label, the key is returned as-is instead of an empty string — a blank
     * label beside a secret tells the reader nothing about what they are holding.
     */
    public function labelFor(string $key): ?string
    {
        foreach ($this->fields_schema ?? [] as $field) {
            if (isset($field['key']) && (string) $field['key'] === $key) {
                $label = trim((string) ($field['label'] ?? ''));

                return $label !== '' ? $label : $key;
            }
        }

        return null;
    }

    /** @return list<string> keys of the fields that must be present */
    public function requiredFields(): array
    {
        $schema = $this->fields_schema ?? [];

        $keys = [];
        foreach ($schema as $field) {
            if (isset($field['key']) && ($field['required'] ?? false)) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }
}
