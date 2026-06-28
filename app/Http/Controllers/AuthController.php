<?php

namespace App\Http\Controllers;

use App\Mail\LoginLinkMail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function sendLoginLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Look up the teacher in the users table.
        $teacher = \App\Models\User::where('email', $request->email)->first();
        if (! $teacher) {
            return back()->withErrors('Teacher not found.');
        }

        // Reuse an existing, still-valid token instead of minting a new one on
        // every click. Otherwise a second "send" invalidates the first email's
        // link — and because Gmail collapses the two messages into one thread, a
        // teacher often opens the older (now-dead) one. Reusing the token means
        // every email in the window carries the *same* working link, so it no
        // longer matters which message they open.
        if ($teacher->login_token &&
            $teacher->login_token_expires_at &&
            $teacher->login_token_expires_at->isFuture()
        ) {
            $token = $teacher->login_token;
        } else {
            $token = Str::random(32);
            $teacher->update([
                'login_token' => $token,
                'login_token_expires_at' => now()->addHours(config('custom.token_expiry_hours', 4)),
            ]);
        }

        $loginLink = route('login.token', ['token' => $token]);

        // Send after the response is flushed so the page returns immediately
        // rather than blocking on SMTP. This runs in the same process after the
        // browser has its response, so it needs no queue worker (important on
        // shared hosting). Log by id, not name/email — keep PII out of the log.
        $email = $request->email;
        $teacherId = $teacher->id;
        dispatch(function () use ($email, $loginLink, $teacherId) {
            Mail::to($email)->send(new LoginLinkMail($loginLink));
            Log::info('Login link emailed', ['teacher_id' => $teacherId]);
        })->afterResponse();

        return back()->with('message', 'A login link has been sent to your email. If you request it again, open the most recent message — the link stays the same.');
    }

    public function loginUsingToken(Request $request, $token)
    {
        try {
            $teacher = \App\Models\User::where('login_token', $token)
                ->where('login_token_expires_at', '>', now())
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return redirect('login')->withErrors(['msg' => 'That link has expired. Make sure you have clicked on the latest email or else enter your email and try again.']);
        }

        // Regenerate the session ID as we cross from anonymous to logged-in so
        // a pre-set (fixed) session ID can't be ridden across the boundary.
        $request->session()->regenerate();

        session([
            'login_token_expires_at' => $teacher->login_token_expires_at,
            'teacher_logged_in' => true,
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name,
        ]);
        Log::info('Teacher logged in', ['teacher_id' => $teacher->id]);

        return redirect()->route('attendance.selection');
    }
}
