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
    Livewire::withoutLazyLoading();
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

test('summary report renders academic department leaderboard for admin and dean', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Academic Department Rankings')
        ->assertSee('College of Computer Studies')
        ->assertSee('College of Business Administration');
});

test('summary report calculates institutional average and student average correctly', function () {
    // Student Evaluation for Faculty CCS
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.50,
    ]);

    // Student Evaluation for Faculty CBA
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCBA->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 3.50,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('4.00') // Institutional Average: (4.50 + 3.50) / 2 = 4.00
        ->assertSee('Scope: All Academic Departments');
});

test('summary report flags faculty requiring attention below benchmark', function () {
    // Evaluation with low score and pacing comment for Faculty CBA
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCBA->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 2.80,
        'comments' => 'Ang mabilis magturo ni sir, please slow down lecture pacing.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Faculty Requiring Pedagogical Attention')
        ->assertSee('Faculty2 CBA')
        ->assertSee('2.80')
        ->assertSee('Critical')
        ->assertSee('lecture pacing');
});

test('summary report displays prescriptive recommendations and rating spread', function () {
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.80,
        'comments' => 'Very approachable and clear discussion, highly organized.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Prescriptive AI Executive Insights')
        ->assertSee('Target Benchmark: 4.00')
        ->assertSee('Range:')
        ->assertSee('Across All Evaluations');
});

test('individual report renders without errors when selecting a professor', function () {
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.65,
        'comments' => 'Excellent teaching approach and active engagement.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->set('selectedTeacherId', $this->facCCS->id)
        ->assertSee('Summary of Faculty Performance Evaluation on Teaching Effectiveness')
        ->assertSee('Faculty1 CCS')
        ->assertSee('Global Reciprocal Colleges')
        ->assertSee('AI Qualitative Analysis')
        ->assertSee('Students Evaluation');
});
