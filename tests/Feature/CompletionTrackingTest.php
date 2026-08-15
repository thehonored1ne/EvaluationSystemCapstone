<?php

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Evaluation;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'department head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'staff']);
    Role::firstOrCreate(['name' => 'student']);

    $this->ay = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
    $this->sem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $this->dept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);
    $this->adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@track.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    $this->faculty = Employee::create([
        'employee_number' => 'FAC-001',
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facUser = User::create(['name' => 'Alan Turing', 'email' => 'turing@track.com', 'employee_id' => $this->faculty->id, 'password' => 'password']);
    $this->facUser->assignRole('faculty');

    $this->facultyPeer = Employee::create([
        'employee_number' => 'FAC-002',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facPeerUser = User::create(['name' => 'Ada Lovelace', 'email' => 'lovelace@track.com', 'employee_id' => $this->facultyPeer->id, 'password' => 'password']);
    $this->facPeerUser->assignRole('faculty');
});

test('completion tracking component renders and allows tab switching across all 7 standardized categories', function () {
    $this->actingAs($this->adminUser);

    Livewire::withoutLazyLoading()
        ->test('manage-evaluations')
        ->assertSet('activeTab', 'student')
        ->assertSee('Student')
        ->set('activeTab', 'dean')
        ->assertSet('activeTab', 'dean')
        ->assertSee('Dean')
        ->set('activeTab', 'program_head')
        ->assertSet('activeTab', 'program_head')
        ->assertSee('Program Head')
        ->set('activeTab', 'department_head')
        ->assertSet('activeTab', 'department_head')
        ->assertSee('Department Head')
        ->set('activeTab', 'peer')
        ->assertSet('activeTab', 'peer')
        ->assertSee('Peer')
        ->set('activeTab', 'supervisor')
        ->assertSet('activeTab', 'supervisor')
        ->assertSee('Supervisor')
        ->set('activeTab', 'self')
        ->assertSet('activeTab', 'self')
        ->assertSee('Self');
});

test('completion tracking calculates peer evaluation progress accurately', function () {
    // Alan Turing evaluates Ada Lovelace as peer
    Evaluation::create([
        'semester_id' => $this->sem->id,
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facPeerUser->id,
        'evaluation_type' => 'peer',
        'rating_average' => 4.50,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::withoutLazyLoading()
        ->test('manage-evaluations')
        ->set('activeTab', 'peer')
        ->assertSee('Alan Turing')
        ->assertSee('1 / 1') // 1 submitted / 1 peer target
        ->assertSee('Completed');
});

test('send reminder toast broadcasts notification and logs activity', function () {
    $this->actingAs($this->adminUser);

    Livewire::withoutLazyLoading()
        ->test('manage-evaluations')
        ->call('sendReminderToast');

    $this->assertDatabaseHas('activity_log', [
        'causer_id' => $this->adminUser->id,
        'log_name' => 'evaluations',
    ]);
});
