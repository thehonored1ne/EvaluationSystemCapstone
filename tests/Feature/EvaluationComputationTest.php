<?php

use App\Jobs\ProcessEvaluationSubmission;
use App\Models\AcademicYear;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
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
});

test('semester computes dynamic category max points based on percentage weights', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'overall_max_points' => 200.00,
        'student_weight' => 30.00,
        'dean_weight' => 15.00,
        'ph_dh_weight' => 15.00,
        'peer_weight' => 15.00,
        'self_weight' => 5.00,
        'superior_weight' => 20.00,
    ]);

    expect($sem->getCategoryMaxPoints('student'))->toBe(60.00);
    expect($sem->getCategoryMaxPoints('dean'))->toBe(30.00);
    expect($sem->getCategoryMaxPoints('peer'))->toBe(30.00);
    expect($sem->getCategoryMaxPoints('self'))->toBe(10.00);
    expect($sem->getCategoryMaxPoints('superior'))->toBe(40.00);
});

test('process evaluation submission calculates raw_score, weighted_score and updates evaluation_summary', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'overall_max_points' => 200.00,
        'student_weight' => 30.00,
    ]);

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Part 1: Instructional Delivery',
        'order' => 1,
        'max_points' => 36.00,
    ]);

    $q1 = EvaluationQuestion::create(['criterion_id' => $criterion->id, 'question_text' => 'Q1', 'order' => 1, 'is_active' => true]);
    $q2 = EvaluationQuestion::create(['criterion_id' => $criterion->id, 'question_text' => 'Q2', 'order' => 2, 'is_active' => true]);

    $evaluatorEmp = Employee::create(['employee_number' => 'STU-99', 'first_name' => 'Student', 'last_name' => 'User', 'role' => 'student']);
    $evaluatorUser = User::create(['name' => 'Student User', 'email' => 'student99@example.com', 'employee_id' => $evaluatorEmp->id, 'password' => bcrypt('password')]);

    $evaluateeEmp = Employee::create(['employee_number' => 'FAC-99', 'first_name' => 'Faculty', 'last_name' => 'Prof', 'role' => 'faculty']);
    $evaluateeUser = User::create(['name' => 'Faculty Prof', 'email' => 'faculty99@example.com', 'employee_id' => $evaluateeEmp->id, 'password' => bcrypt('password')]);

    // Answers: Q1 = 5, Q2 = 5 -> (5/5)*(36/2) + (5/5)*(36/2) = 18 + 18 = 36 raw score
    $answers = [$q1->id => 5, $q2->id => 5];

    $job = new ProcessEvaluationSubmission(
        $evaluatorUser->id,
        $evaluateeUser->id,
        $sem->id,
        null,
        'student',
        $answers,
        'Great professor!'
    );

    $job->handle();

    $evaluation = Evaluation::where('evaluator_id', $evaluatorUser->id)->first();
    expect($evaluation)->not->toBeNull();
    expect((float) $evaluation->raw_score)->toBe(36.00);

    // Max score for student is 60.00 (30% of 200). Weighted score = (36 / 60) * 30% = 18.00
    expect((float) $evaluation->max_score)->toBe(60.00);
    expect((float) $evaluation->weighted_score)->toBe(18.00);

    $summary = EvaluationSummary::where('evaluatee_id', $evaluateeEmp->id)->where('semester_id', $sem->id)->first();
    expect($summary)->not->toBeNull();
    expect((float) $summary->student_score)->toBe(18.00);
    expect($summary->total_submissions)->toBe(1);
});
