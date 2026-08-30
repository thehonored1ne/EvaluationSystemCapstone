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

    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'department head']);
    Role::firstOrCreate(['name' => 'staff']);

    $this->ay = AcademicYear::create(['name' => '2027-2028', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $this->dept = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies', 'type' => 'academic']);
    $this->adminDept = Department::create(['code' => 'ADMIN', 'name' => 'Administrative Office', 'type' => 'administrative']);

    $this->adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    // Dean
    $this->deanEmp = Employee::create(['employee_number' => 'DEAN-01', 'first_name' => 'Dean', 'last_name' => 'One', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->dept->id]);
    $this->deanUser = User::create(['name' => 'Dean One', 'email' => 'dean@example.com', 'employee_id' => $this->deanEmp->id, 'password' => 'password']);
    $this->deanUser->assignRole('dean');

    // Program Head
    $this->phEmp = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'ProgHead', 'last_name' => 'One', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->dept->id]);
    $this->phUser = User::create(['name' => 'ProgHead One', 'email' => 'ph@example.com', 'employee_id' => $this->phEmp->id, 'password' => 'password']);
    $this->phUser->assignRole('program head');

    // Faculty
    $this->facEmp = Employee::create(['employee_number' => 'FAC-01', 'first_name' => 'Faculty', 'last_name' => 'One', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->dept->id]);
    $this->facUser = User::create(['name' => 'Faculty One', 'email' => 'fac@example.com', 'employee_id' => $this->facEmp->id, 'password' => 'password']);
    $this->facUser->assignRole('faculty');

    // Department Head
    $this->dhEmp = Employee::create(['employee_number' => 'DH-01', 'first_name' => 'DeptHead', 'last_name' => 'One', 'role' => 'department head', 'status' => 'active', 'department_id' => $this->adminDept->id]);
    $this->dhUser = User::create(['name' => 'DeptHead One', 'email' => 'dh@example.com', 'employee_id' => $this->dhEmp->id, 'password' => 'password']);
    $this->dhUser->assignRole('department head');

    // Staff
    $this->staffEmp = Employee::create(['employee_number' => 'STAFF-01', 'first_name' => 'Staff', 'last_name' => 'One', 'role' => 'staff', 'status' => 'active', 'department_id' => $this->adminDept->id]);
    $this->staffUser = User::create(['name' => 'Staff One', 'email' => 'staff@example.com', 'employee_id' => $this->staffEmp->id, 'password' => 'password']);
    $this->staffUser->assignRole('staff');
});

test('dean evaluating program head with dean evaluation_type decrements pending count and updates completion tracking', function () {
    // Before submission: Dean has pending self (1) + PH (1) = 2
    expect($this->deanUser->countPendingEvaluations($this->semester))->toBe(2);

    // Submit self evaluation
    Evaluation::create([
        'evaluator_id' => $this->deanUser->id,
        'evaluatee_id' => $this->deanUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.0,
    ]);

    // Submit dean evaluation for PH
    Evaluation::create([
        'evaluator_id' => $this->deanUser->id,
        'evaluatee_id' => $this->phUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'dean',
        'rating_average' => 4.8,
    ]);

    expect($this->deanUser->countPendingEvaluations($this->semester))->toBe(0);

    // Verify Dean Tracking in Manage Evaluations component
    $this->actingAs($this->adminUser);
    Livewire::test('manage-evaluations')
        ->set('activeTab', 'dean')
        ->assertSee('Dean One')
        ->assertSee('100%');
});

test('program head evaluating faculty with program_head evaluation_type decrements pending count and updates completion tracking', function () {
    // Before submission: PH has pending self (1) + Faculty (1) = 2
    expect($this->phUser->countPendingEvaluations($this->semester))->toBe(2);

    // Submit self
    Evaluation::create([
        'evaluator_id' => $this->phUser->id,
        'evaluatee_id' => $this->phUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.0,
    ]);

    // Submit program_head evaluation
    Evaluation::create([
        'evaluator_id' => $this->phUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'program_head',
        'rating_average' => 4.9,
    ]);

    expect($this->phUser->countPendingEvaluations($this->semester))->toBe(0);

    // Verify Program Head Tracking in Manage Evaluations
    $this->actingAs($this->adminUser);
    Livewire::test('manage-evaluations')
        ->set('activeTab', 'program_head')
        ->assertSee('ProgHead One')
        ->assertSee('100%');
});

test('department head evaluating staff with department_head evaluation_type decrements pending count and updates completion tracking', function () {
    // Before: DH has pending self (1) + staff (1) = 2
    expect($this->dhUser->countPendingEvaluations($this->semester))->toBe(2);

    // Submit self
    Evaluation::create([
        'evaluator_id' => $this->dhUser->id,
        'evaluatee_id' => $this->dhUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.0,
    ]);

    // Submit department_head evaluation
    Evaluation::create([
        'evaluator_id' => $this->dhUser->id,
        'evaluatee_id' => $this->staffUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'department_head',
        'rating_average' => 4.7,
    ]);

    expect($this->dhUser->countPendingEvaluations($this->semester))->toBe(0);

    // Verify Dept Head Tracking in Manage Evaluations
    $this->actingAs($this->adminUser);
    Livewire::test('manage-evaluations')
        ->set('activeTab', 'department_head')
        ->assertSee('DeptHead One')
        ->assertSee('100%');
});

test('faculty evaluating program head with upward_employee evaluation_type decrements pending count and updates completion tracking', function () {
    // In CCS dept: Faculty One has self (1) + PH One (1) = 2 pending (0 peers since only 1 faculty)
    expect($this->facUser->countPendingEvaluations($this->semester))->toBe(2);

    // Submit self
    Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.0,
    ]);

    // Submit upward_employee evaluation for Program Head
    Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->phUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'upward_employee',
        'rating_average' => 4.9,
    ]);

    expect($this->facUser->countPendingEvaluations($this->semester))->toBe(0);

    // Verify Professor Tracking in Manage Evaluations
    $this->actingAs($this->adminUser);
    Livewire::test('manage-evaluations')
        ->set('activeTab', 'professor')
        ->assertSee('Faculty One')
        ->assertSee('100%');
});
