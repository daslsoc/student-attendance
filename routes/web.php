<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookDistributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\RegistrationSyncController;
use App\Http\Middleware\EnsureTeacherAuthenticated;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
// Throttle link requests so the endpoint can't be used to spam a teacher's
// inbox or to probe which emails are registered.
Route::post('/login', [AuthController::class, 'sendLoginLink'])
    ->middleware('throttle:5,1')
    ->name('login.send');
Route::get('/login/{token}', [AuthController::class, 'loginUsingToken'])->name('login.token');

// Signed-in area. EnsureTeacherAuthenticated also re-checks that the account is
// still active on every request, and each route names the permission atom
// (config/permissions.php) its page needs.
Route::middleware([EnsureTeacherAuthenticated::class])->group(function () {
    Route::get('/attendance-selection', [AttendanceController::class, 'index'])
        ->middleware('can:mark_attendance')->name('attendance.selection');
    Route::get('/attendance', [AttendanceController::class, 'showForm'])
        ->middleware('can:mark_attendance')->name('attendance.form');
    Route::post('/attendance', [AttendanceController::class, 'submit'])
        ->middleware('can:mark_attendance')->name('attendance.submit');

    Route::get('/book-distribution-selection', [BookDistributionController::class, 'index'])
        ->middleware('can:mark_book_distribution')->name('book_distribution.selection');
    Route::get('/book-distribution', [BookDistributionController::class, 'showForm'])
        ->middleware('can:mark_book_distribution')->name('book_distribution.form');
    Route::post('/book-distribution', [BookDistributionController::class, 'submit'])
        ->middleware('can:mark_book_distribution')->name('book_distribution.submit');

    Route::get('/attendance-summary', [DashboardController::class, 'summary'])
        ->middleware('can:view_summary')->name('attendance.summary');
    Route::get('/attendance-details', [DashboardController::class, 'details'])
        ->middleware('can:view_details')->name('attendance.details');
    Route::get('/attendance-report', [DashboardController::class, 'report'])
        ->middleware('can:view_reports')->name('attendance.report');
    Route::get('/attendance-edit', [DashboardController::class, 'editGrid'])
        ->middleware('can:edit_attendance')->name('attendance.edit');
    Route::post('/attendance-edit', [DashboardController::class, 'updateGrid'])
        ->middleware('can:edit_attendance')->name('attendance.edit.update');

    Route::get('/registration-sync', [RegistrationSyncController::class, 'show'])
        ->middleware('can:run_registration_sync')->name('integration.status');
    Route::post('/registration-sync', [RegistrationSyncController::class, 'run'])
        ->middleware('can:run_registration_sync')->name('integration.sync');

    // Teacher accounts. Only a role carrying manage_users gets in here.
    Route::middleware('can:manage_users')->group(function () {
        Route::get('/admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [UserAdminController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [UserAdminController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
        // "Remove" is a deactivation, so it's a POST, not a DELETE.
        Route::post('/admin/users/{user}/deactivate', [UserAdminController::class, 'deactivate'])->name('admin.users.deactivate');
        Route::post('/admin/users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('admin.users.reactivate');
    });

    // Roles and their permissions.
    Route::middleware('can:manage_roles')->group(function () {
        Route::get('/admin/roles', [RoleController::class, 'index'])->name('admin.roles.index');
        Route::get('/admin/roles/create', [RoleController::class, 'create'])->name('admin.roles.create');
        Route::post('/admin/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::get('/admin/roles/{role}/edit', [RoleController::class, 'edit'])->name('admin.roles.edit');
        Route::put('/admin/roles/{role}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::delete('/admin/roles/{role}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
    });

    Route::get('/admin/audit', [ActivityLogController::class, 'index'])
        ->middleware('can:view_audit_log')->name('admin.audit');

    // Teacher help guide (screenshots live in public/images/help — see
    // tests/Browser/HelpScreenshotsCapture.php to regenerate them). No
    // permission atom: anyone who can sign in can read the guide.
    Route::get('/help', [HelpController::class, 'index'])->name('help');
});

Route::get('/', function () {
    if (! session('teacher_logged_in')) {
        return redirect()->route('login.form');
    }

    // Not every role can mark attendance any more, so send people who can't to
    // the help page rather than a 403 they can do nothing about.
    return redirect()->route(
        Gate::allows('mark_attendance') ? 'attendance.selection' : 'help'
    );
});
