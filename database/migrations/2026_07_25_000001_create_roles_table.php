<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles: a named bundle of permission atoms. Each teacher points at exactly one
 * row here (users.role_id, added in the next migration).
 *
 * `permission_list` is a comma-BOUNDED CSV (",a,b,c,"). The leading and
 * trailing commas are what let User::hasPermission do a substring check for
 * ",{$atom}," without one atom name matching a longer one.
 *
 * Three roles are seeded so the app is usable the moment this runs; the atoms
 * on each are editable afterwards in Admin -> Roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            // Comma-bounded CSV of atoms from config/permissions.php.
            $table->text('permission_list');
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert([
            [
                'name' => 'Administrator',
                'description' => 'Full access, including teacher accounts, roles and the audit log.',
                'permission_list' => $this->csv([
                    'mark_attendance', 'edit_attendance', 'mark_book_distribution',
                    'view_summary', 'view_details', 'view_reports',
                    'run_registration_sync', 'manage_users', 'manage_roles', 'view_audit_log',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Coordinator',
                'description' => 'Runs attendance across the school. Cannot manage accounts or roles.',
                'permission_list' => $this->csv([
                    'mark_attendance', 'edit_attendance', 'mark_book_distribution',
                    'view_summary', 'view_details', 'view_reports', 'run_registration_sync',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Teacher',
                'description' => "Marks their class and sees today's summary.",
                'permission_list' => $this->csv([
                    'mark_attendance', 'mark_book_distribution', 'view_summary',
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }

    /**
     * @param  list<string>  $atoms
     */
    private function csv(array $atoms): string
    {
        return ','.implode(',', $atoms).',';
    }
};
