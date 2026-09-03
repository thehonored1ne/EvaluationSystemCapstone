<?php

use App\Models\AcademicClass;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin dashboard computes and renders semester comparison when prior term has records', function () {
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create([
        'name' => 'System Admin',
        'email' => 'admin@grc.edu.ph',
    ]);
    $admin->assignRole('admin');

    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);

    $sem1 = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Sem',
        'is_active' => false,
        'evaluation_starts_at' => now()->subMonths(6),
        'evaluation_ends_at' => now()->subMonths(5),
    ]);

    $sem2 = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '2nd Sem',
        'is_active' => true,
        'evaluation_starts_at' => now()->subDays(5),
        'evaluation_ends_at' => now()->addDays(5),
    ]);

    $dept = Department::create([
        'name' => 'College of Computer Studies',
        'code' => 'CCS',
        'type' => 'academic',
    ]);

    $program = Program::create([
        'department_id' => $dept->id,
        'name' => 'BS Information Technology',
        'code' => 'BSIT',
    ]);

    // Create teacher and subject
    $teacherEmp = Employee::create([
        'employee_number' => 'EMP-TEST-001',
        'first_name' => 'John',
        'last_name' => 'Teacher',
        'department_id' => $dept->id,
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $teacherUser = User::factory()->create(['employee_id' => $teacherEmp->id]);

    $subject = Subject::create([
        'code' => 'IT101',
        'name' => 'Intro to IT',
        'department_id' => $dept->id,
    ]);

    // Sem 1 class and evaluation
    $class1 = AcademicClass::create([
        'semester_id' => $sem1->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacherEmp->id,
        'section' => 'BSIT 1A',
    ]);

    $student1 = Student::create([
        'student_number' => '2025-0001',
        'first_name' => 'Alice',
        'last_name' => 'Student',
        'program_id' => $program->id,
        'year_level' => 1,
        'status' => 'regular',
    ]);
    $studentUser1 = User::factory()->create(['student_id' => $student1->id]);
    $class1->students()->attach($student1->id);

    // Create criterion and question for evaluation
    $crit = EvaluationCriterion::create([
        'name' => 'Teaching Effectiveness',
        'evaluation_type' => 'student_to_teacher',
    ]);
    $q = EvaluationQuestion::create([
        'criterion_id' => $crit->id,
        'question_text' => 'Demonstrates mastery of subject matter',
        'scale_type' => 'likert',
    ]);

    Evaluation::create([
        'evaluator_id' => $studentUser1->id,
        'evaluatee_id' => $teacherUser->id,
        'class_id' => $class1->id,
        'semester_id' => $sem1->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.5,
    ]);

    // Sem 2 class
    $class2 = AcademicClass::create([
        'semester_id' => $sem2->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacherEmp->id,
        'section' => 'BSIT 1A',
    ]);
    $class2->students()->attach($student1->id);

    Livewire::withoutLazyLoading()
        ->actingAs($admin)
        ->test('admin.dashboard')
        ->assertViewHas('hasPrevComparison', true)
        ->assertSee('Compare vs Last Sem');
});

test('admin dashboard hides comparison button when no prior semester records exist', function () {
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create([
        'name' => 'System Admin',
        'email' => 'admin2@grc.edu.ph',
    ]);
    $admin->assignRole('admin');

    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Sem',
        'is_active' => true,
    ]);

    Livewire::withoutLazyLoading()
        ->actingAs($admin)
        ->test('admin.dashboard')
        ->assertViewHas('hasPrevComparison', false);
});
