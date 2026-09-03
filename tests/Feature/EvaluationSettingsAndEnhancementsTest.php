<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EvaluationCriterion;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'staff']);
    Role::firstOrCreate(['name' => 'student']);

    $this->year = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->year->id,
        'name' => '1st Semester',
        'is_active' => true,
        'overall_max_points' => 200,
        'student_weight' => 40,
        'dean_weight' => 20,
        'ph_dh_weight' => 20,
        'peer_weight' => 15,
        'self_weight' => 5,
        'upward_student_max_points' => 80,
        'dean_max_points' => 40,
        'program_head_max_points' => 40,
        'peer_max_points' => 30,
        'self_max_points' => 10,
    ]);

    $this->dept = Department::create(['name' => 'College of Computer Studies', 'code' => 'CCS', 'type' => 'academic']);

    $this->adminEmp = Employee::create(['employee_number' => 'ADM-01', 'first_name' => 'Admin', 'last_name' => 'Root', 'role' => 'admin', 'department_id' => $this->dept->id]);
    $this->adminUser = User::create(['name' => 'Admin User', 'email' => 'admin@grc.edu.ph', 'employee_id' => $this->adminEmp->id, 'password' => 'password', 'show_ai_pipeline' => true]);
    $this->adminUser->assignRole('admin');
});

test('training settings component renders and toggles show_ai_pipeline', function () {
    $this->actingAs($this->adminUser);

    Volt::test('settings.training')
        ->assertSee('Sidebar AI Pipeline Navigation')
        ->assertSet('showAiPipeline', true)
        ->call('toggleAiPipeline')
        ->assertSet('showAiPipeline', false);

    expect($this->adminUser->fresh()->show_ai_pipeline)->toBeFalse();

    // Verify sidebar hides AI pipeline when show_ai_pipeline is false
    $response = $this->get('/notifications');
    $response->assertDontSee('AI Pipeline');

    // Toggle back to true
    Volt::test('settings.training')
        ->call('toggleAiPipeline')
        ->assertSet('showAiPipeline', true);

    expect($this->adminUser->fresh()->show_ai_pipeline)->toBeTrue();

    // Verify sidebar displays AI pipeline
    $response = $this->get('/notifications');
    $response->assertSee('AI Pipeline');
});

test('training settings is inaccessible to non-admin users and hidden in settings nav', function () {
    // Create a student user
    $studentUser = User::create([
        'name' => 'Student User',
        'email' => 'student.test@grc.edu.ph',
        'password' => 'password',
    ]);
    $studentUser->assignRole('student');

    // 1. Settings page for student should not contain Training nav link
    $this->actingAs($studentUser);
    $response = $this->get('/settings/profile');
    $response->assertOk();
    $response->assertDontSee('Training');

    // 2. Direct access to /settings/training route should be forbidden (403)
    $response = $this->get('/settings/training');
    $response->assertForbidden();

    // 3. Admin user visiting /settings/profile should see Training nav link
    $this->actingAs($this->adminUser);
    $adminResponse = $this->get('/settings/profile');
    $adminResponse->assertOk();
    $adminResponse->assertSee('Training');

    // 4. Admin user accessing /settings/training route should succeed (200)
    $adminTrainingResponse = $this->get('/settings/training');
    $adminTrainingResponse->assertOk();
});

