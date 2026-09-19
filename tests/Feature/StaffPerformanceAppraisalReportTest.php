<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'staff']);
    Role::firstOrCreate(['name' => 'department head']);
    Evaluation::flushStatusCache();
});

test('reports page switches track to staff and filters administrative departments and employees', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $academicDept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);
    $adminDept = Department::create(['name' => 'Registrar Office', 'code' => 'REG', 'type' => 'administrative']);

    $facEmp = Employee::create(['employee_number' => 'FAC-001', 'first_name' => 'John', 'last_name' => 'Doe', 'role' => 'faculty', 'department_id' => $academicDept->id]);
    $facUser = User::create(['name' => 'John Doe', 'email' => 'john@example.com', 'employee_id' => $facEmp->id, 'password' => bcrypt('password')]);
    $facUser->assignRole('faculty');

    $staffEmp = Employee::create(['employee_number' => 'STF-001', 'first_name' => 'Maria', 'last_name' => 'Santos', 'role' => 'staff', 'department_id' => $adminDept->id]);
    $staffUser = User::create(['name' => 'Maria Santos', 'email' => 'maria@example.com', 'employee_id' => $staffEmp->id, 'password' => bcrypt('password')]);
    $staffUser->assignRole('staff');

    $adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
    $adminUser->assignRole('admin');

    $component = Livewire::actingAs($adminUser)
        ->test('reports')
        ->assertSet('activeTab', 'individual')
        ->assertSet('reportTrack', 'faculty')
        ->assertSee('Individual Teaching Effectiveness Report')
        ->assertSee('Evaluation Summary Report')
        ->assertSee('Non-Teaching Staff Performance Report')
        ->assertSee('John Doe')
        ->assertDontSee('Maria Santos');

    // Switch to 3rd tab: Non-Teaching Staff Performance
    $component->call('setActiveTab', 'staff')
        ->assertSet('activeTab', 'staff')
        ->assertSet('reportTrack', 'staff')
        ->assertSee('Maria Santos')
        ->assertDontSee('John Doe')
        ->assertSee('Registrar Office');
});

test('getReportDataForStaff aggregates department head, peer, and self scores accurately', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $adminDept = Department::create(['name' => 'Accounting Office', 'code' => 'ACCT', 'type' => 'administrative']);

    $headEmp = Employee::create(['employee_number' => 'HD-001', 'first_name' => 'Chief', 'last_name' => 'Accountant', 'role' => 'department head', 'department_id' => $adminDept->id]);
    $headUser = User::create(['name' => 'Chief Accountant', 'email' => 'head@example.com', 'employee_id' => $headEmp->id, 'password' => bcrypt('password')]);
    $headUser->assignRole('department head');
    $adminDept->update(['department_head_id' => $headEmp->id]);

    $staffEmp = Employee::create(['employee_number' => 'STF-002', 'first_name' => 'Carlos', 'last_name' => 'Staff', 'role' => 'staff', 'department_id' => $adminDept->id]);
    $staffUser = User::create(['name' => 'Carlos Staff', 'email' => 'carlos@example.com', 'employee_id' => $staffEmp->id, 'password' => bcrypt('password')]);
    $staffUser->assignRole('staff');

    $peerEmp = Employee::create(['employee_number' => 'STF-003', 'first_name' => 'Elena', 'last_name' => 'Peer', 'role' => 'staff', 'department_id' => $adminDept->id]);
    $peerUser = User::create(['name' => 'Elena Peer', 'email' => 'elena@example.com', 'employee_id' => $peerEmp->id, 'password' => bcrypt('password')]);
    $peerUser->assignRole('staff');

    // 1. Department Head Evaluation (50 max points): 4.50 / 5.0 => 45.0 points
    Evaluation::create([
        'evaluator_id' => $headUser->id,
        'evaluatee_id' => $staffUser->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'department_head',
        'rating_average' => 4.50,
        'raw_score' => 45.0,
        'max_score' => 50.0,
        'weighted_score' => 45.0,
        'comments' => 'Demonstrates efficient transaction handling and accurate records.',
    ]);

    // 2. Peer Evaluation (30 max points): 4.00 / 5.0 => 24.0 points
    Evaluation::create([
        'evaluator_id' => $peerUser->id,
        'evaluatee_id' => $staffUser->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'peer',
        'rating_average' => 4.00,
        'raw_score' => 24.0,
        'max_score' => 30.0,
        'weighted_score' => 24.0,
        'comments' => 'Always cooperative and respectful in daily work.',
    ]);

    // 3. Self-Evaluation (20 max points): 5.00 / 5.0 => 20.0 points
    Evaluation::create([
        'evaluator_id' => $staffUser->id,
        'evaluatee_id' => $staffUser->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.00,
        'raw_score' => 20.0,
        'max_score' => 20.0,
        'weighted_score' => 20.0,
        'comments' => 'Strived to achieve zero backlog in student receipts.',
    ]);

    $adminUser = User::create(['name' => 'Admin Boss', 'email' => 'boss@example.com', 'password' => bcrypt('password')]);
    $adminUser->assignRole('admin');

    $component = Livewire::actingAs($adminUser)
        ->test('reports')
        ->set('reportTrack', 'staff')
        ->set('selectedSemesterId', $sem->id)
        ->set('selectedTeacherId', $staffEmp->id);

    $reportData = $component->get('individualReportData');

    expect($reportData)->not->toBeNull();
    expect($reportData->report_type)->toBe('staff');
    expect($reportData->dept_head_section->subtotal)->toBe(45.0);
    expect($reportData->peer_section->subtotal)->toBe(24.0);
    expect($reportData->self_section->subtotal)->toBe(20.0);
    // Composite score: 45 + 24 + 20 = 89.00 / 100.00 => Very Satisfactory (VS)
    expect($reportData->total_achieved_points)->toBe(89.00);
    expect($reportData->rating_code)->toBe('VS');
    expect($reportData->descriptive_rating)->toBe('Very Satisfactory (VS)');
    expect($reportData->department_head_name)->toBe('Chief Accountant');
});

