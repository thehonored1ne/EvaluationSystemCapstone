<?php

use App\Models\User;
use App\Models\Employee;
use App\Models\Student;
use App\Models\Department;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use App\Models\Subject;
use App\Models\AcademicClass;
use App\Jobs\ProcessEvaluationSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Roles
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'faculty']);

    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $this->student = Student::create(['student_number' => 'STU-1', 'first_name' => 'Student', 'last_name' => 'One']);
    $this->studentUser = User::create(['name' => 'Student One', 'email' => 'student@example.com', 'student_id' => $this->student->id, 'password' => 'password']);
    
    $this->dept = Department::create(['code' => 'CCS', 'name' => 'Computer Studies']);
    $this->facEmp = Employee::create(['employee_number' => 'FAC-1', 'first_name' => 'John', 'last_name' => 'Doe', 'role' => 'faculty', 'department_id' => $this->dept->id]);
    $this->facUser = User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'employee_id' => $this->facEmp->id, 'password' => 'password']);

    $this->subject = Subject::create(['code' => 'CS101', 'name' => 'Intro to CS']);
    $this->class = AcademicClass::create([
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->facEmp->id,
        'section' => 'A',
    ]);

    $this->criterion = \App\Models\EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Teaching Quality',
        'max_points' => 90,
        'order' => 1
    ]);

    $this->question = \App\Models\EvaluationQuestion::create([
        'criterion_id' => $this->criterion->id,
        'question_text' => 'Does the teacher explain concepts clearly?',
        'order' => 1,
        'is_active' => true,
    ]);
});

test('ProcessEvaluationSubmission job calls Flask analyze API and records sentiment', function () {
    Http::fake([
        'http://127.0.0.1:5001/analyze' => Http::response([
            'comment' => 'Magaling magturo si sir.',
            'vader_score' => 0.85,
            'vader_label' => 'positive',
            'dt_label' => 'positive',
        ], 200),
    ]);

    $job = new ProcessEvaluationSubmission(
        $this->studentUser->id,
        $this->facUser->id,
        $this->semester->id,
        $this->class->id,
        'student',
        [$this->question->id => 5],
        'Magaling magturo si sir.'
    );

    $job->handle();

    // Verify evaluation is created
    $evaluation = Evaluation::where('evaluator_id', $this->studentUser->id)->first();
    expect($evaluation)->not->toBeNull();

    // Verify sentiment record is created
    $sentiment = EvaluationSentiment::where('evaluation_id', $evaluation->id)->first();
    expect($sentiment)->not->toBeNull();
    expect($sentiment->vader_score)->toBe(0.85);
    expect($sentiment->vader_label)->toBe('positive');
    expect($sentiment->dt_label)->toBe('positive');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://127.0.0.1:5001/analyze' &&
               $request->hasHeader('X-API-KEY', config('services.ai.key')) &&
               $request['comment'] === 'Magaling magturo si sir.';
    });
});

test('ProcessEvaluationSubmission job survives Flask API failure', function () {
    // Fake a connection error or 500 error
    Http::fake([
        'http://127.0.0.1:5001/analyze' => Http::response('Error', 500),
    ]);

    $job = new ProcessEvaluationSubmission(
        $this->studentUser->id,
        $this->facUser->id,
        $this->semester->id,
        $this->class->id,
        'student',
        [$this->question->id => 5],
        'Failure comment'
    );

    // Should run without throwing exception
    $job->handle();

    // Verify evaluation is still created
    $evaluation = Evaluation::where('evaluator_id', $this->studentUser->id)->first();
    expect($evaluation)->not->toBeNull();

    // Verify no sentiment record is created
    $sentiment = EvaluationSentiment::where('evaluation_id', $evaluation->id)->first();
    expect($sentiment)->toBeNull();
});

test('ai:train command triggers training and backfills missing sentiments', function () {
    // Create an evaluation with comment but no sentiment record
    $evaluation = Evaluation::create([
        'evaluator_id' => $this->studentUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'class_id' => $this->class->id,
        'evaluation_type' => 'student',
        'rating_average' => 5.0,
        'comments' => 'Magaling magturo.',
    ]);

    Http::fake([
        'http://127.0.0.1:5001/train' => Http::response([
            'status' => 'success',
            'samples_trained' => 23,
            'db_samples' => 1,
            'seed_samples' => 22,
        ], 200),
        'http://127.0.0.1:5001/analyze' => Http::response([
            'comment' => 'Magaling magturo.',
            'vader_score' => 0.75,
            'vader_label' => 'positive',
            'dt_label' => 'positive',
        ], 200),
    ]);

    $this->artisan('ai:train')
        ->expectsOutput('Fetching comments from the database...')
        ->expectsOutput('Sending 1 database comments to Flask API `/train` endpoint...')
        ->expectsOutput('AI training completed successfully!')
        ->expectsOutput('Checking for unanalyzed comments to backfill...')
        ->expectsOutput('Analyzing 1 comments...')
        ->assertExitCode(0);

    // Verify backfill occurred
    $sentiment = EvaluationSentiment::where('evaluation_id', $evaluation->id)->first();
    expect($sentiment)->not->toBeNull();
    expect($sentiment->vader_score)->toBe(0.75);
    expect($sentiment->vader_label)->toBe('positive');
});
