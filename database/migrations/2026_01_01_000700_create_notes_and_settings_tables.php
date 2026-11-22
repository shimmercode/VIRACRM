<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-08.01–08.02 — notes and manual reminders.
 * REQ-15.01 — configurable settings with an audit trail.
 *
 * §8 wants notes with title, description, due date, priority, status, an
 * optional link to a customer/contract/project, overdue surfacing in the
 * کارتابل, and completion history. A single `notes` table with nullable FKs
 * covers all three link targets without a polymorphic column, which keeps
 * referential integrity enforceable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 16)->default('open')->index();

            // Optional links; a note may reference none, one or several.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'due_date']);
        });

        // ── Settings (§15) ────────────────────────────────────────────────
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->unique();
            $table->text('value')->nullable();
            $table->string('type', 16)->default('string'); // string|int|bool|json
            $table->string('group', 48)->default('general')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('settings_history', function (Blueprint $table) {
            $table->id();
            $table->string('key', 96)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_history');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notes');
    }
};