test('staff report dynamically normalizes weights when peer evaluation is absent', function () {
    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $adminDept = Department::create(['name' => 'Admissions Office', 'code' => 'ADM', 'type' => 'administrative']);

    $headEmp = Employee::create(['employee_number' => 'HD-002', 'first_name' => 'Grace', 'last_name' => 'Head', 'role' => 'department head', 'department_id' => $adminDept->id]);
    $headUser = User::create(['name' => 'Grace Head', 'email' => 'grace@example.com', 'employee_id' => $headEmp->id, 'password' => bcrypt('password')]);
    $headUser->assignRole('department head');

    $staffEmp = Employee::create(['employee_number' => 'STF-004', 'first_name' => 'Solo', 'last_name' => 'Worker', 'role' => 'staff', 'department_id' => $adminDept->id]);
    $staffUser = User::create(['name' => 'Solo Worker', 'email' => 'solo@example.com', 'employee_id' => $staffEmp->id, 'password' => bcrypt('password')]);
    $staffUser->assignRole('staff');

    // Head evaluation: 5.0 rating => 50.0 points
    Evaluation::create([
        'evaluator_id' => $headUser->id,
        'evaluatee_id' => $staffUser->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'department_head',
        'rating_average' => 5.00,
        'raw_score' => 50.0,
        'max_score' => 50.0,
        'weighted_score' => 50.0,
    ]);

    // Self evaluation: 5.0 rating => 20.0 points
    Evaluation::create([
        'evaluator_id' => $staffUser->id,
        'evaluatee_id' => $staffUser->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.00,
        'raw_score' => 20.0,
        'max_score' => 20.0,
        'weighted_score' => 20.0,
    ]);

    // No peer evaluations submitted (e.g. solitary unit)
    $adminUser = User::create(['name' => 'HR Admin', 'email' => 'hr@example.com', 'password' => bcrypt('password')]);
    $adminUser->assignRole('admin');

    $component = Livewire::actingAs($adminUser)
        ->test('reports')
        ->set('reportTrack', 'staff')
        ->set('selectedSemesterId', $sem->id)
        ->set('selectedTeacherId', $staffEmp->id);

    $reportData = $component->get('individualReportData');

    expect($reportData->is_peer_exempted)->toBeTrue();
    expect($reportData->peer_section->is_exempted)->toBeTrue();
    // Normalized scale factor = 100 / 70 => 1.42857
    // Head: 50 * (100/70) = 71.43, Self: 20 * (100/70) = 28.57, Total = 100.00
    expect($reportData->total_achieved_points)->toBe(100.00);
    expect($reportData->rating_code)->toBe('E');
    expect($reportData->descriptive_rating)->toBe('Excellent (E)');
});

