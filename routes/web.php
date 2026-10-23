<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes (Default Root / is Login Page)
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::livewire('/', 'pages::auth.login')->name('login');
    Route::get('/login', fn () => redirect()->route('login'));
});

// Optional Information Landing Page
Route::view('/tentang', 'welcome')->name('about');

// Authenticated Routes (Both Petugas Lapangan & Admin Dinas)
Route::middleware('auth')->group(function () {
    // Logout Handler
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Shared Core Modules (Scoping handled per role in models & controllers)
    Route::livewire('/dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('/pus', 'pages::pus.index')->name('pus.index');
    Route::livewire('/screening/create', 'pages::screening.create')->name('screening.create');
    Route::livewire('/screening/priority-queue', 'pages::screening.priority-queue')->name('screening.priority-queue');
    Route::livewire('/interventions', 'pages::interventions.index')->name('interventions.index');
    Route::livewire('/screenings', 'pages::screening.index')->name('screening.index');
    Route::livewire('/analitik', 'pages::analitik.index')->name('analitik.index');
    Route::livewire('/mcpr-tfr', 'pages::mcpr.index')->name('mcpr.index');
    Route::livewire('/laporan', 'pages::laporan.index')->name('laporan.index');
    Route::livewire('/profile', 'pages::profile.index')->name('profile');

    // Admin Dinas Only Area
    Route::middleware('role:admin_dinas')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/users', 'pages::admin.users')->name('users');

        Route::livewire('/wilayah', 'pages::admin.wilayah')->name('wilayah');

        Route::livewire('/laporan', 'pages::admin.laporan')->name('laporan');
    });
});
