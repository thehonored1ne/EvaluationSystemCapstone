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
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Livewire\Volt\Volt;
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

    // Admin user
    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    // Faculty user & Employee record
    $this->dept = Department::create(['code' => 'CCS', 'name' => 'Computer Studies']);
    $this->facultyEmp = Employee::create([
        'employee_number' => 'FAC-01',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facultyUser = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'employee_id' => $this->facultyEmp->id,
        'password' => 'password',
    ]);
    $this->facultyUser->assignRole('faculty');

    // Student user & Student record
    $this->student = Student::create([
        'student_number' => 'STU-01',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'year_level' => 3,
    ]);
    $this->studentUser = User::create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'student_id' => $this->student->id,
        'password' => 'password',
    ]);
    $this->studentUser->assignRole('student');

    // Create a second student
    $this->otherStudent = Student::create([
        'student_number' => 'STU-02',
        'first_name' => 'Alice',
        'last_name' => 'Cooper',
        'year_level' => 2,
    ]);

    // Academic Semester context
    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDay(),
    ]);
});

test('only admins can access subjects and classes and role management routes', function () {
    // Admin access
    $this->actingAs($this->adminUser);
    $this->get('/admin/subjects')->assertStatus(200);
    $this->get('/admin/classes')->assertStatus(200);
    $this->get('/admin/employees')->assertStatus(200);
    $this->get('/admin/students')->assertStatus(200);

    // Faculty access denied
    $this->actingAs($this->facultyUser);
    $this->get('/admin/subjects')->assertStatus(403);
    $this->get('/admin/classes')->assertStatus(403);
    $this->get('/admin/employees')->assertStatus(403);
    $this->get('/admin/students')->assertStatus(403);

    // Student access denied
    $this->actingAs($this->studentUser);
    $this->get('/admin/subjects')->assertStatus(403);
    $this->get('/admin/classes')->assertStatus(403);
    $this->get('/admin/employees')->assertStatus(403);
    $this->get('/admin/students')->assertStatus(403);
});

test('subject crud works correctly with validations', function () {
    $this->actingAs($this->adminUser);

    // 1. Create Subject
    $component = Volt::test('admin.manage-subjects')
        ->set('code', 'CS101')
        ->set('name', 'Intro to CS')
        ->set('units', 3)
        ->set('description', 'Introduction class')
        ->call('createSubject');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('subjects', ['code' => 'CS101', 'name' => 'Intro to CS']);

    $subject = Subject::where('code', 'CS101')->first();

    // 2. Edit/Update Subject
    $component = Volt::test('admin.manage-subjects')
        ->call('editSubject', $subject)
        ->set('name', 'Intro to Computer Science')
        ->call('updateSubject');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('subjects', ['code' => 'CS101', 'name' => 'Intro to Computer Science']);

    // 3. Prevent duplicate code validation
    $component = Volt::test('admin.manage-subjects')
        ->set('code', 'CS101')
        ->set('name', 'Another CS Class')
        ->call('createSubject');

    $component->assertHasErrors(['code']);

    // 4. Delete Subject
    $component = Volt::test('admin.manage-subjects')
        ->call('confirmDelete', $subject)
        ->call('deleteSubject');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
});

test('class crud and student enrollment functions work', function () {
    $this->actingAs($this->adminUser);

    // Create a subject
    $subject = Subject::create(['code' => 'CS102', 'name' => 'Data Structures', 'units' => 4]);

    // 1. Create AcademicClass
    $component = Volt::test('admin.manage-classes')
        ->set('subject_id', $subject->id)
        ->set('teacher_id', $this->facultyEmp->id)
        ->set('semester_id', $this->semester->id)
        ->set('section', 'BSCS-3A')
        ->set('schedule', 'MW 9:00-10:30')
        ->set('room', 'Lab 2')
        ->call('createClass');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('classes', [
        'subject_id' => $subject->id,
        'teacher_id' => $this->facultyEmp->id,
        'section' => 'BSCS-3A',
    ]);

    $class = AcademicClass::where('section', 'BSCS-3A')->first();

    // 2. Edit/Update AcademicClass
    $component = Volt::test('admin.manage-classes')
        ->call('editClass', $class)
        ->set('room', 'Lab 3')
        ->call('updateClass');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('classes', ['id' => $class->id, 'room' => 'Lab 3']);

    // 3. Student Enrollment
    $component = Volt::test('admin.manage-classes')
        ->call('manageStudents', $class)
        ->call('enrollStudent', $this->student->id);

    $component->assertHasNoErrors();
    expect($class->students()->where('student_id', $this->student->id)->exists())->toBeTrue();

    // Search excludes already enrolled
    $component->set('studentSearch', 'Jane')
        ->assertViewHas('studentSearchResults', function ($results) {
            return count($results) === 0;
        });

    // Search includes unenrolled students (e.g. Alice)
    $component->set('studentSearch', 'Alice')
        ->assertViewHas('studentSearchResults', function ($results) {
            return count($results) === 1 && $results[0]->first_name === 'Alice';
        });

    // Unenroll Student
    $component->call('unenrollStudent', $this->student->id);
    $component->assertHasNoErrors();
    expect($class->students()->where('student_id', $this->student->id)->exists())->toBeFalse();
});

