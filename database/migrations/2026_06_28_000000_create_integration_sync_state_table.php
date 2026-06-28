<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A single row tracking how far we've synced from the registration app.
        Schema::create('integration_sync_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_synced_at')->nullable();   // registration high-water mark consumed
            $table->timestamp('last_checked_at')->nullable();  // when we last polled
            $table->unsignedInteger('last_count')->nullable();  // paid-student count last seen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_state');
    }
};
