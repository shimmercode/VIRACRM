<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-05.02 / §5-1 — two fields the SEO project was missing.
 *
 * «نام پروژه، نام مشتری و آدرس وب‌سایت» — the website address.
 * «مبلغ قرارداد و توضیحات مالی مرتبط» — financial notes, which are not the same
 * thing as «توضیحات داخلی» two bullets later. Only one notes column existed
 * (`projects.notes`, labelled یادداشت داخلی), so the financial note had nowhere
 * to go. Both are added here because both belong to this one requirement.
 *
 * An SEO project had no place to record the website it optimises. The only
 * domain column in the schema is `design_project_details.domain`, which belongs
 * to the design fieldset and to a different project type; reusing it would let
 * an SEO project write into the design table and make the two fieldsets lie
 * about which type a project is.
 *
 * BACKWARD COMPATIBILITY
 * ----------------------
 * The column is nullable with no default, so:
 *   - existing `seo_project_details` rows survive unchanged and read back NULL;
 *   - an SEO project created before this migration is still valid;
 *   - no row is rewritten, so no data is touched.
 *
 * Both directions are guarded with hasColumn/hasTable so the migration is
 * idempotent: re-running it on a database that already has the column is a
 * no-op rather than a fatal. That matters because the migration set is replayed
 * against freshly copied flow databases on every test run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_project_details')) {
            return;
        }

        if (! Schema::hasColumn('seo_project_details', 'website_url')) {
            Schema::table('seo_project_details', function (Blueprint $table): void {
                // Nullable, no default: NULL means "not recorded yet", which is
                // a different statement from an empty string.
                $table->string('website_url', 2048)->nullable()->after('keyword_count');
            });
        }

        if (! Schema::hasColumn('seo_project_details', 'financial_notes')) {
            Schema::table('seo_project_details', function (Blueprint $table): void {
                $table->text('financial_notes')->nullable()->after('website_url');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('seo_project_details')) {
            return;
        }

        // Guarded per column so a half-applied up can still be rolled back.
        foreach (['website_url', 'financial_notes'] as $column) {
            if (Schema::hasColumn('seo_project_details', $column)) {
                Schema::table('seo_project_details', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