test('notifications count and auto read feature works', function () {
    // Authenticate as a student who has pending evaluations
    $this->actingAs($this->studentUser);

    // Let's create a subject and class for the student to be enrolled in
    $subject = Subject::create(['code' => 'CS103', 'name' => 'Algorithms', 'units' => 3]);
    $class = AcademicClass::create([
        'subject_id' => $subject->id,
        'semester_id' => $this->semester->id,
        'teacher_id' => $this->facultyEmp->id,
        'section' => 'BSCS-3A',
    ]);
    $class->students()->attach($this->student->id);

    // Make evaluations open
    $this->semester->update(['is_evaluation_open' => true]);

    // Retrieve notifications. Since evaluations are open and they haven't submitted yet, they should have notifications
    $notifications = $this->studentUser->getNotifications();
    expect(count($notifications))->toBeGreaterThan(0);

    // Initial notifications last viewed at is null
    expect($this->studentUser->notifications_last_viewed_at)->toBeNull();

    // The unread notifications count should match the total list size
    $unreadCountBefore = 0;
    foreach ($notifications as $notif) {
        if (! $this->studentUser->notifications_last_viewed_at || $notif->created_at->gt($this->studentUser->notifications_last_viewed_at)) {
            $unreadCountBefore++;
        }
    }
    expect($unreadCountBefore)->toBe(count($notifications));

    // Access notifications page to trigger the auto-read logic (mount method)
    Volt::test('notifications')
        ->assertHasNoErrors();

    // Verify notifications_last_viewed_at was set
    $this->studentUser->refresh();
    expect($this->studentUser->notifications_last_viewed_at)->not->toBeNull();

    // Verify unread notifications count is now 0 since last viewed is set to now
    $unreadCountAfter = 0;
    $updatedNotifications = $this->studentUser->getNotifications();
    foreach ($updatedNotifications as $notif) {
        if (! $this->studentUser->notifications_last_viewed_at || $notif->created_at->gt($this->studentUser->notifications_last_viewed_at)) {
            $unreadCountAfter++;
        }
    }
    expect($unreadCountAfter)->toBe(0);
});

test('dean and student role management pages CRUD functions correctly', function () {
    $this->actingAs($this->adminUser);

    // 1. Create Dean
    $component = Volt::test('admin.manage-employees')
        ->set('first_name', 'Albert')
        ->set('last_name', 'Einstein')
        ->set('email', 'albert@example.com')
        ->set('employee_number', 'DEC-999')
        ->set('role', 'dean')
        ->set('department_id', $this->dept->id)
        ->call('createUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('employees', ['employee_number' => 'DEC-999', 'role' => 'dean']);
    $this->assertDatabaseHas('users', ['email' => 'albert@example.com']);

    $deanUser = User::where('email', 'albert@example.com')->first();
    expect($deanUser->hasRole('dean'))->toBeTrue();

    // Toggle active status
    expect($deanUser->is_active)->toBeTrue();
    $component->call('toggleActive', $deanUser);
    expect($deanUser->refresh()->is_active)->toBeFalse();

    // 2. Create Student
    $program = Program::create(['code' => 'BSCS', 'name' => 'Computer Science', 'department_id' => $this->dept->id]);
    $studentComponent = Volt::test('admin.manage-students')
        ->set('first_name', 'Ada')
        ->set('last_name', 'Lovelace')
        ->set('email', 'ada@example.com')
        ->set('student_number', 'STU-999')
        ->set('program_id', $program->id)
        ->set('year_level', 4)
        ->call('createUser');

    $studentComponent->assertHasNoErrors();
    $this->assertDatabaseHas('students', ['student_number' => 'STU-999', 'year_level' => 4]);
    $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);

    $studentUser = User::where('email', 'ada@example.com')->first();
    expect($studentUser->hasRole('student'))->toBeTrue();
});

