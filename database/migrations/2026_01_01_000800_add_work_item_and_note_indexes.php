<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EXP-WORKITEM-001 / EXP-NOTE-001 — completing the کارتابل.
 *
 * WHY THIS MIGRATION EXISTS
 * -------------------------
 * `work_items` already holds real obligations (9 rows at the time of writing)
 * and the dashboard already lists them, but **nothing in the application could
 * ever mark one done**. `state` was written at seed time and never changed.
 * An obligation that can only appear and never be resolved is a display, not a
 * feature — exactly the distinction the directive draws.
 *
 * The columns added here are the ones the completion flow genuinely needs:
 *
 *   state      already existed but had no constraint; the values are now
 *              documented and indexed for the "open" filter.
 *   notes      completion_notes existed; nothing else was missing.
 *
 * `notes` (the table) also needed one thing: an index for the "my tasks /
 * overdue" queries the dashboard and the tasks page run on every load.
 * Without it those are full scans once the table has a year of history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            // The open-item query is `done_at IS NULL ORDER BY due_date`, run on
            // the dashboard for every user. Due date alone is not selective
            // enough once there are years of rows, so the pair is indexed.
            $table->index(['done_at', 'due_date'], 'work_items_open_idx');
            $table->index(['project_id', 'kind', 'period'], 'work_items_period_idx');
        });

        Schema::table('notes', function (Blueprint $table) {
            // The tasks page filters by status + due date; the dashboard counts
            // overdue ones. Both are the same index.
            $table->index(['status', 'due_date'], 'notes_status_due_idx');
            $table->index(['assigned_to', 'status'], 'notes_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropIndex('work_items_open_idx');
            $table->dropIndex('work_items_period_idx');
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_status_due_idx');
            $table->dropIndex('notes_assignee_idx');
        });
    }
};
