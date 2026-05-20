<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();
    
    if ($user->hasRole('admin')) return redirect()->route('admin.dashboard');
    if ($user->hasRole('dean')) return redirect()->route('dean.dashboard');
    if ($user->hasRole('program head')) return redirect()->route('program-head.dashboard');
    if ($user->hasRole('faculty')) return redirect()->route('faculty.dashboard');
    if ($user->hasRole('student')) return redirect()->route('student.dashboard');
    if ($user->hasRole('staff')) return redirect()->route('staff.dashboard');

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Admin Dashboard
    Volt::route('/admin/dashboard', 'admin.dashboard')
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Volt::route('/admin/users', 'admin.manage-users')
        ->middleware('role:admin')
        ->name('admin.users');

    Volt::route('/admin/evaluation-settings', 'admin.evaluation-settings')
        ->middleware('role:admin')
        ->name('admin.evaluation-settings');

    Volt::route('/admin/questions', 'admin.manage-questions')
        ->middleware('role:admin')
        ->name('admin.questions');

    // Dean Dashboard
    Volt::route('/dean/dashboard', 'dean.dashboard')
        ->middleware('role:dean')
        ->name('dean.dashboard');

    // Program Head Dashboard
    Volt::route('/program-head/dashboard', 'program-head.dashboard')
        ->middleware('role:program head')
        ->name('program-head.dashboard');

    // Faculty Dashboard
    Volt::route('/faculty/dashboard', 'faculty.dashboard')
        ->middleware('role:faculty')
        ->name('faculty.dashboard');

    // Student Dashboard
    Volt::route('/student/dashboard', 'student.dashboard')
        ->middleware('role:student')
        ->name('student.dashboard');

    // Staff Dashboard
    Volt::route('/staff/dashboard', 'staff.dashboard')
        ->middleware('role:staff')
        ->name('staff.dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
