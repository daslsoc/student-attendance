<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookDistributionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Middleware\EnsureTeacherAuthenticated;

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'sendLoginLink'])->name('login.send');
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
});

Route::get('/', function () {
    return redirect()->route('login.form');
});
