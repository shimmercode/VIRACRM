<?php

namespace Database\Seeders;

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

    public function run(): void
    {
        $order = 0;

        foreach (self::TYPES as $code => $definition) {
            CredentialType::updateOrCreate(
                ['code' => $code],
                [
                    'title' => $definition['title'],
                    'fields_schema' => $definition['fields'],
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );
        }

        $this->command?->info('Credential types: ' . count(self::TYPES) . ' synced.');
    }
}
