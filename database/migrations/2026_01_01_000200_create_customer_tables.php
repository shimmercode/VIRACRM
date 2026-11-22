<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-03.01–03.08 — customer records.
 *
 * A single `customers` table holds both natural and legal persons, because the
 * client document §1 demands a *central customer profile* that everything else
 * points at. Splitting into two tables would make every contract, project and
 * invoice need a polymorphic customer reference — exactly the "duplicate entry"
 * problem the document is trying to solve.
 *
 * Type-specific columns are nullable and enforced at the validation layer
 * (CustomerType::requiredIdentityFields / forbiddenFields).
 *
 * DEDUP: the document §3 requires preventing duplicate customers. We keep a
 * normalised search key alongside the raw values so "۰۹۱۲ ۱۲۳ ۴۵۶۷",
 * "09121234567" and "+989121234567" collapse to one comparison, without
 * destroying what the user originally typed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->nullable()->unique(); // human-friendly VIRA-0001
            $table->string('type', 16)->index();              // natural | legal

            // ── Natural person ────────────────────────────────────────────
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('national_id', 10)->nullable();
            $table->date('birth_date')->nullable();

            // ── Legal entity ──────────────────────────────────────────────
            $table->string('company_name')->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->string('legal_representative')->nullable();

            // ── Contact (both types) ──────────────────────────────────────
            $table->string('mobile', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();

            // ── Relationship ──────────────────────────────────────────────
            $table->string('acquaintance_source')->nullable(); // how we met them (§3)
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('lead')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // ── Dedup support (normalised, index-only) ────────────────────
            $table->string('search_key', 255)->nullable()->index();
            $table->string('normalized_mobile', 16)->nullable()->index();
            $table->string('normalized_national_id', 10)->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['last_name', 'first_name']);
            $table->index('company_name');
        });

        // §3: a customer can have several websites; the profile must show them
        // and search must match on domain.
        Schema::create('customer_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('url');                  // as entered
            $table->string('domain', 191)->index(); // normalised, for search
            $table->string('label')->nullable();    // 'سایت اصلی', 'فروشگاه'
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_primary']);
        });

        // §3: contact persons for a legal entity.
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_websites');
        Schema::dropIfExists('customers');
    }
};