test('department CRUD inside evaluation settings works correctly', function () {
    $this->actingAs($this->adminUser);

    // Create a dean to test dean assignment
    $deanEmp = Employee::create([
        'employee_number' => 'DEAN-TEST-01',
        'first_name' => 'Richard',
        'last_name' => 'Feynman',
        'role' => 'dean',
        'status' => 'active',
    ]);

    // 1. Create Department
    $component = Volt::test('admin.evaluation-settings')
        ->call('openDeptModal')
        ->set('deptCode', 'COE')
        ->set('deptName', 'College of Engineering')
        ->set('deptDeanId', $deanEmp->id)
        ->call('saveDept');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('departments', ['code' => 'COE', 'name' => 'College of Engineering', 'dean_id' => $deanEmp->id]);
    expect($deanEmp->refresh()->department_id)->not->toBeNull();

    $dept = Department::where('code', 'COE')->first();

    // 2. Edit/Update Department
    $component = Volt::test('admin.evaluation-settings')
        ->call('openDeptModal', $dept->id)
        ->set('deptName', 'College of Engineering and Technology')
        ->call('saveDept');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'College of Engineering and Technology']);

    // 3. Prevent duplicate code validation
    $component = Volt::test('admin.evaluation-settings')
        ->call('openDeptModal')
        ->set('deptCode', 'COE')
        ->set('deptName', 'Duplicate College')
        ->call('saveDept');

    $component->assertHasErrors(['deptCode']);

    // 4. Delete Department
    $component = Volt::test('admin.evaluation-settings')
        ->call('confirmDeleteDept', $dept->id)
        ->call('deleteDept');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
});

test('filtering user management lists by department works correctly', function () {
    $this->actingAs($this->adminUser);

    // Create another department
    $otherDept = Department::create(['code' => 'COE', 'name' => 'Engineering']);

    // Create a program in COE
    $program = Program::create(['code' => 'BSEE', 'name' => 'Electrical Engineering', 'department_id' => $otherDept->id]);

    // Create a student in COE
    $studentEmp = Student::create([
        'student_number' => 'STU-COE',
        'first_name' => 'Nikola',
        'last_name' => 'Tesla',
        'program_id' => $program->id,
        'year_level' => 1,
    ]);
    $studentUser = User::create([
        'name' => 'Nikola Tesla',
        'email' => 'tesla@example.com',
        'student_id' => $studentEmp->id,
        'password' => 'password',
    ]);
    $studentUser->assignRole('student');

    // Test Students program filter
    Volt::test('admin.manage-students')
        ->set('selectedProgramId', $program->id)
        ->assertViewHas('users', function ($paginator) use ($studentUser) {
            $items = $paginator->items();

            return count($items) === 1 && $items[0]->id === $studentUser->id;
        })
        ->set('selectedProgramId', '')
        ->assertViewHas('users', function ($paginator) {
            return count($paginator->items()) > 1;
        });

    // Create a faculty in COE
    $facultyEmp2 = Employee::create([
        'employee_number' => 'FAC-COE',
        'first_name' => 'Thomas',
        'last_name' => 'Edison',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $otherDept->id,
    ]);
    $facultyUser2 = User::create([
        'name' => 'Thomas Edison',
        'email' => 'edison@example.com',
        'employee_id' => $facultyEmp2->id,
        'password' => 'password',
    ]);
    $facultyUser2->assignRole('faculty');

    // Test Faculty department filter
    Volt::test('admin.manage-employees')
        ->set('selectedRole', 'faculty')
        ->set('selectedDepartmentId', $otherDept->id)
        ->assertViewHas('users', function ($paginator) use ($facultyUser2) {
            $items = $paginator->items();

            return count($items) === 1 && $items[0]->id === $facultyUser2->id;
        })
        ->set('selectedDepartmentId', '')
        ->assertViewHas('users', function ($paginator) {
            return count($paginator->items()) > 1;
        });
});

