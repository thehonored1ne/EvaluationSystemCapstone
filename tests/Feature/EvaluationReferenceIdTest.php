<?php

use App\Models\AcademicClass;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EvaluationReferenceService;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

test('it generates and verifies a tamper-proof 15-digit pure numeric reference ID', function () {
    $academicYear = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $semester = Semester::create([
        'academic_year_id' => $academicYear->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $userId = 412;
    $refId = EvaluationReferenceService::generate($userId, $semester->id);

    // 1. Assert purely numeric and 15 digits
    expect($refId)->toBeString()
        ->and(strlen($refId))->toBe(15)
        ->and(ctype_digit($refId))->toBeTrue();

    // 2. Assert starts with year (2025) and semester code (01)
    expect(substr($refId, 0, 6))->toBe('202501');

    // 3. Assert contains padded user ID (00412)
    expect(substr($refId, 6, 5))->toBe('00412');

    // 4. Assert formatting
    $formatted = EvaluationReferenceService::format($refId);
    expect($formatted)->toBe('2025-01-00412-'.substr($refId, 11, 4));

    // 5. Assert verification passes for authentic ID and fails for forged ID
    expect(EvaluationReferenceService::verify($refId, $userId, $semester->id))->toBeTrue();
    expect(EvaluationReferenceService::verify($formatted, $userId, $semester->id))->toBeTrue();

    // Forged ID with altered digits must fail verification
    $forgedRefId = substr($refId, 0, 11).'9999';
    expect(EvaluationReferenceService::verify($forgedRefId, $userId, $semester->id))->toBeFalse();
});

test('student dashboard renders proof card with numeric reference id when 100 percent evaluated', function () {
    Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'faculty', 'guard_name' => 'web']);

    Semester::query()->update(['is_active' => false]);
    AcademicYear::query()->update(['is_active' => false]);

    $academicYear = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $semester = Semester::create([
        'academic_year_id' => $academicYear->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDays(1),
        'evaluation_ends_at' => now()->addDays(5),
    ]);

    $dept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);
    $program = Program::create(['name' => 'BS Information Technology', 'code' => 'BSIT', 'department_id' => $dept->id]);
    $subject = Subject::create(['name' => 'Web Development', 'code' => 'IT101', 'units' => 3]);

    $student = Student::create([
        'student_number' => '2022-00412',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'program_id' => $program->id,
        'year_level' => 4,
        'section' => 'A',
        'status' => 'regular',
    ]);

    $studentUser = User::factory()->create([
        'name' => 'Juan Dela Cruz',
        'student_id' => $student->id,
    ]);
    $studentUser->assignRole('student');

    $teacherUser = User::factory()->create(['name' => 'Prof Smith']);
    $teacherUser->assignRole('faculty');
    $teacher = Employee::create([
        'user_id' => $teacherUser->id,
        'employee_number' => 'EMP-101',
        'first_name' => 'John',
        'last_name' => 'Smith',
        'role' => 'faculty',
        'department_id' => $dept->id,
        'status' => 'active',
    ]);
    $teacherUser->employee_id = $teacher->id;
    $teacherUser->save();

    $class = AcademicClass::create([
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'semester_id' => $semester->id,
        'section' => '4A',
    ]);

    $student->classes()->attach($class->id);

    // Initial state: 0/1 evaluated -> no proof card yet
    Volt::actingAs($studentUser->fresh())
        ->test('student.dashboard')
        ->assertSee('Student Evaluation Dashboard')
        ->assertDontSee('Proof of Evaluation Completion');

    // Create completed evaluation for the 1 class (100% completed)
    Evaluation::create([
        'evaluator_id' => $studentUser->id,
        'evaluatee_id' => $teacherUser->id,
        'semester_id' => $semester->id,
        'class_id' => $class->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.5,
        'rating_total' => 4.5,
        'evaluator_type' => 'student',
    ]);

    $expectedRefId = EvaluationReferenceService::generate($studentUser->id, $semester->id);
    $expectedFormatted = EvaluationReferenceService::format($expectedRefId);

    // After 100% completion: Proof of Evaluation Completion and Reference ID must appear
    Volt::actingAs($studentUser->fresh())
        ->test('student.dashboard')
        ->assertSee('Proof of Evaluation Completion')
        ->assertSee('100% Completed')
        ->assertSee($expectedFormatted)
        ->assertSee('Evaluation Reference ID');
});
