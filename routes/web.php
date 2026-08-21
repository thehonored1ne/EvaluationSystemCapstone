<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->middleware('throttle:global')->name('home');

Route::get('dashboard', function () {
    $user = auth()->user();

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    }
    if ($user->hasRole('dean')) {
        return redirect()->route('dean.dashboard');
    }
    if ($user->hasRole('department head')) {
        return redirect()->route('department-head.dashboard');
    }
    if ($user->hasRole('program head')) {
        return redirect()->route('program-head.dashboard');
    }
    if ($user->hasRole('faculty')) {
        return redirect()->route('faculty.dashboard');
    }
    if ($user->hasRole('student')) {
        return redirect()->route('student.dashboard');
    }
    if ($user->hasRole('staff')) {
        return redirect()->route('staff.dashboard');
    }

    return view('dashboard');
})->middleware(['auth', 'verified', 'throttle:global'])->name('dashboard');

Route::middleware(['auth', 'verified', 'throttle:global'])->group(function () {
    // Admin Dashboard
    Volt::route('/admin/dashboard', 'admin.dashboard')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::redirect('/admin/users', '/admin/employees')->name('admin.users');

    Volt::route('/admin/employees', 'admin.manage-employees')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.employees');

    Volt::route('/admin/students', 'admin.manage-students')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.students');

    // Legacy redirects for old user management routes
    Route::redirect('/admin/deans', '/admin/employees?selectedRole=dean')->name('admin.deans');
    Route::redirect('/admin/department-heads', '/admin/employees?selectedRole=department+head')->name('admin.department-heads');
    Route::redirect('/admin/program-heads', '/admin/employees?selectedRole=program+head')->name('admin.program-heads');
    Route::redirect('/admin/faculty', '/admin/employees?selectedRole=faculty')->name('admin.faculty');
    Route::redirect('/admin/staff', '/admin/employees?selectedRole=staff')->name('admin.staff');

    Volt::route('/admin/evaluation-settings', 'admin.evaluation-settings')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.evaluation-settings');

    Volt::route('/admin/questions', 'admin.manage-questions')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.questions');

    Volt::route('/admin/subjects', 'admin.manage-subjects')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.subjects');

    Volt::route('/admin/classes', 'admin.manage-classes')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.classes');

    Volt::route('/admin/departments', 'admin.manage-departments')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.departments');

    Volt::route('/admin/programs', 'admin.manage-programs')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.programs');

    Volt::route('/admin/ai', 'admin.manage-ai')
        ->lazy()
        ->middleware('role:admin')
        ->name('admin.ai');

    // Dean Dashboard
    Volt::route('/dean/dashboard', 'dean.dashboard')
        ->lazy()
        ->middleware('role:dean')
        ->name('dean.dashboard');

    // Department Head Dashboard
    Volt::route('/department-head/dashboard', 'department-head.dashboard')
        ->lazy()
        ->middleware('role:department head')
        ->name('department-head.dashboard');

    // Program Head Dashboard
    Volt::route('/program-head/dashboard', 'program-head.dashboard')
        ->lazy()
        ->middleware('role:program head')
        ->name('program-head.dashboard');

    // Faculty Dashboard
    Volt::route('/faculty/dashboard', 'faculty.dashboard')
        ->lazy()
        ->middleware('role:faculty')
        ->name('faculty.dashboard');

    // Student Dashboard
    Volt::route('/student/dashboard', 'student.dashboard')
        ->lazy()
        ->middleware('role:student')
        ->name('student.dashboard');

    // Staff Dashboard
    Volt::route('/staff/dashboard', 'staff.dashboard')
        ->lazy()
        ->middleware('role:staff')
        ->name('staff.dashboard');

    // Unified Sidebar Features
    Volt::route('/manage-evaluations', 'manage-evaluations')
        ->lazy()
        ->middleware('role:admin|dean|program head')
        ->name('manage-evaluations');

    Volt::route('/evaluation-results', 'evaluation-results')
        ->lazy()
        ->middleware('role:admin|dean')
        ->name('evaluation-results');

    Volt::route('/rankings', 'rankings')
        ->lazy()
        ->middleware('role:admin|dean|program head')
        ->name('rankings');

    Volt::route('/analytics', 'analytics')
        ->lazy()
        ->middleware('role:admin')
        ->name('analytics');

    Volt::route('/reports', 'reports')
        ->lazy()
        ->middleware('role:admin|dean|program head')
        ->name('reports');

    Volt::route('/notifications', 'notifications')
        ->name('notifications');
});

Route::middleware(['auth', 'throttle:global'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/training', 'settings.training')
        ->middleware('role:admin')
        ->name('settings.training');
});

require __DIR__.'/auth.php';
