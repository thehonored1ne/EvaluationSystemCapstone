<?php

use App\Models\AcademicClass;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();

    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'department head']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'student']);
    Role::firstOrCreate(['name' => 'staff']);

    $this->adminUser = User::factory()->create([
        'name' => 'System Admin',
        'email' => 'admin@grc.edu.ph',
    ]);
    $this->adminUser->assignRole('admin');

    $this->adminEmployee = Employee::create([
        'employee_number' => 'ADM-001',
        'first_name' => 'System',
        'last_name' => 'Admin',
        'role' => 'admin',
        'status' => 'active',
    ]);
    $this->adminUser->update(['employee_id' => $this->adminEmployee->id]);

    $this->department = Department::create([
        'code' => 'CCS',
        'name' => 'College of Computer Studies',
        'department_type' => 'academic',
    ]);

    $this->program = Program::create([
        'department_id' => $this->department->id,
        'code' => 'BSIT',
        'name' => 'Bachelor of Science in Information Technology',
    ]);

    $this->ay = AcademicYear::create([
        'name' => '2025-2026',
        'start_date' => '2025-08-01',
        'end_date' => '2026-05-31',
    ]);

    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);
});

test('admin can download student CSV template and export students', function () {
    $this->actingAs($this->adminUser);

    Volt::test('admin.manage-students')
        ->call('downloadTemplate')
        ->assertStatus(200);

    Volt::test('admin.manage-students')
        ->call('exportStudents')
        ->assertStatus(200);
});

test('admin can bulk import students via CSV', function () {
    $this->actingAs($this->adminUser);

    $csvContent = "student_number,first_name,middle_name,last_name,suffix,email,program_code,year_level,section,status\n"
        ."2026-01-9991,Jose,Protacio,Rizal,,jose.rizal@grc.edu.ph,BSIT,1,BSIT-1A,regular\n"
        ."2026-01-9992,Andres,,Bonifacio,,andres.bonifacio@grc.edu.ph,BSIT,2,BSIT-2A,irregular\n";

    $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

    Volt::test('admin.manage-students')
        ->set('importFile', $file)
        ->call('importStudents')
        ->assertHasNoErrors();

    expect(Student::where('student_number', '2026-01-9991')->exists())->toBeTrue()
        ->and(User::where('email', 'jose.rizal@grc.edu.ph')->exists())->toBeTrue()
        ->and(Student::where('student_number', '2026-01-9992')->first()->status)->toBe('irregular');
});

test('admin can download employee CSV template and bulk import employees', function () {
    $this->actingAs($this->adminUser);

    Volt::test('admin.manage-employees')
        ->call('downloadTemplate')
        ->assertStatus(200);

    $csvContent = "employee_number,first_name,middle_name,last_name,suffix,email,role,department_code,status\n"
        ."FAC-999,Grace,,Hopper,,grace.hopper@grc.edu.ph,faculty,CCS,active\n"
        ."PH-999,Linus,,Torvalds,,linus.torvalds@grc.edu.ph,program head,CCS,active\n";

    $file = UploadedFile::fake()->createWithContent('employees.csv', $csvContent);

    Volt::test('admin.manage-employees')
        ->set('importFile', $file)
        ->call('importEmployees')
        ->assertHasNoErrors();

    expect(Employee::where('employee_number', 'FAC-999')->exists())->toBeTrue()
        ->and(User::where('email', 'grace.hopper@grc.edu.ph')->exists())->toBeTrue()
        ->and(Employee::where('employee_number', 'PH-999')->first()->role)->toBe('program head');
});

test('admin can bulk import classes and student roster enrollments via CSV', function () {
    $this->actingAs($this->adminUser);

    $teacher = Employee::create([
        'employee_number' => 'FAC-101',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'role' => 'faculty',
        'department_id' => $this->department->id,
        'status' => 'active',
    ]);

    $subject = Subject::create([
        'code' => 'IT101',
        'name' => 'Introduction to Computing',
        'units' => 3,
    ]);

    $student1 = Student::create([
        'student_number' => '2026-01-8881',
        'first_name' => 'Test',
        'last_name' => 'Student One',
        'program_id' => $this->program->id,
        'year_level' => 1,
        'section' => 'BSIT-1A',
        'status' => 'regular',
    ]);

    $student2 = Student::create([
        'student_number' => '2026-01-8882',
        'first_name' => 'Test',
        'last_name' => 'Student Two',
        'program_id' => $this->program->id,
        'year_level' => 1,
        'section' => 'BSIT-1A',
        'status' => 'regular',
    ]);

    $csvContent = "subject_code,teacher_employee_number,section,schedule,room,student_numbers_comma_separated\n"
        ."IT101,FAC-101,BSIT-1A,MWF 08:00 AM - 09:30 AM,CL-1,2026-01-8881, 2026-01-8882\n";

    $file = UploadedFile::fake()->createWithContent('classes.csv', $csvContent);

    Volt::test('admin.manage-classes')
        ->set('importFile', $file)
        ->call('importClasses')
        ->assertHasNoErrors();

    $class = AcademicClass::where([
        'semester_id' => $this->semester->id,
        'subject_id' => $subject->id,
        'section' => 'BSIT-1A',
    ])->first();

    expect($class)->not->toBeNull()
        ->and($class->students()->count())->toBe(2);
});
