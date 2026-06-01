<?php

use App\Models\User;
use App\Models\Employee;
use App\Models\Student;
use App\Models\Department;
use App\Models\Program;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\Evaluation;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Jobs\ProcessEvaluationSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set queue to database configuration
    config(['queue.default' => 'database']);

    // Create academic context
    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    // Create Department
    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);

    // Create Program
    $this->bscs = Program::create(['code' => 'BSCS', 'name' => 'Computer Science', 'department_id' => $this->ccs->id]);

    // Create Dean
    $this->deanEmp = Employee::create(['employee_number' => 'D-01', 'first_name' => 'Dean', 'last_name' => 'CCS', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->deanUser = User::create(['name' => 'Dean CCS', 'email' => 'dean.ccs@example.com', 'employee_id' => $this->deanEmp->id, 'password' => 'password']);
    $this->ccs->update(['dean_id' => $this->deanEmp->id]);

    // Create Program Head
    $this->phEmp = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'Head', 'last_name' => 'BSCS', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->phUser = User::create(['name' => 'Head BSCS', 'email' => 'head.bscs@example.com', 'employee_id' => $this->phEmp->id, 'password' => 'password']);
    $this->bscs->update(['program_head_id' => $this->phEmp->id]);

    // Create Faculty
    $this->facEmp1 = Employee::create(['employee_number' => 'F-01', 'first_name' => 'Fac', 'last_name' => 'CCS1', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUser1 = User::create(['name' => 'Fac CCS1', 'email' => 'fac1.ccs@example.com', 'employee_id' => $this->facEmp1->id, 'password' => 'password']);

    $this->facEmp2 = Employee::create(['employee_number' => 'F-02', 'first_name' => 'Fac', 'last_name' => 'CCS2', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUser2 = User::create(['name' => 'Fac CCS2', 'email' => 'fac2.ccs@example.com', 'employee_id' => $this->facEmp2->id, 'password' => 'password']);

    // Create Staff
    $this->staffEmp = Employee::create(['employee_number' => 'S-01', 'first_name' => 'Staff', 'last_name' => 'CCS', 'role' => 'staff', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->staffUser = User::create(['name' => 'Staff CCS', 'email' => 'staff.ccs@example.com', 'employee_id' => $this->staffEmp->id, 'password' => 'password']);

    // Create Student
    $this->studentEmp = Student::create(['student_number' => 'ST-01', 'first_name' => 'Student', 'last_name' => 'One', 'status' => 'active', 'program_id' => $this->bscs->id]);
    $this->studentUser = User::create(['name' => 'Student One', 'email' => 'student.one@example.com', 'student_id' => $this->studentEmp->id, 'password' => 'password']);

    // Create Academic Class
    $this->subject = Subject::create(['code' => 'CS101', 'name' => 'Intro to CS']);
    $this->class = AcademicClass::create([
        'semester_id' => $this->semester->id,
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->facEmp1->id,
        'section' => 'A',
        'schedule' => 'MWF',
    ]);
    $this->class->students()->attach($this->studentEmp->id);
});

test('Evaluation getStatus correctly retrieves processing status for database queue jobs', function () {
    $evaluatorId = $this->facUser1->id;
    $evaluateeId = $this->facUser2->id;
    $semesterId = $this->semester->id;
    $classId = null;
    $type = 'peer';

    // Verify it is pending initially
    expect(Evaluation::getStatus($evaluatorId, $evaluateeId, $semesterId, $classId, $type))->toBe('pending');

    // Construct serialized job payload matching ProcessEvaluationSubmission
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\ProcessEvaluationSubmission',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => null,
        'delay' => null,
        'timeout' => null,
        'timeoutAt' => null,
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessEvaluationSubmission',
            'command' => serialize(new ProcessEvaluationSubmission(
                $evaluatorId,
                $evaluateeId,
                $semesterId,
                $classId,
                $type,
                []
            ))
        ]
    ]);

    // Insert into database jobs table
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    // Assert status is now processing
    expect(Evaluation::getStatus($evaluatorId, $evaluateeId, $semesterId, $classId, $type))->toBe('processing');

    // Assert different parameters do not return processing (loose checking isolation)
    expect(Evaluation::getStatus($evaluatorId, $evaluateeId, $semesterId + 1, $classId, $type))->toBe('pending');
});

test('Student dashboard blocks selectClass when evaluation is processing in queue', function () {
    $evaluatorId = $this->studentUser->id;
    $evaluateeId = $this->facUser1->id;
    $semesterId = $this->semester->id;
    $classId = $this->class->id;
    $type = 'student';

    // Set up queue job
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\ProcessEvaluationSubmission',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessEvaluationSubmission',
            'command' => serialize(new ProcessEvaluationSubmission(
                $evaluatorId,
                $evaluateeId,
                $semesterId,
                $classId,
                $type,
                []
            ))
        ]
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    // Test component blocks and flashes error
    $component = Livewire::actingAs($this->studentUser)
        ->test('student.dashboard')
        ->call('selectClass', $classId, $evaluateeId)
        ->assertSee('This evaluation is already processing or completed.');

    expect($component->get('showForm'))->toBeFalse();
});

test('Faculty dashboard blocks selectTarget when evaluation is processing in queue', function () {
    $evaluatorId = $this->facUser1->id;
    $evaluateeId = $this->facUser1->id; // self evaluation
    $semesterId = $this->semester->id;
    $classId = null;
    $type = 'self';

    // Set up queue job
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\ProcessEvaluationSubmission',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessEvaluationSubmission',
            'command' => serialize(new ProcessEvaluationSubmission(
                $evaluatorId,
                $evaluateeId,
                $semesterId,
                $classId,
                $type,
                []
            ))
        ]
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    $component = Livewire::actingAs($this->facUser1)
        ->test('faculty.dashboard')
        ->call('selectTarget', $evaluateeId, $type)
        ->assertSee('This evaluation is already processing or completed.');

    expect($component->get('showForm'))->toBeFalse();
});

test('Dean dashboard blocks selectTarget when evaluation is processing in queue', function () {
    $evaluatorId = $this->deanUser->id;
    $evaluateeId = $this->phUser->id; // Program head
    $semesterId = $this->semester->id;
    $classId = null;
    $type = 'peer';

    // Set up queue job
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\ProcessEvaluationSubmission',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessEvaluationSubmission',
            'command' => serialize(new ProcessEvaluationSubmission(
                $evaluatorId,
                $evaluateeId,
                $semesterId,
                $classId,
                $type,
                []
            ))
        ]
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    $component = Livewire::actingAs($this->deanUser)
        ->test('dean.dashboard')
        ->call('selectTarget', $evaluateeId, $type)
        ->assertSee('This evaluation is already processing or completed.');

    expect($component->get('showForm'))->toBeFalse();
});

test('Staff dashboard blocks selectTarget when evaluation is processing in queue', function () {
    $evaluatorId = $this->staffUser->id;
    $evaluateeId = $this->phUser->id; // Program head
    $semesterId = $this->semester->id;
    $classId = null;
    $type = 'peer';

    // Set up queue job
    $payload = json_encode([
        'displayName' => 'App\\Jobs\\ProcessEvaluationSubmission',
        'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'data' => [
            'commandName' => 'App\\Jobs\\ProcessEvaluationSubmission',
            'command' => serialize(new ProcessEvaluationSubmission(
                $evaluatorId,
                $evaluateeId,
                $semesterId,
                $classId,
                $type,
                []
            ))
        ]
    ]);

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => time(),
        'created_at' => time(),
    ]);

    $component = Livewire::actingAs($this->staffUser)
        ->test('staff.dashboard')
        ->call('selectTarget', $evaluateeId, $type)
        ->assertSee('This evaluation is already processing or completed.');

    expect($component->get('showForm'))->toBeFalse();
});
