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

test('admin can bulk update student status and deactivates when graduated', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-B1',
        'first_name' => 'Bulk',
        'last_name' => 'One',
        'program_id' => $this->program->id,
        'year_level' => 4,
        'status' => 'regular',
    ]);
    $u1 = User::create([
        'name' => 'Bulk One',
        'email' => 'bulk1@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u1->assignRole('student');

    $s2 = Student::create([
        'student_number' => 'STU-B2',
        'first_name' => 'Bulk',
        'last_name' => 'Two',
        'program_id' => $this->program->id,
        'year_level' => 4,
        'status' => 'regular',
    ]);
    $u2 = User::create([
        'name' => 'Bulk Two',
        'email' => 'bulk2@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u2->assignRole('student');

    Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->set('bulkStatus', 'graduated')
        ->call('bulkSetStatus')
        ->assertHasNoErrors();

    expect($s1->fresh()->status)->toBe('graduated')
        ->and($s2->fresh()->status)->toBe('graduated')
        ->and($u1->fresh()->is_active)->toBeFalse()
        ->and($u2->fresh()->is_active)->toBeFalse();
});

test('admin can bulk update student year level', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-Y1',
        'first_name' => 'Year',
        'last_name' => 'One',
        'program_id' => $this->program->id,
        'year_level' => 1,
        'status' => 'regular',
    ]);
    $u1 = User::create([
        'name' => 'Year One',
        'email' => 'year1@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u1->assignRole('student');

    $s2 = Student::create([
        'student_number' => 'STU-Y2',
        'first_name' => 'Year',
        'last_name' => 'Two',
        'program_id' => $this->program->id,
        'year_level' => 1,
        'status' => 'regular',
    ]);
    $u2 = User::create([
        'name' => 'Year Two',
        'email' => 'year2@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u2->assignRole('student');

    Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->set('bulkYearLevel', '2')
        ->call('bulkSetYearLevel')
        ->assertHasNoErrors();

    expect($s1->fresh()->year_level)->toBe(2)
        ->and($s2->fresh()->year_level)->toBe(2);
});

test('admin can bulk toggle student login access', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-T1',
        'first_name' => 'Toggle',
        'last_name' => 'One',
        'year_level' => 2,
    ]);
    $u1 = User::create([
        'name' => 'Toggle One',
        'email' => 'toggle1@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
        'is_active' => true,
    ]);

    $s2 = Student::create([
        'student_number' => 'STU-T2',
        'first_name' => 'Toggle',
        'last_name' => 'Two',
        'year_level' => 2,
    ]);
    $u2 = User::create([
        'name' => 'Toggle Two',
        'email' => 'toggle2@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
        'is_active' => true,
    ]);

    // Bulk disable
    Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->call('bulkSetActive', false);

    expect($u1->fresh()->is_active)->toBeFalse()
        ->and($u2->fresh()->is_active)->toBeFalse();

    // Bulk enable
    Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->call('bulkSetActive', true);

    expect($u1->fresh()->is_active)->toBeTrue()
        ->and($u2->fresh()->is_active)->toBeTrue();
});

test('admin can bulk delete students safely protecting those with evaluation history', function () {
    $this->actingAs($this->adminUser);

    $sClean = Student::create([
        'student_number' => 'STU-CLEAN',
        'first_name' => 'Clean',
        'last_name' => 'Student',
        'year_level' => 1,
    ]);
    $uClean = User::create([
        'name' => 'Clean Student',
        'email' => 'clean@grc.edu.ph',
        'student_id' => $sClean->id,
        'password' => 'password',
        'is_active' => true,
    ]);

    $sHistory = Student::create([
        'student_number' => 'STU-HIST',
        'first_name' => 'History',
        'last_name' => 'Student',
        'year_level' => 1,
    ]);
    $uHistory = User::create([
        'name' => 'History Student',
        'email' => 'history@grc.edu.ph',
        'student_id' => $sHistory->id,
        'password' => 'password',
        'is_active' => true,
    ]);

    // Insert an evaluation record for uHistory
    DB::table('evaluations')->insert([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $uHistory->id,
        'evaluatee_id' => $this->adminUser->id,
        'evaluation_type' => 'student_to_faculty',
        'rating_average' => 4.5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Confirm bulk delete should identify 1 eligible and 1 blocked
    $component = Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $uClean->id, (string) $uHistory->id])
        ->call('confirmBulkDelete');

    $component->assertSet('bulkDeleteEligibleCount', 1)
        ->assertSet('bulkDeleteBlockedCount', 1)
        ->assertSet('showBulkDeleteModal', true);

    // Call bulkDelete
    $component->call('bulkDelete');

    // uClean should be deleted
    expect(User::find($uClean->id))->toBeNull()
        ->and(Student::find($sClean->id))->toBeNull();

    // uHistory should be safely preserved
    expect(User::find($uHistory->id))->not->toBeNull()
        ->and(Student::find($sHistory->id))->not->toBeNull();
});