test('program CRUD inside evaluation settings works correctly', function () {
    $this->actingAs($this->adminUser);

    // Create a program head to test assignment
    $headEmp = Employee::create([
        'employee_number' => 'HEAD-TEST-01',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'role' => 'program head',
        'status' => 'active',
    ]);

    // 1. Create Program
    $component = Volt::test('admin.evaluation-settings')
        ->call('openProgModal')
        ->set('progCode', 'BSIT')
        ->set('progName', 'BS Information Technology')
        ->set('progDeptId', $this->dept->id)
        ->set('progHeadId', $headEmp->id)
        ->call('saveProg');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('programs', ['code' => 'BSIT', 'name' => 'BS Information Technology', 'department_id' => $this->dept->id, 'program_head_id' => $headEmp->id]);
    expect($headEmp->refresh()->department_id)->not->toBeNull();

    $prog = Program::where('code', 'BSIT')->first();

    // 2. Edit/Update Program
    $component = Volt::test('admin.evaluation-settings')
        ->call('openProgModal', $prog->id)
        ->set('progName', 'BS Info Tech')
        ->call('saveProg');

    $component->assertHasNoErrors();
    $this->assertDatabaseHas('programs', ['id' => $prog->id, 'name' => 'BS Info Tech']);

    // 3. Prevent duplicate code validation
    $component = Volt::test('admin.evaluation-settings')
        ->call('openProgModal')
        ->set('progCode', 'BSIT')
        ->set('progName', 'Duplicate Program')
        ->set('progDeptId', $this->dept->id)
        ->call('saveProg');

    $component->assertHasErrors(['progCode']);

    // 4. Delete Program
    $component = Volt::test('admin.evaluation-settings')
        ->call('confirmDeleteProg', $prog->id)
        ->call('deleteProg');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('programs', ['id' => $prog->id]);
});

test('admin notifications badge disappears when navigating to notifications page', function () {
    // Authenticate as Admin
    $this->actingAs($this->adminUser);

    // Initial last viewed is null, should show badge if notifications exist
    expect($this->adminUser->notifications_last_viewed_at)->toBeNull();
    expect(count($this->adminUser->getNotifications()))->toBeGreaterThan(0);

    // Visit notifications page
    $response = $this->get('/notifications');
    $response->assertStatus(200);

    // Check that notifications_last_viewed_at has been updated
    $this->adminUser->refresh();
    expect($this->adminUser->notifications_last_viewed_at)->not->toBeNull();

    // The rendered notifications page should NOT contain the badge count in the sidebar
    // Since notifications are read, count is 0, so the amber badge should not be present
    $response->assertDontSee('color="amber"');

    // Visit admin dashboard, it should also NOT show the badge
    $dashboardResponse = $this->get('/admin/dashboard');
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertDontSee('color="amber"');
});

