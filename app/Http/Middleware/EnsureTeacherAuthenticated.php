<?php

namespace App\Http\Middleware;

use App\Support\CurrentTeacher;
use Closure;
use Illuminate\Http\Request;

class EnsureTeacherAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! session('teacher_logged_in') ||
            ! session('login_token_expires_at') ||
            session('login_token_expires_at') < now()
        ) {
            return redirect()->route('login.form')->withErrors('Please login first.');
        }

        // Re-check the account itself on every request, not just at sign-in:
        // deactivating a teacher has to take effect now, even if they already
        // have a live session. Same for an account that has been removed from
        // the database entirely.
        $teacher = CurrentTeacher::get();

        if ($teacher === null || ! $teacher->isActive()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login.form')
                ->withErrors('That account has been deactivated. Please contact an administrator.');
        }

        return $next($request);
    }
}