test('student selections are preserved across filter and search changes', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-P1',
        'first_name' => 'FirstYear',
        'last_name' => 'Student',
        'year_level' => 1,
    ]);
    $u1 = User::create([
        'name' => 'FirstYear Student',
        'email' => 'firstyear@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
    ]);

    $s2 = Student::create([
        'student_number' => 'STU-P2',
        'first_name' => 'FourthYear',
        'last_name' => 'Student',
        'year_level' => 4,
    ]);
    $u2 = User::create([
        'name' => 'FourthYear Student',
        'email' => 'fourthyear@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
    ]);

    $component = Volt::test('admin.manage-students')
        // Filter by 1st year
        ->set('selectedYearLevel', '1')
        ->set('selectedIds', [(string) $u1->id])
        // Now change filter to 4th year
        ->set('selectedYearLevel', '4');

    // Selection of u1 should still be preserved
    expect($component->get('selectedIds'))->toContain((string) $u1->id);

    // Now select u2 as well
    $component->set('selectedIds', [(string) $u1->id, (string) $u2->id]);

    // Perform bulk status update
    $component->set('bulkStatus', 'irregular')
        ->call('bulkSetStatus');

    // Both u1 and u2 should now be updated
    expect($s1->fresh()->status)->toBe('irregular')
        ->and($s2->fresh()->status)->toBe('irregular');
});

test('admin can remove individual student from selection via review selection modal', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-REV-1',
        'first_name' => 'Review',
        'last_name' => 'One',
        'year_level' => 1,
    ]);
    $u1 = User::create([
        'name' => 'Review One',
        'email' => 'rev1@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
    ]);

    $s2 = Student::create([
        'student_number' => 'STU-REV-2',
        'first_name' => 'Review',
        'last_name' => 'Two',
        'year_level' => 2,
    ]);
    $u2 = User::create([
        'name' => 'Review Two',
        'email' => 'rev2@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
    ]);

    $component = Volt::test('admin.manage-students')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->set('showReviewSelectionModal', true);

    expect($component->get('selectedIds'))->toHaveCount(2);

    // Remove u1
    $component->call('removeSelected', (string) $u1->id);

    expect($component->get('selectedIds'))->toEqual([(string) $u2->id])
        ->and($component->get('showReviewSelectionModal'))->toBeTrue();

    // Remove u2 (last one)
    $component->call('removeSelected', (string) $u2->id);

    expect($component->get('selectedIds'))->toBeEmpty()
        ->and($component->get('showReviewSelectionModal'))->toBeFalse();
});

