<?php

/**
 * Permission atom registry.
 *
 * Every action the app gates on lives here. AuthServiceProvider walks this file
 * at boot and calls Gate::define for each atom, so every check — `@can(...)` in
 * Blade, `->middleware('can:...')` on a route, `Gate::allows(...)` in code —
 * funnels through one bottleneck: CurrentTeacher::can, which reads the CSV on
 * the signed-in teacher's role.
 *
 * NOTE: this app has no passwords — teachers sign in with an emailed magic link
 * and the session holds `teacher_id` rather than a Laravel Auth user. That's why
 * the Gate callbacks take a NULLABLE user and ignore it; see AuthServiceProvider.
 *
 * Adding a permission:
 *   1. Add the atom to the right module group below with a short label.
 *   2. Tick it on whichever roles should have it (Admin -> Roles).
 *   3. Apply it with `@can('foo')` (Blade) or `->middleware('can:foo')` (route).
 */
return [
    'attendance' => [
        'label' => 'Attendance',
        'atoms' => [
            'mark_attendance' => 'Mark attendance',
            'edit_attendance' => 'Edit attendance after the day',
            'mark_book_distribution' => 'Record book distribution',
        ],
    ],

    'reporting' => [
        'label' => 'Reports',
        'atoms' => [
            'view_summary' => "View today's summary",
            'view_details' => 'View attendance details',
            'view_reports' => 'View the full-year report',
        ],
    ],

    'administration' => [
        'label' => 'Administration',
        'atoms' => [
            'run_registration_sync' => 'Sync students from registration',
            'manage_users' => 'Add, edit & deactivate teachers',
            'manage_roles' => 'Create roles & change their permissions',
            'view_audit_log' => 'View the audit log',
        ],
    ],
];
