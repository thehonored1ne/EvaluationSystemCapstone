<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Livewire\Livewire::withoutLazyLoading();
    // Create roles
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'student']);

    // Create academic context
    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDay(),
        'upward_student_max_points' => 5.0,
        'upward_employee_max_points' => 5.0,
        'downward_max_points' => 5.0,
        'peer_max_points' => 5.0,
        'self_max_points' => 5.0,
    ]);

    // Create Departments
    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);
    $this->cba = Department::create(['code' => 'CBA', 'name' => 'College of Business Administration']);

    // Admin user
    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    // Dean CCS
    $this->deanCCS = Employee::create(['employee_number' => 'D-01', 'first_name' => 'Dean', 'last_name' => 'CCS', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->deanUser = User::create(['name' => 'Dean CCS', 'email' => 'dean@example.com', 'employee_id' => $this->deanCCS->id, 'password' => 'password']);
    $this->deanUser->assignRole('dean');

    // Faculty CCS
    $this->facCCS = Employee::create(['employee_number' => 'F-01', 'first_name' => 'Faculty1', 'last_name' => 'CCS', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUserCCS = User::create(['name' => 'Faculty1 CCS', 'email' => 'fac1@example.com', 'employee_id' => $this->facCCS->id, 'password' => 'password']);
    $this->facUserCCS->assignRole('faculty');

    // Faculty CBA
    $this->facCBA = Employee::create(['employee_number' => 'F-02', 'first_name' => 'Faculty2', 'last_name' => 'CBA', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->cba->id]);
    $this->facUserCBA = User::create(['name' => 'Faculty2 CBA', 'email' => 'fac2@example.com', 'employee_id' => $this->facCBA->id, 'password' => 'password']);
    $this->facUserCBA->assignRole('faculty');
});

test('reports page contains reports Livewire component', function () {
    $this->actingAs($this->adminUser);
    $response = $this->get('/reports');
    $response->assertStatus(200);
    $response->assertSeeLivewire('reports');
});

test('reports component defaults to individual tab and switches to summary tab', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->assertSet('activeTab', 'individual')
        ->set('activeTab', 'summary')
        ->assertSet('activeTab', 'summary');
});

test('dean summary report only includes department faculty', function () {
    $this->actingAs($this->deanUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Faculty1 CCS')
        ->assertDontSee('Faculty2 CBA');
});

test('admin summary report includes all departments', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Faculty1 CCS')
        ->assertSee('Faculty2 CBA');
});

test('summary report calculates average scores correctly', function () {
    // Create evaluations for Faculty CCS
    // Student Evaluation
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.50,
    ]);

    // Peer Evaluation
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'peer',
        'rating_average' => 3.50,
    ]);

    // Self Evaluation
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->facUserCCS->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.00,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('4.50')
        ->assertSee('3.50')
        ->assertSee('5.00')
        ->assertSee('4.33'); // overall average (4.50 + 3.50 + 5.00) / 3 = 4.33
});
