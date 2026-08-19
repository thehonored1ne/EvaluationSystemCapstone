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
use Illuminate\Support\Facades\Artisan;
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
        'evaluation_starts_at' => now()->subDays(2),
        'evaluation_ends_at' => now()->addHours(20),
    ]);

    $this->dept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);
    $this->program = Program::create(['name' => 'BS Computer Science', 'code' => 'BSCS', 'department_id' => $this->dept->id]);

    $this->adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@reminders.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    $this->faculty = Employee::create([
        'employee_number' => 'FAC-001',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->dept->id,
    ]);
    $this->facultyUser = User::create(['name' => 'John Doe', 'email' => 'johndoe@reminders.com', 'employee_id' => $this->faculty->id, 'password' => 'password']);
    $this->facultyUser->assignRole('faculty');

    $this->student = Student::create([
        'student_number' => 'STU-001',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'program_id' => $this->program->id,
    ]);
    $this->studentUser = User::create(['name' => 'Jane Smith', 'email' => 'janesmith@reminders.com', 'student_id' => $this->student->id, 'password' => 'password']);
    $this->studentUser->assignRole('student');

    $this->subject = Subject::create(['code' => 'CS101', 'name' => 'Intro to CS', 'units' => 3]);
    $this->class = AcademicClass::create([
        'subject_id' => $this->subject->id,
        'semester_id' => $this->sem->id,
        'teacher_id' => $this->faculty->id,
        'section' => 'BSCS 1-A',
    ]);
    $this->class->students()->attach($this->student->id);
});

test('command runs cleanly when no active semester is configured', function () {
    Semester::query()->update(['is_active' => false]);

    Artisan::call('evaluations:send-reminders');
    expect(Artisan::output())->toContain('No active semester found.');
});

test('command skips execution when evaluation window is closed', function () {
    $this->sem->update(['is_evaluation_open' => false]);

    Artisan::call('evaluations:send-reminders');
    expect(Artisan::output())->toContain('Evaluations are currently closed');
});

test('command identifies 24h milestone and notifies pending student and faculty', function () {
    // 20 hours remaining -> 24h tier
    Artisan::call('evaluations:send-reminders');
    $output = Artisan::output();

    expect($output)->toContain('Tier: 24h')
        ->toContain('Notified 2 pending evaluators.');
});

test('command supports force flag when outside scheduled milestone', function () {
    $this->sem->update(['evaluation_ends_at' => now()->addDays(15)]);

    Artisan::call('evaluations:send-reminders', ['--force' => true]);
    $output = Artisan::output();

    expect($output)->toContain('Tier: manual')
        ->toContain('Broadcast completed successfully.');
});

test('user model dynamic notifications surface deadline approaching alert', function () {
    $notifs = $this->studentUser->getNotifications();
    $deadlineNotifs = array_filter($notifs, fn ($n) => str_contains($n->id, 'deadline'));

    expect($deadlineNotifs)->not->toBeEmpty();
    $firstDeadline = reset($deadlineNotifs);
    expect($firstDeadline->title)->toBe('Evaluation Deadline Approaching')
        ->and($firstDeadline->type)->toBe('warning');
});

test('admin send reminders action in completion tracking triggers broadcast', function () {
    $this->actingAs($this->adminUser);

    Livewire::withoutLazyLoading()
        ->test('manage-evaluations')
        ->call('sendReminderToast');

    $this->assertDatabaseHas('activity_log', [
        'causer_id' => $this->adminUser->id,
        'log_name' => 'evaluations',
    ]);
});
