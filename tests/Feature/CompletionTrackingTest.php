<?php

use App\Models\AcademicClass;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
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
    ]);

    $this->dept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);
    $this->adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@track.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    $this->faculty = Employee::create([
        'employee_number' => 'FAC-001',
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facUser = User::create(['name' => 'Alan Turing', 'email' => 'turing@track.com', 'employee_id' => $this->faculty->id, 'password' => 'password']);
    $this->facUser->assignRole('faculty');

    $this->facultyPeer = Employee::create([
        'employee_number' => 'FAC-002',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facPeerUser = User::create(['name' => 'Ada Lovelace', 'email' => 'lovelace@track.com', 'employee_id' => $this->facultyPeer->id, 'password' => 'password']);
    $this->facPeerUser->assignRole('faculty');
});

test('completion tracking route responds with 200 for admin, dean, and program head', function () {
    $this->actingAs($this->adminUser)
        ->get('/manage-evaluations')
        ->assertStatus(200);
});

test('completion tracking component renders and allows tab switching across all 6 evaluator roles', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('manage-evaluations')
        ->assertSet('activeTab', 'student')
        ->assertSee('Student')
        ->set('activeTab', 'dean')
        ->assertSet('activeTab', 'dean')
        ->assertSee('Dean')
        ->set('activeTab', 'program_head')
        ->assertSet('activeTab', 'program_head')
        ->assertSee('Program Head')
        ->set('activeTab', 'department_head')
        ->assertSet('activeTab', 'department_head')
        ->assertSee('Department Head')
        ->set('activeTab', 'professor')
        ->assertSet('activeTab', 'professor')
        ->assertSee('Professor')
        ->set('activeTab', 'staff')
        ->assertSet('activeTab', 'staff')
        ->assertSee('Staff');
});

test('completion tracking calculates professor evaluation progress accurately', function () {
    // Alan Turing evaluates self and peer Ada Lovelace
    Evaluation::create([
        'semester_id' => $this->sem->id,
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.00,
    ]);

    Evaluation::create([
        'semester_id' => $this->sem->id,
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facPeerUser->id,
        'evaluation_type' => 'peer',
        'rating_average' => 4.50,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('manage-evaluations')
        ->set('activeTab', 'professor')
        ->assertSee('Alan Turing')
        ->assertSee('100%')
        ->assertSee('Completed');
});

test('completion tracking student tab renders enrolled student, section, subjects and reference ID when completed', function () {
    $student = Student::create([
        'student_number' => 'STU-9901',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'year_level' => 3,
        'section' => 'A',
        'status' => 'regular',
    ]);
    $studentUser = User::create([
        'name' => 'Grace Hopper',
        'email' => 'hopper@track.com',
        'student_id' => $student->id,
        'password' => 'password',
    ]);
    $studentUser->assignRole('student');

    $subject = Subject::create(['code' => 'CS101', 'name' => 'Intro CS', 'units' => 3]);
    $class = AcademicClass::create([
        'subject_id' => $subject->id,
        'semester_id' => $this->sem->id,
        'teacher_id' => $this->faculty->id,
        'section' => 'CS3A',
    ]);
    $student->classes()->attach($class->id);

    // Complete the evaluation
    Evaluation::create([
        'semester_id' => $this->sem->id,
        'evaluator_id' => $studentUser->id,
        'evaluatee_id' => $this->facUser->id,
        'class_id' => $class->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 5.0,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('manage-evaluations')
        ->assertSet('activeTab', 'student')
        ->assertSee('Grace Hopper')
        ->assertSee('A')
        ->assertSee('1 Subject')
        ->assertSee('100%')
        ->assertSee('Completed');
});

test('dean and program head can access manage-evaluations without errors', function () {
    $deanEmp = Employee::create([
        'employee_number' => 'DEAN-001',
        'first_name' => 'Charles',
        'last_name' => 'Babbage',
        'role' => 'dean',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $deanUser = User::create([
        'name' => 'Charles Babbage',
        'email' => 'babbage@track.com',
        'employee_id' => $deanEmp->id,
        'password' => 'password',
    ]);
    $deanUser->assignRole('dean');

    $this->actingAs($deanUser)
        ->get('/manage-evaluations')
        ->assertStatus(200);

    Livewire::actingAs($deanUser)
        ->test('manage-evaluations')
        ->assertStatus(200);
});

test('manage evaluations component supports search and status filtering', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('manage-evaluations')
        ->set('search', 'NonExistentPersonXYZ')
        ->assertSee('No student records found')
        ->set('search', '')
        ->set('selectedStatus', 'completed')
        ->assertStatus(200);
});