test('admin can restore valid student selections from session storage payload', function () {
    $this->actingAs($this->adminUser);

    $s1 = Student::create([
        'student_number' => 'STU-RESTORE-1',
        'first_name' => 'Restore',
        'last_name' => 'One',
        'year_level' => 1,
    ]);
    $u1 = User::create([
        'name' => 'Restore One',
        'email' => 'restore1@grc.edu.ph',
        'student_id' => $s1->id,
        'password' => 'password',
    ]);
    $u1->assignRole('student');

    $s2 = Student::create([
        'student_number' => 'STU-RESTORE-2',
        'first_name' => 'Restore',
        'last_name' => 'Two',
        'year_level' => 2,
    ]);
    $u2 = User::create([
        'name' => 'Restore Two',
        'email' => 'restore2@grc.edu.ph',
        'student_id' => $s2->id,
        'password' => 'password',
    ]);
    $u2->assignRole('student');

    // Create a non-student user to verify validation filtering
    $adminOther = User::create([
        'name' => 'Other Admin',
        'email' => 'otheradmin@grc.edu.ph',
        'password' => 'password',
    ]);
    $adminOther->assignRole('admin');

    $component = Volt::test('admin.manage-students');

    // Restore payload containing valid student IDs, a non-student user ID, and a nonexistent ID (999999)
    $component->call('restoreSelectedIds', [(string) $u1->id, (string) $u2->id, (string) $adminOther->id, 999999]);

    // Should only restore valid student users
    expect($component->get('selectedIds'))->toHaveCount(2)
        ->toContain((string) $u1->id)
        ->toContain((string) $u2->id)
        ->not->toContain((string) $adminOther->id)
        ->not->toContain('999999');

    // Deselect all should clear IDs and dispatch clear event
    $component->call('deselectAll');
    expect($component->get('selectedIds'))->toBeEmpty();
    $component->assertDispatched('clear-selected-storage');
});

