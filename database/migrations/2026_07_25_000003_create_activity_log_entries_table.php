<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail: who changed what, when, and from/to what.
 *
 * Deliberately the same shape as the tea-roster and registration apps'
 * activity_log_entries so all three are read the same way. `type` separates
 * admin actions from system events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log_entries', function (Blueprint $table) {
            $table->id();

            // 'admin_action' or 'system_event'.
            $table->string('type');

            // Who did it. No foreign key on purpose: deactivating (or, one day,
            // deleting) a teacher must never erase history.
            $table->unsignedBigInteger('user_id')->nullable();

            // Their name at the time, so old entries still read correctly after
            // a rename.
            $table->string('user_name')->nullable();

            // Machine-readable code, e.g. 'user.created', 'role.updated'.
            $table->string('action');

            // Plain-English sentence shown in the UI.
            $table->string('description');

            // Which record this is about, as a simple label + id rather than a
            // polymorphic relation.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Extra detail; for edits this holds before/after.
            $table->json('properties')->nullable();

            $table->timestamps();

            // Reads are almost always "newest first, filtered by type".
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log_entries');
    }
};
