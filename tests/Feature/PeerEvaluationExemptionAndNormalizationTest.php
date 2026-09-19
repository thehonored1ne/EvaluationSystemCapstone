<?php

use App\Jobs\ProcessEvaluationSubmission;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationExemption;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSummary;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'student']);
    Evaluation::flushStatusCache();
});

test('evaluation exemption can be recorded and prevents duplicate entries', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDays(7),
    ]);

    $emp1 = Employee::create(['employee_number' => 'FAC-E1', 'first_name' => 'Alice', 'last_name' => 'Prof', 'role' => 'faculty']);
    $user1 = User::create(['name' => 'Alice Prof', 'email' => 'alice@example.com', 'employee_id' => $emp1->id, 'password' => bcrypt('password')]);
    $user1->assignRole('faculty');

    $emp2 = Employee::create(['employee_number' => 'FAC-E2', 'first_name' => 'Bob', 'last_name' => 'Prof', 'role' => 'faculty']);
    $user2 = User::create(['name' => 'Bob Prof', 'email' => 'bob@example.com', 'employee_id' => $emp2->id, 'password' => bcrypt('password')]);
    $user2->assignRole('faculty');

    $exemption = EvaluationExemption::create([
        'evaluator_id' => $user1->id,
        'evaluatee_id' => $user2->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'peer',
        'reason' => 'schedule_conflict',
        'notes' => 'Different teaching shifts, zero overlap.',
    ]);

    expect($exemption->id)->not->toBeNull();
    expect($exemption->reason)->toBe('schedule_conflict');

    // Verification of unique constraint / updateOrCreate behavior
    $updated = EvaluationExemption::updateOrCreate(
        [
            'evaluator_id' => $user1->id,
            'evaluatee_id' => $user2->id,
            'semester_id' => $sem->id,
            'evaluation_type' => 'peer',
        ],
        [
            'reason' => 'new_faculty',
            'notes' => 'Updated audit reason.',
        ]
    );

    expect(EvaluationExemption::count())->toBe(1);
    expect($updated->reason)->toBe('new_faculty');
});

test('evaluation getStatus returns exempted when exemption exists', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $user1 = User::create(['name' => 'Evaluator', 'email' => 'evaluator@example.com', 'password' => bcrypt('password')]);
    $user2 = User::create(['name' => 'Evaluatee', 'email' => 'evaluatee@example.com', 'password' => bcrypt('password')]);

    expect(Evaluation::getStatus($user1->id, $user2->id, $sem->id, null, 'peer'))->toBe('pending');

    EvaluationExemption::create([
        'evaluator_id' => $user1->id,
        'evaluatee_id' => $user2->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'peer',
        'reason' => 'different_specialization',
    ]);

    Evaluation::flushStatusCache();

    expect(Evaluation::getStatus($user1->id, $user2->id, $sem->id, null, 'peer'))->toBe('exempted');
});

test('user pending evaluations exclude exempted peers from notifications and counts', function () {
    $dept = Department::create(['name' => 'Computer Studies', 'code' => 'CS', 'type' => 'academic']);

    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDays(7),
    ]);

    $emp1 = Employee::create(['employee_number' => 'FAC-01', 'first_name' => 'Alpha', 'last_name' => 'One', 'role' => 'faculty', 'department_id' => $dept->id, 'status' => 'active']);
    $user1 = User::create(['name' => 'Alpha One', 'email' => 'alpha@example.com', 'employee_id' => $emp1->id, 'password' => bcrypt('password')]);
    $user1->assignRole('faculty');

    $emp2 = Employee::create(['employee_number' => 'FAC-02', 'first_name' => 'Beta', 'last_name' => 'Two', 'role' => 'faculty', 'department_id' => $dept->id, 'status' => 'active']);
    $user2 = User::create(['name' => 'Beta Two', 'email' => 'beta@example.com', 'employee_id' => $emp2->id, 'password' => bcrypt('password')]);
    $user2->assignRole('faculty');

    // Initially, peer Beta is pending for user Alpha
    $pendingBefore = $user1->countPendingEvaluations($sem);

    // Create an exemption for peer Beta
    EvaluationExemption::create([
        'evaluator_id' => $user1->id,
        'evaluatee_id' => $user2->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'peer',
        'reason' => 'schedule_conflict',
    ]);

    $pendingAfter = $user1->countPendingEvaluations($sem);

    expect($pendingAfter)->toBe($pendingBefore - 1);
});

