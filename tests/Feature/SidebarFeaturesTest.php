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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();
    // Create roles
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'student']);
    Role::firstOrCreate(['name' => 'staff']);

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

    // Create Department
    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);

    // Admin user
    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    // Dean user
    $this->deanEmp = Employee::create(['employee_number' => 'D-01', 'first_name' => 'Dean', 'last_name' => 'CCS', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->deanUser = User::create(['name' => 'Dean', 'email' => 'dean@example.com', 'employee_id' => $this->deanEmp->id, 'password' => 'password']);
    $this->deanUser->assignRole('dean');

    // Program Head user
    $this->phEmp = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'PH', 'last_name' => 'CCS', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->phUser = User::create(['name' => 'Program Head', 'email' => 'ph@example.com', 'employee_id' => $this->phEmp->id, 'password' => 'password']);
    $this->phUser->assignRole('program head');

    // Faculty user
    $this->facEmp = Employee::create(['employee_number' => 'F-01', 'first_name' => 'Fac', 'last_name' => 'CCS', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUser = User::create(['name' => 'Faculty', 'email' => 'fac@example.com', 'employee_id' => $this->facEmp->id, 'password' => 'password']);
    $this->facUser->assignRole('faculty');

    // Student user
    $this->stud = Student::create(['student_number' => 'STU-01', 'first_name' => 'Stud', 'last_name' => 'CCS', 'year_level' => 1]);
    $this->studUser = User::create(['name' => 'Student', 'email' => 'student@example.com', 'student_id' => $this->stud->id, 'password' => 'password']);
    $this->studUser->assignRole('student');
});

test('route access authorization holds correct boundaries', function () {
    // 1. Admin has access to all routes
    $this->actingAs($this->adminUser);
    $this->get('/manage-evaluations')->assertStatus(200);
    $this->get('/evaluation-results')->assertStatus(200);
    $this->get('/analytics')->assertStatus(200);
    $this->get('/reports')->assertStatus(200);
    $this->get('/notifications')->assertStatus(200);

    // 2. Dean has access to evaluations, results, reports, notifications but NOT analytics
    $this->actingAs($this->deanUser);
    $this->get('/manage-evaluations')->assertStatus(200);
    $this->get('/evaluation-results')->assertStatus(200);
    $this->get('/reports')->assertStatus(200);
    $this->get('/notifications')->assertStatus(200);
    $this->get('/analytics')->assertStatus(403);

    // 3. Faculty cannot access manage-evaluations, evaluation-results, analytics, reports
    $this->actingAs($this->facUser);
    $this->get('/notifications')->assertStatus(200);
    $this->get('/manage-evaluations')->assertStatus(403);
    $this->get('/evaluation-results')->assertStatus(403);
    $this->get('/reports')->assertStatus(403);
    $this->get('/analytics')->assertStatus(403);

    // 4. Student cannot access either
    $this->actingAs($this->studUser);
    $this->get('/notifications')->assertStatus(200);
    $this->get('/manage-evaluations')->assertStatus(403);
});

test('manage evaluations lists completion rates correctly', function () {
    $this->actingAs($this->adminUser);

    // Create a subject and a class
    $sub = Subject::create(['code' => 'CS101', 'name' => 'Computing', 'units' => 3]);
    $class = AcademicClass::create([
        'subject_id' => $sub->id,
        'semester_id' => $this->semester->id,
        'teacher_id' => $this->facEmp->id,
        'section' => 'BSCS-1A',
    ]);

    // Enroll student
    $class->students()->attach($this->stud->id);

    // Submit evaluation
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->studUser->id,
        'evaluatee_id' => $this->facUser->id,
        'class_id' => $class->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.00,
    ]);

    // Request endpoint and assert class progress info is present
    $response = $this->get('/manage-evaluations');
    $response->assertStatus(200);
    $response->assertSee('BSCS-1A');
    $response->assertSee('1 / 1'); // Evaluated / Enrolled
});

test('sidebar renders correct evaluator submenus depending on user role', function () {
    // 1. Student
    $this->actingAs($this->studUser);
    $response = $this->get('/notifications'); // any page showing layout/sidebar
    $response->assertSee('My Evaluations');
    $response->assertSee('Evaluate Professors');
    $response->assertDontSee('Self Evaluation');
    $response->assertDontSee('Peer Evaluation');
    $response->assertDontSee('Overview');

    // 2. Faculty
    $this->actingAs($this->facUser);
    $response = $this->get('/notifications');
    $response->assertSee('My Evaluations');
    $response->assertSee('Self Evaluation');
    $response->assertSee('Peer Evaluation');
    $response->assertSee('Supervisor Evaluation');
    $response->assertDontSee('Overview');

    // 3. Staff (create a staff user for test)
    $staffEmp = Employee::create(['employee_number' => 'S-99', 'first_name' => 'St', 'last_name' => 'Ff', 'role' => 'staff', 'status' => 'active']);
    $staffUser = User::create(['name' => 'Staff Test', 'email' => 'staff.test@example.com', 'employee_id' => $staffEmp->id, 'password' => 'password']);
    $staffUser->assignRole('staff');

    $this->actingAs($staffUser);
    $response = $this->get('/notifications');
    $response->assertSee('My Evaluations');
    $response->assertSee('Self Evaluation');
    $response->assertSee('Supervisor Evaluation');
    $response->assertDontSee('Peer Evaluation');
    $response->assertDontSee('Overview');

    // 4. Dean
    $this->actingAs($this->deanUser);
    $response = $this->get('/notifications');
    $response->assertSee('My Evaluations');
    $response->assertSee('Self Evaluation');
    $response->assertSee('Program Head Evaluations');
    $response->assertDontSee('Overview');

    // 5. Program Head
    $this->actingAs($this->phUser);
    $response = $this->get('/notifications');
    $response->assertSee('My Evaluations');
    $response->assertSee('Self Evaluation');
    $response->assertSee('Supervisor Evaluation');
    $response->assertSee('Faculty Evaluations');
    $response->assertDontSee('Overview');
});
