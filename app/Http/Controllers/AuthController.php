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

        $token = Str::random(32);
        $expiration = now()->addHours(config('custom.token_expiry_hours', 4));

        // Save token details to the teacher's record.
        $teacher->update([
            'login_token' => $token,
            'login_token_expires_at' => $expiration,
        ]);

        $loginLink = route('login.token', ['token' => $token]);

        Mail::to($request->email)->send(new LoginLinkMail($loginLink));

        // Log by id, not name/email — keep PII out of the application log.
        Log::info('Login link emailed', ['teacher_id' => $teacher->id]);

        return back()->with('message', 'A login link has been sent to your email.');
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
