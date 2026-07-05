<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookDistributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\RegistrationSyncController;
use App\Http\Middleware\EnsureTeacherAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
// Throttle link requests so the endpoint can't be used to spam a teacher's
// inbox or to probe which emails are registered.
Route::post('/login', [AuthController::class, 'sendLoginLink'])
    ->middleware('throttle:5,1')
    ->name('login.send');
Route::get('/login/{token}', [AuthController::class, 'loginUsingToken'])->name('login.token');

Route::middleware([EnsureTeacherAuthenticated::class])->group(function () {
    Route::get('/attendance-selection', [AttendanceController::class, 'index'])->name('attendance.selection');
    Route::get('/attendance', [AttendanceController::class, 'showForm'])->name('attendance.form');
    Route::post('/attendance', [AttendanceController::class, 'submit'])->name('attendance.submit');

    Route::get('/book-distribution-selection', [BookDistributionController::class, 'index'])->name('book_distribution.selection');
    Route::get('/book-distribution', [BookDistributionController::class, 'showForm'])->name('book_distribution.form');
    Route::post('/book-distribution', [BookDistributionController::class, 'submit'])->name('book_distribution.submit');

    Route::get('/attendance-summary', [DashboardController::class, 'summary'])->name('attendance.summary');
    Route::get('/attendance-details', [DashboardController::class, 'details'])->name('attendance.details');
    Route::get('/attendance-report', [DashboardController::class, 'report'])->name('attendance.report');
    Route::get('/attendance-edit', [DashboardController::class, 'editGrid'])->name('attendance.edit');
    Route::post('/attendance-edit', [DashboardController::class, 'updateGrid'])->name('attendance.edit.update');

    Route::get('/registration-sync', [RegistrationSyncController::class, 'show'])->name('integration.status');
    Route::post('/registration-sync', [RegistrationSyncController::class, 'run'])->name('integration.sync');

    // Teacher help guide (screenshots live in public/images/help — see
    // tests/Browser/HelpScreenshotsCapture.php to regenerate them).
    Route::get('/help', [HelpController::class, 'index'])->name('help');
});

Route::get('/', function () {
    return redirect()->route(
        session('teacher_logged_in') ? 'attendance.selection' : 'login.form'
    );
});