test('department head can access reports page for their administrative unit and only sees staff tab', function () {
    $adminDept = Department::create(['name' => 'Guidance Office', 'code' => 'GUID', 'type' => 'administrative']);

    $headEmp = Employee::create(['employee_number' => 'HD-003', 'first_name' => 'Guidance', 'last_name' => 'Director', 'role' => 'department head', 'department_id' => $adminDept->id]);
    $headUser = User::create(['name' => 'Guidance Director', 'email' => 'guidance@example.com', 'employee_id' => $headEmp->id, 'password' => bcrypt('password'), 'email_verified_at' => now()]);
    $headUser->assignRole('department head');

    $this->actingAs($headUser)
        ->get('/reports')
        ->assertOk()
        ->assertSee('Non-Teaching Staff Performance Report')
        ->assertDontSee('Individual Teaching Effectiveness Report')
        ->assertDontSee('Evaluation Summary Report');

    Livewire::actingAs($headUser)
        ->test('reports')
        ->assertSet('activeTab', 'staff')
        ->assertSet('reportTrack', 'staff')
        ->assertSee('Non-Teaching Staff Performance Report')
        ->assertDontSee('Individual Teaching Effectiveness Report')
        ->assertDontSee('Evaluation Summary Report')
        ->call('setActiveTab', 'individual')
        ->assertSet('activeTab', 'staff')
        ->call('setActiveTab', 'summary')
        ->assertSet('activeTab', 'staff');
});

test('program head can access reports page and only sees individual teaching effectiveness tab', function () {
    Role::firstOrCreate(['name' => 'program head']);
    $acadDept = Department::create(['name' => 'Computer Science', 'code' => 'CS', 'type' => 'academic']);

    $progEmp = Employee::create(['employee_number' => 'PH-001', 'first_name' => 'Alan', 'last_name' => 'Turing', 'role' => 'program head', 'department_id' => $acadDept->id]);
    $progUser = User::create(['name' => 'Alan Turing', 'email' => 'turing@example.com', 'employee_id' => $progEmp->id, 'password' => bcrypt('password'), 'email_verified_at' => now()]);
    $progUser->assignRole('program head');

    $this->actingAs($progUser)
        ->get('/reports')
        ->assertOk()
        ->assertSee('Individual Teaching Effectiveness Report')
        ->assertDontSee('Evaluation Summary Report')
        ->assertDontSee('Non-Teaching Staff Performance Report');

    Livewire::actingAs($progUser)
        ->test('reports')
        ->assertSet('activeTab', 'individual')
        ->assertSet('reportTrack', 'faculty')
        ->assertSee('Individual Teaching Effectiveness Report')
        ->assertDontSee('Evaluation Summary Report')
        ->assertDontSee('Non-Teaching Staff Performance Report')
        ->call('setActiveTab', 'summary')
        ->assertSet('activeTab', 'individual')
        ->call('setActiveTab', 'staff')
        ->assertSet('activeTab', 'individual');
});

test('dean can access individual and summary tabs but not staff tab', function () {
    Role::firstOrCreate(['name' => 'dean']);
    $deanDept = Department::create(['name' => 'College of Engineering', 'code' => 'COE', 'type' => 'academic']);

    $deanEmp = Employee::create(['employee_number' => 'DN-001', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'role' => 'dean', 'department_id' => $deanDept->id]);
    $deanUser = User::create(['name' => 'Grace Hopper', 'email' => 'hopper@example.com', 'employee_id' => $deanEmp->id, 'password' => bcrypt('password'), 'email_verified_at' => now()]);
    $deanUser->assignRole('dean');

    $this->actingAs($deanUser)
        ->get('/reports')
        ->assertOk()
        ->assertSee('Individual Teaching Effectiveness Report')
        ->assertSee('Evaluation Summary Report')
        ->assertDontSee('Non-Teaching Staff Performance Report');

    Livewire::actingAs($deanUser)
        ->test('reports')
        ->assertSet('activeTab', 'individual')
        ->assertSee('Individual Teaching Effectiveness Report')
        ->assertSee('Evaluation Summary Report')
        ->assertDontSee('Non-Teaching Staff Performance Report')
        ->call('setActiveTab', 'summary')
        ->assertSet('activeTab', 'summary')
        ->call('setActiveTab', 'staff')
        ->assertSet('activeTab', 'summary');
});