test('admin can bulk update employee status and deactivates when resigned or retired', function () {
    $this->actingAs($this->adminUser);

    $e1 = Employee::create([
        'employee_number' => 'EMP-BULK-1',
        'first_name' => 'Alice',
        'last_name' => 'Faculty',
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $u1 = User::create([
        'name' => 'Alice Faculty',
        'email' => 'alice.fac@grc.edu.ph',
        'employee_id' => $e1->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u1->assignRole('faculty');

    $e2 = Employee::create([
        'employee_number' => 'EMP-BULK-2',
        'first_name' => 'Bob',
        'last_name' => 'Faculty',
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $u2 = User::create([
        'name' => 'Bob Faculty',
        'email' => 'bob.fac@grc.edu.ph',
        'employee_id' => $e2->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u2->assignRole('faculty');

    $component = Volt::test('admin.manage-employees')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->set('bulkStatus', 'resigned')
        ->call('bulkSetStatus');

    expect($component->get('selectedIds'))->toBeEmpty();

    expect($e1->fresh()->status)->toBe('resigned');
    expect($u1->fresh()->is_active)->toBeFalse();

    expect($e2->fresh()->status)->toBe('resigned');
    expect($u2->fresh()->is_active)->toBeFalse();
});

test('admin can bulk assign employee department and employment type', function () {
    $this->actingAs($this->adminUser);

    $newDept = Department::create([
        'code' => 'CBAE',
        'name' => 'College of Business Administration',
        'department_type' => 'academic',
    ]);

    $e = Employee::create([
        'employee_number' => 'EMP-DEPT-1',
        'first_name' => 'Charlie',
        'last_name' => 'Teacher',
        'role' => 'faculty',
        'status' => 'active',
        'employment_type' => 'full_time',
        'department_id' => $this->department->id,
    ]);
    $u = User::create([
        'name' => 'Charlie Teacher',
        'email' => 'charlie.teach@grc.edu.ph',
        'employee_id' => $e->id,
        'password' => 'password',
    ]);
    $u->assignRole('faculty');

    Volt::test('admin.manage-employees')
        ->set('selectedIds', [(string) $u->id])
        ->set('bulkDepartmentId', (string) $newDept->id)
        ->call('bulkSetDepartment');

    expect($e->fresh()->department_id)->toBe($newDept->id);

    Volt::test('admin.manage-employees')
        ->set('selectedIds', [(string) $u->id])
        ->set('bulkEmploymentType', 'part_time')
        ->call('bulkSetEmploymentType');

    expect($e->fresh()->employment_type)->toBe('part_time');
});

test('admin can bulk toggle employee login access', function () {
    $this->actingAs($this->adminUser);

    $e1 = Employee::create([
        'employee_number' => 'EMP-ACC-1',
        'first_name' => 'David',
        'last_name' => 'Staff',
        'role' => 'staff',
        'status' => 'active',
    ]);
    $u1 = User::create([
        'name' => 'David Staff',
        'email' => 'david.staff@grc.edu.ph',
        'employee_id' => $e1->id,
        'password' => 'password',
        'is_active' => true,
    ]);
    $u1->assignRole('staff');

    $component = Volt::test('admin.manage-employees')
        ->set('selectedIds', [(string) $u1->id]);

    expect($component->get('hasSelectedActive'))->toBeTrue();

    // Disable access
    $component->call('bulkSetActive', false);
    expect($u1->fresh()->is_active)->toBeFalse();

    // Enable access
    $component->set('selectedIds', [(string) $u1->id]);
    expect($component->get('hasSelectedInactive'))->toBeTrue();
    $component->call('bulkSetActive', true);
    expect($u1->fresh()->is_active)->toBeTrue();
});

test('admin can bulk delete employees safely protecting those with evaluations or classes', function () {
    $this->actingAs($this->adminUser);

    // Employee 1: has class -> blocked
    $e1 = Employee::create([
        'employee_number' => 'EMP-DEL-1',
        'first_name' => 'Elena',
        'last_name' => 'Prof',
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $u1 = User::create([
        'name' => 'Elena Prof',
        'email' => 'elena.prof@grc.edu.ph',
        'employee_id' => $e1->id,
        'password' => 'password',
    ]);
    $u1->assignRole('faculty');

    $sub = Subject::create([
        'code' => 'IT101',
        'name' => 'Intro to IT',
    ]);

    AcademicClass::create([
        'subject_id' => $sub->id,
        'teacher_id' => $e1->id,
        'academic_year_id' => $this->ay->id,
        'semester_id' => $this->semester->id,
        'section' => '4A',
    ]);

    // Employee 2: safe to delete
    $e2 = Employee::create([
        'employee_number' => 'EMP-DEL-2',
        'first_name' => 'Frank',
        'last_name' => 'Temp',
        'role' => 'staff',
        'status' => 'active',
    ]);
    $u2 = User::create([
        'name' => 'Frank Temp',
        'email' => 'frank.temp@grc.edu.ph',
        'employee_id' => $e2->id,
        'password' => 'password',
    ]);
    $u2->assignRole('staff');

    $component = Volt::test('admin.manage-employees')
        ->set('selectedIds', [(string) $u1->id, (string) $u2->id])
        ->call('confirmBulkDelete');

    expect($component->get('bulkDeleteBlockedCount'))->toBe(1)
        ->and($component->get('bulkDeleteEligibleCount'))->toBe(1)
        ->and($component->get('bulkDeleteEligibleIds'))->toEqual([(string) $u2->id])
        ->and($component->get('showBulkDeleteModal'))->toBeTrue();

    // Execute safe delete
    $component->call('bulkDelete');

    expect(User::find($u1->id))->not->toBeNull()
        ->and(User::find($u2->id))->toBeNull()
        ->and(Employee::find($e2->id))->toBeNull();
});

test('admin can restore employee selections from session storage payload', function () {
    $this->actingAs($this->adminUser);

    $e = Employee::create([
        'employee_number' => 'EMP-RESTORE-1',
        'first_name' => 'Grace',
        'last_name' => 'Dean',
        'role' => 'dean',
        'status' => 'active',
    ]);
    $u = User::create([
        'name' => 'Grace Dean',
        'email' => 'grace.dean@grc.edu.ph',
        'employee_id' => $e->id,
        'password' => 'password',
    ]);
    $u->assignRole('dean');

    $component = Volt::test('admin.manage-employees')
        ->call('restoreSelectedIds', [(string) $u->id, 999999]);

    expect($component->get('selectedIds'))->toHaveCount(1)
        ->toContain((string) $u->id)
        ->not->toContain('999999');

    $component->call('deselectAll');
    expect($component->get('selectedIds'))->toBeEmpty();
    $component->assertDispatched('clear-selected-storage');
});
