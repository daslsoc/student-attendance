<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Point every teacher at a role, and give accounts an off switch.
 *
 * `deactivated_at` is how a teacher is "removed": no login link will be sent to
 * them, an existing link stops working, and any live session is dropped on its
 * next request. The row survives so the audit log keeps naming a real person.
 *
 * Rollout: the account named by ROLE_ROLLOUT_ADMIN_EMAIL (see config/roles.php)
 * becomes the Administrator and everyone else lands on Teacher — mark
 * attendance, record books, see today's summary. That is narrower than today
 * (where any teacher can reach the reports and the registration sync), so
 * expect to promote the coordinators by hand afterwards in Admin -> Teachers.
 * If that isn't set, or names an address with no account, the oldest account is
 * promoted instead, so the app is never left with nobody who can manage users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable + nullOnDelete: roles are never deleted while they have
            // members (RoleController blocks it), but if one ever were, users
            // must not vanish with it.
            $table->foreignId('role_id')->nullable()->after('password')
                ->constrained('roles')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable()->after('role_id');
        });

        $adminRoleId = DB::table('roles')->where('name', 'Administrator')->value('id');
        $defaultRoleId = DB::table('roles')->where('name', 'Teacher')->value('id');

        if ($adminRoleId === null || $defaultRoleId === null) {
            return;
        }

        DB::table('users')->update(['role_id' => $defaultRoleId]);

        $adminEmail = config('roles.rollout_admin_email');

        $promoted = $adminEmail
            ? DB::table('users')->where('email', $adminEmail)->update(['role_id' => $adminRoleId])
            : 0;

        if ($promoted === 0) {
            // Fall back to the oldest account so the deployment always has one
            // administrator. Logged loudly because it means nobody was named —
            // or the named account wasn't there — and somebody should check who
            // ended up with it.
            $oldest = DB::table('users')->orderBy('id')->value('id');

            if ($oldest !== null) {
                DB::table('users')->where('id', $oldest)->update(['role_id' => $adminRoleId]);

                Log::warning('Role rollout: no ROLE_ROLLOUT_ADMIN_EMAIL match, promoted oldest user instead.', [
                    'expected_email' => $adminEmail ?: '(not set)',
                    'promoted_user_id' => $oldest,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'deactivated_at']);
        });
    }
};
