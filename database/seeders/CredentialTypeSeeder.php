<?php

namespace Database\Seeders;

use App\Models\CredentialRequirement;
use App\Models\CredentialType;
use Illuminate\Database\Seeder;

/**
 * REQ-06.01 — the credential kinds the client document names in §6.
 *
 * §6 lists "WordPress, hosting, domain, Google Search Console, Google Analytics
 * and other services" as the access a project needs. These are exactly those
 * services, plus cPanel/FTP because a host login is what an Iranian agency
 * actually receives.
 *
 * WHY THIS IS CONFIGURATION, NOT SAMPLE DATA: an empty `credential_types` table
 * makes the "add credential" form unusable — the user opens it and finds no
 * type to choose. Seeding the kinds is the difference between a feature that
 * works on a fresh install and one that only works on the developer's machine.
 *
 * `fields_schema` is a JSON array. `required: true` drives the per-credential
 * completeness badge; it does NOT gate project activation, because the client's
 * own mandatory-credential list (REQ-06.02) has not been supplied yet.
 */
class CredentialTypeSeeder extends Seeder
{
    /** @var array<string, array{title:string, fields:list<array{key:string,label:string,required?:bool}>}> */
    public const TYPES = [
        'wordpress' => [
            'title' => 'وردپرس',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور', 'required' => true],
            ],
        ],
        'hosting' => [
            'title' => 'هاست / cPanel',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور پنل', 'required' => true],
                ['key' => 'ftp_password', 'label' => 'رمز FTP', 'required' => false],
            ],
        ],
        'domain' => [
            'title' => 'دامنه (ثبت‌کننده)',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور پنل', 'required' => true],
            ],
        ],
        'gsc' => [
            'title' => 'Google Search Console',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور', 'required' => true],
            ],
        ],
        'ga4' => [
            'title' => 'Google Analytics',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور', 'required' => true],
            ],
        ],
        'other' => [
            'title' => 'سایر سرویس‌ها',
            'fields' => [
                ['key' => 'password', 'label' => 'رمز عبور', 'required' => false],
            ],
        ],
    ];

    /**
     * §6 — which of those kinds an SEO project must have before it can be
     * activated.
     *
     * THESE ARE A DEFAULT, NOT A DECISION. The requirements document mandates
     * the mechanism («سیستم باید امکان تعریف فهرست دسترسی‌های ضروری را داشته
     * باشد») and §14 makes the gate an acceptance criterion, but it never says
     * WHICH kinds are mandatory. The three below are the ones §6 names first for
     * a project's access section — WordPress, host, domain — so they are the most
     * defensible starting point, and they live in a table precisely so the client
     * can change the list without a release. Confirming the list is an open
     * client decision; shipping an empty list would have left the gate unable to
     * fire at all, which is the state the code was in.
     */
    private const REQUIRED_FOR_SEO = ['wordpress', 'hosting', 'domain'];

    public function run(): void
    {
        $order = 0;
        $ids = [];

        foreach (self::TYPES as $code => $definition) {
            $type = CredentialType::updateOrCreate(
                ['code' => $code],
                [
                    'title' => $definition['title'],
                    'fields_schema' => $definition['fields'],
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );

            $ids[$code] = $type->id;
        }

        foreach (self::REQUIRED_FOR_SEO as $code) {
            if (! isset($ids[$code])) {
                continue;
            }

            CredentialRequirement::updateOrCreate(
                ['project_type' => 'seo', 'credential_type_id' => $ids[$code]],
                ['is_required' => true, 'blocks_finalize' => true],
            );
        }

        $this->command?->info('Credential types: ' . count(self::TYPES) . ' synced, '
            . count(self::REQUIRED_FOR_SEO) . ' mandatory for SEO.');
    }
}