test('process evaluation submission applies dynamic weight normalization when peer evaluation is absent', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'overall_max_points' => 200.00,
        'student_weight' => 40.00,
        'dean_weight' => 20.00,
        'ph_dh_weight' => 15.00,
        'peer_weight' => 20.00,
        'self_weight' => 5.00,
    ]);

    $critStudent = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Instructional Competence',
        'order' => 1,
        'max_points' => 80.00,
    ]);
    $qStudent = EvaluationQuestion::create(['criterion_id' => $critStudent->id, 'question_text' => 'Q1', 'order' => 1, 'is_active' => true]);

    $evaluatorEmp = Employee::create(['employee_number' => 'STU-01', 'first_name' => 'Student', 'last_name' => 'User', 'role' => 'student']);
    $evaluatorUser = User::create(['name' => 'Student User', 'email' => 'student01@example.com', 'employee_id' => $evaluatorEmp->id, 'password' => bcrypt('password')]);

    $evaluateeEmp = Employee::create(['employee_number' => 'FAC-PRO', 'first_name' => 'Jane', 'last_name' => 'Doe', 'role' => 'faculty']);
    $evaluateeUser = User::create(['name' => 'Jane Doe', 'email' => 'janedoe@example.com', 'employee_id' => $evaluateeEmp->id, 'password' => bcrypt('password')]);
    $evaluateeUser->assignRole('faculty');

    // Perfect student rating 5/5
    $answers = [$qStudent->id => 5];

    $job = new ProcessEvaluationSubmission(
        $evaluatorUser->id,
        $evaluateeUser->id,
        $sem->id,
        null,
        'student',
        $answers,
        'Exemplary faculty teaching.'
    );
    $job->handle();

    $summary = EvaluationSummary::where('evaluatee_id', $evaluateeEmp->id)
        ->where('semester_id', $sem->id)
        ->first();

    expect($summary)->not->toBeNull();
    // Raw student score is 40.00. Since Peer (20%) is absent, normalization scales:
    // Active weight sum = 40.00 (student). Expected total = 40 + 20 = 60.
    // Normalized score = 40 * (60 / 40) = 60.00.
    expect($summary->is_peer_exempted)->toBeTrue();
    expect((float) $summary->overall_rating)->toBe(60.00);
});

test('faculty report applies dynamic weight normalization preserving 200 pt scale when peer reviews are absent', function () {
    $dept = Department::create(['name' => 'Business Administration', 'code' => 'BA', 'type' => 'academic']);

    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'overall_max_points' => 200.00,
        'student_weight' => 40.00,
        'peer_weight' => 15.00,
    ]);

    $emp = Employee::create(['employee_number' => 'FAC-REP', 'first_name' => 'Robert', 'last_name' => 'Frost', 'role' => 'faculty', 'department_id' => $dept->id, 'status' => 'active']);
    $user = User::create(['name' => 'Robert Frost', 'email' => 'rfrost@example.com', 'employee_id' => $emp->id, 'password' => bcrypt('password')]);
    $user->assignRole('faculty');

    // Fake an evaluation submission for student
    Evaluation::create([
        'evaluator_id' => $user->id,
        'evaluatee_id' => $user->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.00,
        'raw_score' => 10.00,
        'max_score' => 10.00,
        'weighted_score' => 10.00,
        'comments' => 'Self evaluation completed',
    ]);

    $adminEmp = Employee::create(['employee_number' => 'ADM-01', 'first_name' => 'Admin', 'last_name' => 'User', 'role' => 'admin']);
    $adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'employee_id' => $adminEmp->id, 'password' => bcrypt('password')]);
    $adminUser->assignRole('admin');

    $this->actingAs($adminUser);

    $reportsComponent = Livewire::test('reports');
    $reportData = $reportsComponent->instance()->getReportDataForTeacher($emp, $sem);

    expect($reportData)->not->toBeNull();
    // Since peer reviews count is 0, is_peer_exempted should be true
    expect($reportData->is_peer_exempted)->toBeTrue();
    expect($reportData->normalization_scale)->toBeGreaterThan(1.0);
});