test('admin can delete dean, program head, faculty, staff, and student accounts', function () {
    $this->actingAs($this->adminUser);

    // 1. Delete Dean
    $deanEmp = Employee::create([
        'employee_number' => 'DEC-888',
        'first_name' => 'Dean',
        'last_name' => 'Test',
        'role' => 'dean',
        'status' => 'active',
    ]);
    $deanUser = User::create([
        'name' => 'Dean Test',
        'email' => 'dean.test@example.com',
        'employee_id' => $deanEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $deanUser->assignRole('dean');

    $component = Volt::test('admin.manage-employees')
        ->call('confirmDelete', $deanUser)
        ->call('deleteUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $deanUser->id]);
    $this->assertDatabaseMissing('employees', ['id' => $deanEmp->id]);

    // 2. Delete Program Head
    $phEmp = Employee::create([
        'employee_number' => 'PH-888',
        'first_name' => 'PH',
        'last_name' => 'Test',
        'role' => 'program head',
        'status' => 'active',
    ]);
    $phUser = User::create([
        'name' => 'PH Test',
        'email' => 'ph.test@example.com',
        'employee_id' => $phEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $phUser->assignRole('program head');

    $component = Volt::test('admin.manage-employees')
        ->call('confirmDelete', $phUser)
        ->call('deleteUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $phUser->id]);
    $this->assertDatabaseMissing('employees', ['id' => $phEmp->id]);

    // 3. Delete Faculty
    $facultyEmp = Employee::create([
        'employee_number' => 'FAC-888',
        'first_name' => 'Faculty',
        'last_name' => 'Test',
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $facultyUser = User::create([
        'name' => 'Faculty Test',
        'email' => 'faculty.test@example.com',
        'employee_id' => $facultyEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $facultyUser->assignRole('faculty');

    $component = Volt::test('admin.manage-employees')
        ->call('confirmDelete', $facultyUser)
        ->call('deleteUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $facultyUser->id]);
    $this->assertDatabaseMissing('employees', ['id' => $facultyEmp->id]);

    // 4. Delete Staff
    $staffEmp = Employee::create([
        'employee_number' => 'STF-888',
        'first_name' => 'Staff',
        'last_name' => 'Test',
        'role' => 'staff',
        'status' => 'active',
    ]);
    $staffUser = User::create([
        'name' => 'Staff Test',
        'email' => 'staff.test@example.com',
        'employee_id' => $staffEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $staffUser->assignRole('staff');

    $component = Volt::test('admin.manage-employees')
        ->call('confirmDelete', $staffUser)
        ->call('deleteUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $staffUser->id]);
    $this->assertDatabaseMissing('employees', ['id' => $staffEmp->id]);

    // 5. Delete Student
    $studentData = Student::create([
        'student_number' => 'STU-888',
        'first_name' => 'Student',
        'last_name' => 'Test',
        'year_level' => 1,
    ]);
    $studentUser = User::create([
        'name' => 'Student Test',
        'email' => 'student.test@example.com',
        'student_id' => $studentData->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $studentUser->assignRole('student');

    $component = Volt::test('admin.manage-students')
        ->call('confirmDelete', $studentUser)
        ->call('deleteUser');

    $component->assertHasNoErrors();
    $this->assertDatabaseMissing('users', ['id' => $studentUser->id]);
    $this->assertDatabaseMissing('students', ['id' => $studentData->id]);
});

test('filtering by student program and year level works correctly', function () {
    $this->actingAs($this->adminUser);

    // Create a program in department
    $dept = Department::create(['code' => 'TEST-DEPT', 'name' => 'Test Department']);
    $program = Program::create(['code' => 'TEST-PROG', 'name' => 'Test Program', 'department_id' => $dept->id]);

    // Create student 1: program = $program (department = $dept), year_level = 2
    $studentEmp1 = Student::create([
        'student_number' => 'STU-F1',
        'first_name' => 'Filtered',
        'last_name' => 'One',
        'program_id' => $program->id,
        'year_level' => 2,
    ]);
    $studentUser1 = User::create([
        'name' => 'Filtered One',
        'email' => 'f1@example.com',
        'student_id' => $studentEmp1->id,
        'password' => 'password',
    ]);
    $studentUser1->assignRole('student');

    // Create student 2: program = null (department = null), year_level = 3
    $studentEmp2 = Student::create([
        'student_number' => 'STU-F2',
        'first_name' => 'Filtered',
        'last_name' => 'Two',
        'program_id' => null,
        'year_level' => 3,
    ]);
    $studentUser2 = User::create([
        'name' => 'Filtered Two',
        'email' => 'f2@example.com',
        'student_id' => $studentEmp2->id,
        'password' => 'password',
    ]);
    $studentUser2->assignRole('student');

    // Test student filters
    Volt::test('admin.manage-students')
        // Filter by Program
        ->set('selectedProgramId', $program->id)
        ->assertViewHas('users', function ($paginator) use ($studentUser1, $studentUser2) {
            $ids = collect($paginator->items())->pluck('id');

            return $ids->contains($studentUser1->id) && ! $ids->contains($studentUser2->id);
        })
        // Filter by Program None
        ->set('selectedProgramId', 'none')
        ->assertViewHas('users', function ($paginator) use ($studentUser2, $studentUser1) {
            $ids = collect($paginator->items())->pluck('id');

            return $ids->contains($studentUser2->id) && ! $ids->contains($studentUser1->id);
        })
        // Filter by Year Level
        ->set('selectedProgramId', '')
        ->set('selectedYearLevel', 2)
        ->assertViewHas('users', function ($paginator) use ($studentUser1, $studentUser2) {
            $ids = collect($paginator->items())->pluck('id');

            return $ids->contains($studentUser1->id) && ! $ids->contains($studentUser2->id);
        });

    // Create a faculty with no department
    $facultyEmpNone = Employee::create([
        'employee_number' => 'FAC-NONE',
        'first_name' => 'No',
        'last_name' => 'Dept',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => null,
    ]);
    $facultyUserNone = User::create([
        'name' => 'No Dept',
        'email' => 'nodept@example.com',
        'employee_id' => $facultyEmpNone->id,
        'password' => 'password',
    ]);
    $facultyUserNone->assignRole('faculty');

    // Test Faculty Department None filter
    Volt::test('admin.manage-employees')
        ->set('selectedRole', 'faculty')
        ->set('selectedDepartmentId', 'none')
        ->assertViewHas('users', function ($paginator) use ($facultyUserNone) {
            $ids = collect($paginator->items())->pluck('id');

            return $ids->contains($facultyUserNone->id) && ! $ids->contains($this->facultyUser->id);
        });
});

test('admin dashboard recent submissions feed displays correct labels for non-class evaluations', function () {
    $this->actingAs($this->adminUser);

    // Create a program head user/employee to act as the evaluator
    $phEmp = Employee::create([
        'employee_number' => 'PH-123',
        'first_name' => 'Program',
        'last_name' => 'Head',
        'role' => 'program head',
        'status' => 'active',
    ]);
    $phUser = User::create([
        'name' => 'Program Head',
        'email' => 'ph.dashboard@example.com',
        'employee_id' => $phEmp->id,
        'password' => 'password',
    ]);

    // Create a subordinate faculty user/employee (evaluatee for downward)
    $facultyEmp = Employee::create([
        'employee_number' => 'FAC-123',
        'first_name' => 'Faculty',
        'last_name' => 'Subordinate',
        'role' => 'faculty',
        'status' => 'active',
    ]);
    $facultyUser = User::create([
        'name' => 'Faculty Subordinate',
        'email' => 'fac.dashboard@example.com',
        'employee_id' => $facultyEmp->id,
        'password' => 'password',
    ]);

    // Create a superior dean user/employee (evaluatee for upward_employee)
    $deanEmp = Employee::create([
        'employee_number' => 'DEAN-123',
        'first_name' => 'Dean',
        'last_name' => 'Superior',
        'role' => 'dean',
        'status' => 'active',
    ]);
    $deanUser = User::create([
        'name' => 'Dean Superior',
        'email' => 'dean.dashboard@example.com',
        'employee_id' => $deanEmp->id,
        'password' => 'password',
    ]);

    // 1. Self Evaluation
    $eval1 = new Evaluation([
        'evaluator_id' => $phUser->id,
        'evaluatee_id' => $phUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.50,
    ]);
    $eval1->created_at = Carbon::now()->subMinutes(10);
    $eval1->save();

    // 2. Downward Evaluation (Supervisor evaluating Subordinate)
    $eval2 = new Evaluation([
        'evaluator_id' => $phUser->id,
        'evaluatee_id' => $facultyUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.00,
    ]);
    $eval2->created_at = Carbon::now()->subMinutes(5);
    $eval2->save();

    // 3. Upward Employee Evaluation (Subordinate evaluating Superior)
    $eval3 = new Evaluation([
        'evaluator_id' => $phUser->id,
        'evaluatee_id' => $deanUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'upward_employee',
        'rating_average' => 4.80,
    ]);
    $eval3->created_at = Carbon::now();
    $eval3->save();

    // Test Volt component
    Livewire::withoutLazyLoading();
    Volt::test('admin.dashboard')
        ->assertViewHas('recentSubmissions', function ($submissions) {
            expect(count($submissions))->toBe(3);

            // Submissions are latest first, so order should be: Upward Employee, Downward, Self

            // 1. Upward Employee Evaluation:
            expect($submissions[0]['label'])->toBe('Supervisor Evaluation');
            expect($submissions[0]['description'])->toBe('Program Head evaluates Dean');

            // 2. Downward Evaluation:
            expect($submissions[1]['label'])->toBe('Program Head Evaluation');
            expect($submissions[1]['description'])->toBe('Program Head evaluates Professor');

            // 3. Self Evaluation:
            expect($submissions[2]['label'])->toBe('Self Evaluation');
            expect($submissions[2]['description'])->toBe('Program Head evaluates Self');

            return true;
        });
});
