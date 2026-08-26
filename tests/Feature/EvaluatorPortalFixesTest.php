<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Program;
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
        'evaluation_starts_at' => now()->subDays(2),
        'evaluation_ends_at' => now()->addDays(10),
    ]);

    $this->academicDept = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies', 'type' => 'academic']);
    $this->adminDept = Department::create(['code' => 'ACCT', 'name' => 'Accounting Office', 'type' => 'administrative']);

    // Criteria Setup
    // 1. Dean (2 questions)
    $deanCriterion = EvaluationCriterion::create(['evaluation_type' => 'dean', 'name' => 'Dean Part 1', 'order' => 1, 'max_points' => 10]);
    EvaluationQuestion::create(['criterion_id' => $deanCriterion->id, 'question_text' => 'Dean Q1', 'order' => 1]);
    EvaluationQuestion::create(['criterion_id' => $deanCriterion->id, 'question_text' => 'Dean Q2', 'order' => 2]);

    // 2. Program Head (3 questions)
    $phCriterion = EvaluationCriterion::create(['evaluation_type' => 'program_head', 'name' => 'PH Part 1', 'order' => 1, 'max_points' => 10]);
    EvaluationQuestion::create(['criterion_id' => $phCriterion->id, 'question_text' => 'PH Q1', 'order' => 1]);
    EvaluationQuestion::create(['criterion_id' => $phCriterion->id, 'question_text' => 'PH Q2', 'order' => 2]);
    EvaluationQuestion::create(['criterion_id' => $phCriterion->id, 'question_text' => 'PH Q3', 'order' => 3]);

    // 3. Department Head (4 questions)
    $dhCriterion = EvaluationCriterion::create(['evaluation_type' => 'department_head', 'name' => 'DH Part 1', 'order' => 1, 'max_points' => 10]);
    EvaluationQuestion::create(['criterion_id' => $dhCriterion->id, 'question_text' => 'DH Q1', 'order' => 1]);
    EvaluationQuestion::create(['criterion_id' => $dhCriterion->id, 'question_text' => 'DH Q2', 'order' => 2]);
    EvaluationQuestion::create(['criterion_id' => $dhCriterion->id, 'question_text' => 'DH Q3', 'order' => 3]);
    EvaluationQuestion::create(['criterion_id' => $dhCriterion->id, 'question_text' => 'DH Q4', 'order' => 4]);

    // 4. Self (1 question)
    $selfCriterion = EvaluationCriterion::create(['evaluation_type' => 'self', 'name' => 'Self Part 1', 'order' => 1, 'max_points' => 10]);
    EvaluationQuestion::create(['criterion_id' => $selfCriterion->id, 'question_text' => 'Self Q1', 'order' => 1]);

    // Users
    // Dean with null department_id (college-wide)
    $this->deanEmp = Employee::create(['employee_number' => 'DEAN-01', 'first_name' => 'Dean', 'last_name' => 'Leader', 'role' => 'dean', 'status' => 'active', 'department_id' => null]);
    $this->deanUser = User::create(['name' => 'Dean Leader', 'email' => 'dean@example.com', 'employee_id' => $this->deanEmp->id, 'password' => 'password']);
    $this->deanUser->assignRole('dean');

    // Faculty in CCS
    $this->facEmp = Employee::create(['employee_number' => 'FAC-01', 'first_name' => 'John', 'last_name' => 'Professor', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->academicDept->id]);
    $this->facUser = User::create(['name' => 'John Professor', 'email' => 'john@example.com', 'employee_id' => $this->facEmp->id, 'password' => 'password']);
    $this->facUser->assignRole('faculty');

    // Staff in ACCT
    $this->staffEmp = Employee::create(['employee_number' => 'STF-01', 'first_name' => 'Jane', 'last_name' => 'Staff', 'role' => 'staff', 'status' => 'active', 'department_id' => $this->adminDept->id]);
    $this->staffUser = User::create(['name' => 'Jane Staff', 'email' => 'jane@example.com', 'employee_id' => $this->staffEmp->id, 'password' => 'password']);
    $this->staffUser->assignRole('staff');

    // Dept Head in ACCT
    $this->dhEmp = Employee::create(['employee_number' => 'DH-01', 'first_name' => 'Boss', 'last_name' => 'Head', 'role' => 'department head', 'status' => 'active', 'department_id' => $this->adminDept->id]);
    $this->dhUser = User::create(['name' => 'Boss Head', 'email' => 'boss@example.com', 'employee_id' => $this->dhEmp->id, 'password' => 'password']);
    $this->dhUser->assignRole('department head');

    // Program Head in CCS
    $this->phEmp = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'Prog', 'last_name' => 'Head', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->academicDept->id]);
    $this->phUser = User::create(['name' => 'Prog Head', 'email' => 'prog@example.com', 'employee_id' => $this->phEmp->id, 'password' => 'password']);
    $this->phUser->assignRole('program head');
});

test('evaluation form strictly loads dean criteria questions for dean evaluation type', function () {
    $this->actingAs($this->deanUser);

    $component = Livewire::test('evaluation-form', [
        'evaluatee' => $this->facUser,
        'evaluationType' => 'dean',
    ]);

    expect($component->get('questions'))->toHaveCount(2);
    expect($component->get('questions')[0]['question_text'])->toBe('Dean Q1');
});

test('evaluation form strictly loads program_head criteria questions for program_head evaluation type', function () {
    $this->actingAs($this->phUser);

    $component = Livewire::test('evaluation-form', [
        'evaluatee' => $this->facUser,
        'evaluationType' => 'program_head',
    ]);

    expect($component->get('questions'))->toHaveCount(3);
    expect($component->get('questions')[0]['question_text'])->toBe('PH Q1');
});

test('evaluation form strictly loads department_head criteria questions for department_head evaluation type', function () {
    $this->actingAs($this->dhUser);

    $component = Livewire::test('evaluation-form', [
        'evaluatee' => $this->staffUser,
        'evaluationType' => 'department_head',
    ]);

    expect($component->get('questions'))->toHaveCount(4);
    expect($component->get('questions')[0]['question_text'])->toBe('DH Q1');
});

test('dean with null department_id can see academic faculty members in reports', function () {
    $this->actingAs($this->deanUser);

    $component = Livewire::test('reports');

    expect($component->get('teachers'))->toHaveCount(2); // Dean and John Professor
    expect($component->get('teachers')->pluck('id'))->toContain($this->facEmp->id);
});

test('completion tracking is accessible only by admin and forbidden for dean and program head', function () {
    $admin = User::create(['name' => 'Admin User', 'email' => 'admin@admin.com', 'password' => 'password']);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/manage-evaluations')
        ->assertStatus(200);

    $this->actingAs($this->deanUser)
        ->get('/manage-evaluations')
        ->assertStatus(403);

    $this->actingAs($this->phUser)
        ->get('/manage-evaluations')
        ->assertStatus(403);
});
