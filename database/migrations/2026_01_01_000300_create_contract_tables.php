<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-04.01–04.10 — contracts, the contract bank, templates and versions.
 *
 * TWO DECISIONS WORTH RECORDING:
 *
 * 1. Status vs. expiry. The document lists "در آستانه انقضا" (expiring soon)
 *    as a status, but it is really a function of end_date and the configurable
 *    warning thresholds. We store the *user-selectable* status and derive the
 *    expiring flag at query time. Storing it would let the banner and the
 *    reality disagree after someone edits a date.
 *
 * 2. Versions are immutable. §4 requires that renewals keep the old versions.
 *    Once a contract is finalised we snapshot it into contract_versions and
 *    never mutate that snapshot, so "what did we actually sign" has an answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Configurable dictionaries (§15) ───────────────────────────────
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            // Template body with {{placeholder}} tokens, per §4 "editable templates".
            $table->longText('body');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contract_type_id', 'is_active']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->nullable()->unique(); // VIRA-C-0001
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('contract_templates')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // §4: parties are auto-filled from the customer record, then editable.
            $table->string('party_one_name')->nullable();
            $table->string('party_two_name')->nullable();
            $table->text('party_one_address')->nullable();
            $table->text('party_two_address')->nullable();

            // Money is stored as an integer rial amount, never a float.
            $table->unsignedBigInteger('amount')->default(0);

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status', 16)->default('draft')->index();

            // Rendering / signature state.
            $table->longText('rendered_body')->nullable(); // finalised text
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('renewed_from_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // The expiry dashboard and warning thresholds query on these.
            $table->index(['status', 'end_date']);
            $table->index(['customer_id', 'status']);
            $table->index('end_date');
        });

        // §4: immutable snapshots. Old versions survive a renewal.
        Schema::create('contract_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->longText('body');
            $table->json('snapshot')->nullable(); // full field state at that moment
            $table->string('reason', 32)->default('finalize'); // finalize|renew|amend
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'version']);
        });

        // §4: scanned signed copies, stored outside the document root.
        Schema::create('contract_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->nullable();
            $table->string('label')->nullable();
            $table->string('kind', 24)->default('scan'); // scan|pdf|addendum
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_files');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('contract_templates');
        Schema::dropIfExists('contract_types');
    }
};
