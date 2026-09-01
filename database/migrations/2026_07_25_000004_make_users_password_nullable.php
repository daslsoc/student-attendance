<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * There are no passwords in this app: teachers sign in with an emailed magic
 * link, and nothing ever reads or writes users.password. The column was
 * inherited from the Laravel skeleton and left NOT NULL, which meant an account
 * added through the new "Add teacher" screen — name, email, role, no password —
 * couldn't be inserted at all.
 *
 * Making it nullable is the honest fix: an account with no password is exactly
 * what every account here is. Existing rows keep whatever hash they carry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
