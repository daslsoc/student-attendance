<?php

namespace App\Support;

use App\Models\User;

/**
 * Who is signed in, in an app that has no Laravel Auth user.
 *
 * Teachers sign in with an emailed magic link: AuthController puts `teacher_id`
 * in the session and nothing is ever passed to Auth::login. So `Auth::user()` is
 * always null here, and everything that needs to know who is acting — the Gates
 * in AuthServiceProvider, the audit logger, the admin screens — asks this class
 * instead.
 *
 * The lookup deliberately hits the database on every call rather than caching:
 * that is what makes a role edit (or a deactivation) take effect on the very
 * next request instead of at the teacher's next sign-in.
 */
final class CurrentTeacher
{
    public static function get(): ?User
    {
        $id = session('teacher_id');

        if ($id === null) {
            return null;
        }

        return User::with('role')->find($id);
    }

    /**
     * Does the signed-in teacher hold this permission? False when nobody is
     * signed in, when the account has since been deactivated, or when their
     * role doesn't carry the atom.
     */
    public static function can(string $atom): bool
    {
        return self::get()?->hasPermission($atom) ?? false;
    }
}
