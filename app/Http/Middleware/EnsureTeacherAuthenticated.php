<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTeacherAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('teacher_logged_in') ||
            !session('login_token_expires_at') ||
            session('login_token_expires_at') < now()
        ) {
            return redirect()->route('login.form')->withErrors('Please login first.');
        }
        return $next($request);
    }
}
