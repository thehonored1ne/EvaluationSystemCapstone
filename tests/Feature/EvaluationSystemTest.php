<?php

use App\Jobs\ProcessEvaluationSubmission;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create academic context
    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDay(),
    ]);

    // Create Departments
    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);
    $this->coed = Department::create(['code' => 'COED', 'name' => 'College of Education']);

    // Create Programs
    $this->bscs = Program::create(['code' => 'BSCS', 'name' => 'Computer Science', 'department_id' => $this->ccs->id]);
    $this->bsed = Program::create(['code' => 'BSED', 'name' => 'Education', 'department_id' => $this->coed->id]);

    // Create Deans
    $this->deanEmp1 = Employee::create(['employee_number' => 'D-01', 'first_name' => 'Dean', 'last_name' => 'CCS', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->deanUser1 = User::create(['name' => 'Dean CCS', 'email' => 'dean.ccs@example.com', 'employee_id' => $this->deanEmp1->id, 'password' => 'password']);

    $this->ccs->update(['dean_id' => $this->deanEmp1->id]);

    // Create Program Heads
    $this->phEmp1 = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'Head', 'last_name' => 'BSCS', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->phUser1 = User::create(['name' => 'Head BSCS', 'email' => 'head.bscs@example.com', 'employee_id' => $this->phEmp1->id, 'password' => 'password']);

    $this->bscs->update(['program_head_id' => $this->phEmp1->id]);

    $this->phEmp2 = Employee::create(['employee_number' => 'PH-02', 'first_name' => 'Head', 'last_name' => 'BSED', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->coed->id]);
    $this->phUser2 = User::create(['name' => 'Head BSED', 'email' => 'head.bsed@example.com', 'employee_id' => $this->phEmp2->id, 'password' => 'password']);

    $this->bsed->update(['program_head_id' => $this->phEmp2->id]);

    // Create Faculty
    $this->facEmp1 = Employee::create(['employee_number' => 'F-01', 'first_name' => 'Fac', 'last_name' => 'CCS1', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUser1 = User::create(['name' => 'Fac CCS1', 'email' => 'fac1.ccs@example.com', 'employee_id' => $this->facEmp1->id, 'password' => 'password']);

    $this->facEmp2 = Employee::create(['employee_number' => 'F-02', 'first_name' => 'Fac', 'last_name' => 'CCS2', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUser2 = User::create(['name' => 'Fac CCS2', 'email' => 'fac2.ccs@example.com', 'employee_id' => $this->facEmp2->id, 'password' => 'password']);

    $this->facEmp3 = Employee::create(['employee_number' => 'F-03', 'first_name' => 'Fac', 'last_name' => 'COED1', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->coed->id]);
    $this->facUser3 = User::create(['name' => 'Fac COED1', 'email' => 'fac1.coed@example.com', 'employee_id' => $this->facEmp3->id, 'password' => 'password']);

    // Create Staff
    $this->staffEmp1 = Employee::create(['employee_number' => 'S-01', 'first_name' => 'Staff', 'last_name' => 'CCS', 'role' => 'staff', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->staffUser1 = User::create(['name' => 'Staff CCS', 'email' => 'staff.ccs@example.com', 'employee_id' => $this->staffEmp1->id, 'password' => 'password']);

    // Create Criteria and Questions
    $this->criterion = EvaluationCriterion::create(['evaluation_type' => 'peer', 'name' => 'Teaching Delivery', 'order' => 1, 'max_points' => 50.00]);
    $this->q1 = EvaluationQuestion::create(['criterion_id' => $this->criterion->id, 'question_text' => 'Question 1', 'order' => 1]);
    $this->q2 = EvaluationQuestion::create(['criterion_id' => $this->criterion->id, 'question_text' => 'Question 2', 'order' => 2]);
});

test('process evaluation submission job is idempotent and calculates rating average', function () {
    $answers = [
        $this->q1->id => 4, // rating 4
        $this->q2->id => 5, // rating 5
    ];

    // Dispatch the job synchronously to execute it
    $job = new ProcessEvaluationSubmission(
        $this->facUser1->id,
        $this->facUser2->id,
        $this->semester->id,
        null,
        'peer',
        $answers,
        'Great job!'
    );
    $job->handle();

    // Verify evaluation record was created
    $this->assertDatabaseHas('evaluations', [
        'evaluator_id' => $this->facUser1->id,
        'evaluatee_id' => $this->facUser2->id,
        'semester_id' => $this->semester->id,
        'class_id' => null,
        'evaluation_type' => 'peer',
        'rating_average' => 4.50, // (4 + 5) / 2
        'comments' => 'Great job!',
    ]);

    // Verify answers were created
    $eval = Evaluation::first();
    $this->assertDatabaseHas('evaluation_answers', [
        'evaluation_id' => $eval->id,
        'question_id' => $this->q1->id,
        'rating' => 4,
    ]);
    $this->assertDatabaseHas('evaluation_answers', [
        'evaluation_id' => $eval->id,
        'question_id' => $this->q2->id,
        'rating' => 5,
    ]);

    // Dispatch the exact same job again to test idempotency
    $job->handle();

    // Check count of evaluations (should still be exactly 1, not 2)
    expect(Evaluation::count())->toBe(1);
    expect(EvaluationAnswer::count())->toBe(2);
});

test('evaluation form comment filters out curse words', function () {
    $this->actingAs($this->facUser1);

    Livewire::test('evaluation-form', [
        'evaluatee' => $this->facUser2,
        'evaluationType' => 'peer',
    ])
        ->set('comments', 'This is a gago and bbaliw class.')
        ->assertSet('comments', 'This is a and b class.');
});

test('evaluation form requires comments before submission', function () {
    $this->actingAs($this->facUser1);

    Livewire::test('evaluation-form', [
        'evaluatee' => $this->facUser2,
        'evaluationType' => 'peer',
    ])
        ->set("ratings.{$this->q1->id}", 5)
        ->set("ratings.{$this->q2->id}", 5)
        ->set('comments', '')
        ->call('submit')
        ->assertHasErrors(['comments' => 'required']);
});

test('faculty targeting logic finds peers in same department only', function () {
    $this->actingAs($this->facUser1);

    // Faculty 1 (CCS) peers should include Faculty 2 (CCS) but NOT Faculty 3 (COED) and NOT Faculty 1 (self)
    $peers = Employee::where('role', 'faculty')
        ->where('department_id', $this->facEmp1->department_id)
        ->where('id', '!=', $this->facEmp1->id)
        ->pluck('id');

    expect($peers)->toContain($this->facEmp2->id);
    expect($peers)->not->toContain($this->facEmp3->id);
    expect($peers)->not->toContain($this->facEmp1->id);
});

test('faculty supervisor logic finds program heads in same department only', function () {
    // Faculty 1 (CCS) supervisors should include PH 1 (CCS) but NOT PH 2 (COED)
    $heads = Employee::where('role', 'program head')
        ->where('department_id', $this->facEmp1->department_id)
        ->pluck('id');

    expect($heads)->toContain($this->phEmp1->id);
    expect($heads)->not->toContain($this->phEmp2->id);
});

test('program head subordinate logic finds faculty in same department only', function () {
    // PH 1 (CCS) subordinate faculty should include Faculty 1 and 2 (CCS) but NOT Faculty 3 (COED)
    $faculty = Employee::where('role', 'faculty')
        ->where('department_id', $this->phEmp1->department_id)
        ->pluck('id');

    expect($faculty)->toContain($this->facEmp1->id, $this->facEmp2->id);
    expect($faculty)->not->toContain($this->facEmp3->id);
});

test('program head supervisor logic finds dean of their department', function () {
    // PH 1 (CCS) department dean is Dean 1
    $dept = Department::find($this->phEmp1->department_id);
    expect($dept->dean_id)->toBe($this->deanEmp1->id);
});

test('staff supervisor logic finds program heads in same department only', function () {
    // Staff 1 (CCS) supervisors should include PH 1 (CCS) but NOT PH 2 (COED)
    $heads = Employee::where('role', 'program head')
        ->where('department_id', $this->staffEmp1->department_id)
        ->pluck('id');

    expect($heads)->toContain($this->phEmp1->id);
    expect($heads)->not->toContain($this->phEmp2->id);
});
