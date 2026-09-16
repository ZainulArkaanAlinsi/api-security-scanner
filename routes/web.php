<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketImportController;
use App\Http\Controllers\TicketScanController;
use App\Http\Controllers\TicketShareController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('tickets.index') : view('welcome');
})->name('home');

// Public, read-only report shared by its owner.
Route::get('/r/{token}', [TicketShareController::class, 'show'])->name('tickets.public');
Route::get('/badge/{token}.svg', [TicketShareController::class, 'badge'])->name('tickets.badge');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'store'])->middleware('throttle:5,1')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', function () {
        return redirect()->route('tickets.index');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/webhook', [ProfileController::class, 'updateWebhook'])->middleware('throttle:10,1')->name('profile.webhook');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/tickets/export', [TicketController::class, 'export'])->name('tickets.export');
    Route::get('/tickets/import', [TicketImportController::class, 'create'])->name('tickets.import');
    Route::post('/tickets/import', [TicketImportController::class, 'store'])->middleware('throttle:5,1');
    Route::resource('tickets', TicketController::class);
    Route::patch('/tickets/{ticket}/monitoring', [TicketScanController::class, 'monitoring'])->name('tickets.monitoring');
    Route::patch('/tickets/{ticket}/share', [TicketShareController::class, 'update'])->name('tickets.share');

    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    Route::post('/tickets/scan-all', [TicketScanController::class, 'scanAll'])->middleware('throttle:3,1')->name('tickets.scan-all');
    Route::post('/tickets/{ticket}/scan', [TicketScanController::class, 'scan'])->middleware('throttle:10,1')->name('tickets.scan');
    Route::get('/tickets/{ticket}/scans', [TicketScanController::class, 'history'])->name('tickets.scans');
    Route::get('/tickets/{ticket}/compare', [TicketScanController::class, 'compare'])->name('tickets.compare');
    Route::get('/tickets/{ticket}/report', [TicketScanController::class, 'report'])->name('tickets.report');
    Route::get('/tickets/{ticket}/print', [TicketScanController::class, 'print'])->name('tickets.print');
});