test('evaluation settings saves weights and points with editable max weight percentage', function () {
    $this->actingAs($this->adminUser);

    // Create criteria for the 5 teaching effectiveness categories
    $cStudent = EvaluationCriterion::create(['evaluation_type' => 'student', 'name' => 'Part 1: Student', 'max_points' => 80, 'order' => 1]);
    $cDean = EvaluationCriterion::create(['evaluation_type' => 'dean', 'name' => 'Part 1: Dean', 'max_points' => 40, 'order' => 1]);
    $cPH = EvaluationCriterion::create(['evaluation_type' => 'program_head', 'name' => 'Part 1: PH', 'max_points' => 40, 'order' => 1]);
    $cPeer = EvaluationCriterion::create(['evaluation_type' => 'peer', 'name' => 'Part 1: Peer', 'max_points' => 30, 'order' => 1]);
    $cSelf = EvaluationCriterion::create(['evaluation_type' => 'self', 'name' => 'Part 1: Self', 'max_points' => 10, 'order' => 1]);

    Livewire::actingAs($this->adminUser)
        ->test('admin.evaluation-settings')
        ->set('overallMaxTarget', '200')
        ->set('maxWeightPercent', '100')
        ->set('studentWeightTarget', '40')
        ->set('deanWeightTarget', '20')
        ->set('phDhWeightTarget', '20')
        ->set('peerWeightTarget', '15')
        ->set('selfWeightTarget', '5')
        ->set('criteriaPoints.'.$cStudent->id, 80)
        ->set('criteriaPoints.'.$cDean->id, 40)
        ->set('criteriaPoints.'.$cPH->id, 40)
        ->set('criteriaPoints.'.$cPeer->id, 30)
        ->set('criteriaPoints.'.$cSelf->id, 10)
        ->call('savePoints')
        ->assertHasNoErrors();

    $this->semester->refresh();
    expect((float) $this->semester->student_weight)->toEqual(40.0)
        ->and((float) $this->semester->dean_weight)->toEqual(20.0)
        ->and((float) $this->semester->ph_dh_weight)->toEqual(20.0)
        ->and((float) $this->semester->peer_weight)->toEqual(15.0)
        ->and((float) $this->semester->self_weight)->toEqual(5.0)
        ->and((float) $this->semester->overall_max_points)->toEqual(200.0);
});

test('individual reports filters teachers by search query and department', function () {
    $this->actingAs($this->adminUser);

    $deptBba = Department::create(['name' => 'College of Business', 'code' => 'CBA', 'type' => 'academic']);

    $emp1 = Employee::create(['employee_number' => 'F-001', 'first_name' => 'Alan', 'last_name' => 'Turing', 'role' => 'faculty', 'department_id' => $this->dept->id]);
    $u1 = User::create(['name' => 'Alan Turing', 'email' => 'alan@grc.edu.ph', 'employee_id' => $emp1->id, 'password' => 'password']);
    $u1->assignRole('faculty');

    $emp2 = Employee::create(['employee_number' => 'F-002', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'role' => 'faculty', 'department_id' => $deptBba->id]);
    $u2 = User::create(['name' => 'Grace Hopper', 'email' => 'grace@grc.edu.ph', 'employee_id' => $emp2->id, 'password' => 'password']);
    $u2->assignRole('faculty');

    Volt::test('reports')
        ->set('activeTab', 'individual')
        ->set('searchTeacher', 'Turing')
        ->assertSee('Alan Turing')
        ->assertDontSee('Grace Hopper')
        ->set('searchTeacher', '')
        ->set('selectedDepartment', (string) $deptBba->id)
        ->assertSee('Grace Hopper')
        ->assertDontSee('Alan Turing');
});

test('admin dashboard renders evaluation analytics visual charts', function () {
    $this->actingAs($this->adminUser);

    Livewire::withoutLazyLoading()
        ->actingAs($this->adminUser)
        ->test('admin.dashboard')
        ->assertSee('Completion Rate by Role')
        ->assertSee('Completion Rate by Department');
});

test('admin can edit questionnaire part name and max points via modal', function () {
    $this->actingAs($this->adminUser);

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Original Part Name',
        'max_points' => 25.0,
        'order' => 1,
    ]);

    Livewire::withoutLazyLoading()
        ->test('admin.evaluation-settings')
        ->call('openEditCriterionModal', $criterion->id)
        ->assertSet('showEditCriterionModal', true)
        ->assertSet('editCriterionName', 'Original Part Name')
        ->assertSet('editCriterionMaxPoints', '25')
        ->set('editCriterionName', 'Updated Mastery Part')
        ->set('editCriterionMaxPoints', '30')
        ->call('updateCriterion')
        ->assertSet('showEditCriterionModal', false)
        ->assertHasNoErrors();

    expect($criterion->fresh()->name)->toBe('Updated Mastery Part');
    expect((float) $criterion->fresh()->max_points)->toBe(30.0);
});
