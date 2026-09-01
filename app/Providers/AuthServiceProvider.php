<?php

namespace App\Providers;

use App\Models\User;
use App\Support\CurrentTeacher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Turns config/permissions.php into Gates.
 *
 * One Gate per atom, so `@can('view_reports')`, `->middleware('can:manage_users')`
 * and `Gate::allows(...)` all answer the same question the same way.
 *
 * The callbacks type-hint a NULLABLE user and ignore the argument. That is
 * deliberate: this app has no Laravel Auth user (teachers sign in with a magic
 * link and the session carries `teacher_id`), so Laravel would pass null and
 * refuse to run a non-nullable callback at all. Who is acting comes from
 * CurrentTeacher instead.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (config('permissions', []) as $module) {
            foreach (array_keys($module['atoms'] ?? []) as $atom) {
                Gate::define($atom, fn (?User $user) => CurrentTeacher::can($atom));
            }
        }
    }
}
