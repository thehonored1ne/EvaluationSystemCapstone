<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
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

    // Create academic context
    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDay(),
        'upward_student_max_points' => 5.0,
        'upward_employee_max_points' => 5.0,
        'downward_max_points' => 5.0,
        'peer_max_points' => 5.0,
        'self_max_points' => 5.0,
    ]);

    // Create Departments
    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies']);
    $this->cba = Department::create(['code' => 'CBA', 'name' => 'College of Business Administration']);

    // Admin user
    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    // Dean CCS
    $this->deanCCS = Employee::create(['employee_number' => 'D-01', 'first_name' => 'Dean', 'last_name' => 'CCS', 'role' => 'dean', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->deanUser = User::create(['name' => 'Dean CCS', 'email' => 'dean@example.com', 'employee_id' => $this->deanCCS->id, 'password' => 'password']);
    $this->deanUser->assignRole('dean');

    // Faculty CCS
    $this->facCCS = Employee::create(['employee_number' => 'F-01', 'first_name' => 'Faculty1', 'last_name' => 'CCS', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $this->facUserCCS = User::create(['name' => 'Faculty1 CCS', 'email' => 'fac1@example.com', 'employee_id' => $this->facCCS->id, 'password' => 'password']);
    $this->facUserCCS->assignRole('faculty');

    // Faculty CBA
    $this->facCBA = Employee::create(['employee_number' => 'F-02', 'first_name' => 'Faculty2', 'last_name' => 'CBA', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->cba->id]);
    $this->facUserCBA = User::create(['name' => 'Faculty2 CBA', 'email' => 'fac2@example.com', 'employee_id' => $this->facCBA->id, 'password' => 'password']);
    $this->facUserCBA->assignRole('faculty');
});

test('reports page contains reports Livewire component', function () {
    $this->actingAs($this->adminUser);
    $response = $this->get('/reports');
    $response->assertStatus(200);
    $response->assertSeeLivewire('reports');
});

test('reports component defaults to individual tab and switches to summary tab', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->assertSet('activeTab', 'individual')
        ->set('activeTab', 'summary')
        ->assertSet('activeTab', 'summary');
});

test('summary report renders academic department leaderboard for admin and dean', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Academic Department Rankings')
        ->assertSee('College of Computer Studies')
        ->assertSee('College of Business Administration');
});

test('summary report calculates institutional average and student average correctly', function () {
    // Student Evaluation for Faculty CCS
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.50,
    ]);

    // Student Evaluation for Faculty CBA
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCBA->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 3.50,
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('4.00') // Institutional Average: (4.50 + 3.50) / 2 = 4.00
        ->assertSee('Scope: All Academic Departments');
});

test('summary report flags faculty requiring attention below benchmark', function () {
    // Evaluation with low score and pacing comment for Faculty CBA
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCBA->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 2.80,
        'comments' => 'Ang mabilis magturo ni sir, please slow down lecture pacing.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Faculty Requiring Pedagogical Attention')
        ->assertSee('Faculty2 CBA')
        ->assertSee('2.80')
        ->assertSee('Critical')
        ->assertSee('lecture pacing');
});

test('summary report displays prescriptive recommendations and rating spread', function () {
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.80,
        'comments' => 'Very approachable and clear discussion, highly organized.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'summary')
        ->assertSet('selectedSemesterId', $this->semester->id)
        ->assertSee('Prescriptive AI Executive Insights')
        ->assertSee('Target Benchmark: 4.00')
        ->assertSee('Range:')
        ->assertSee('Across All Evaluations');
});

test('individual report renders without errors when selecting a professor', function () {
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.65,
        'comments' => 'Excellent teaching approach and active engagement.',
    ]);

    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->set('selectedTeacherId', $this->facCCS->id)
        ->assertSee('Summary of Faculty Performance Evaluation on Teaching Effectiveness')
        ->assertSee('Faculty1 CCS')
        ->assertSee('Global Reciprocal Colleges')
        ->assertSee('AI Qualitative Analysis')
        ->assertSee('Students Evaluation')
        // Assert Page 2 header signatories banner and extracts section were removed
        ->assertDontSee('Human Resource Manager')
        ->assertDontSee('Executive Director')
        ->assertDontSee('Representative Student Feedback Extracts');
});

test('individual tab supports batch print all for faculty members', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->assertSee('Print All')
        ->call('startPrintAll')
        ->assertSet('isPrintingAll', true)
        ->assertSet('batchPreviewIndex', 0)
        ->assertSee('Batch Print Hub')
        ->assertSee('Faculty1 CCS')
        ->call('nextBatchPreview')
        ->assertSet('batchPreviewIndex', 1)
        ->call('toggleBatchShowAll')
        ->assertSet('batchShowAllOnScreen', true)
        ->assertSee('Continuous Scroll Mode')
        ->call('exitPrintAll')
        ->assertSet('isPrintingAll', false)
        ->assertDontSee('Batch Print Hub');
});

test('individual report renders performance trend and recommendations on page 2', function () {
    // 1. First semester (baseline): no prior semester exists
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.50,
        'comments' => 'Great professor.',
    ]);

    $this->actingAs($this->adminUser);

    $component = Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->set('selectedTeacherId', $this->facCCS->id)
        ->assertSee('Performance of employee:')
        ->assertSee('Improving')
        ->assertSee('Stationary')
        ->assertSee('Deteriorating')
        ->assertSee('Recommendations for employee:')
        ->assertSee('Extension of Probationary period')
        ->assertSee('For Regularization')
        ->assertSee('Retention in present position')
        ->assertSee('Transfer to another position / department')
        ->assertSee('Salary Adjustment (%)')
        ->assertSee('Promotion in what position')
        ->assertSee('Separation from service');

    // For baseline with no previous semester, performance_trend is null
    $report = $component->get('individualReportData');
    expect($report->performance_trend)->toBeNull();
});

test('individual report correctly detects improving and deteriorating performance trends', function () {
    // Program Head user
    $phEmp = Employee::create(['employee_number' => 'PH-01', 'first_name' => 'PH', 'last_name' => 'CCS', 'role' => 'program head', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $phUser = User::create(['name' => 'PH CCS', 'email' => 'ph@example.com', 'employee_id' => $phEmp->id, 'password' => 'password']);
    $phUser->assignRole('program head');

    // Current semester with standard 200-scale weights
    $currentSem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '2nd Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'upward_student_max_points' => 80.0,
        'dean_max_points' => 40.0,
        'program_head_max_points' => 40.0,
        'peer_max_points' => 30.0,
        'self_max_points' => 10.0,
    ]);

    expect($currentSem->id)->toBeGreaterThan($this->semester->id);

    $evalTypes = [
        ['type' => 'upward_student', 'evaluator_id' => $this->adminUser->id],
        ['type' => 'dean', 'evaluator_id' => $this->deanUser->id],
        ['type' => 'program_head', 'evaluator_id' => $phUser->id],
        ['type' => 'peer', 'evaluator_id' => $this->facUserCBA->id],
        ['type' => 'self', 'evaluator_id' => $this->facUserCCS->id],
    ];

    // 1. Prior semester evaluations for facCCS: ~3.00 rating (lower)
    foreach ($evalTypes as $item) {
        Evaluation::create([
            'semester_id' => $this->semester->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $this->facUserCCS->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 3.00,
        ]);
    }

    // 2. Current semester evaluations for facCCS: ~4.80 rating (improving)
    foreach ($evalTypes as $item) {
        Evaluation::create([
            'semester_id' => $currentSem->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $this->facUserCCS->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 4.80,
        ]);
    }

    $this->actingAs($this->adminUser);

    $component = Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $currentSem->id)
        ->set('selectedTeacherId', $this->facCCS->id);

    $report = $component->get('individualReportData');
    expect($report->performance_trend)->toBe('improving');
    expect($report->score_growth)->toBeGreaterThan(0.50);

    // 3. Test deteriorating for facCBA: prior high (4.80) vs current low (2.00)
    $evalTypesCBA = [
        ['type' => 'upward_student', 'evaluator_id' => $this->adminUser->id],
        ['type' => 'dean', 'evaluator_id' => $this->deanUser->id],
        ['type' => 'program_head', 'evaluator_id' => $phUser->id],
        ['type' => 'peer', 'evaluator_id' => $this->facUserCCS->id],
        ['type' => 'self', 'evaluator_id' => $this->facUserCBA->id],
    ];

    foreach ($evalTypesCBA as $item) {
        Evaluation::create([
            'semester_id' => $this->semester->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $this->facUserCBA->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 4.80,
        ]);

        Evaluation::create([
            'semester_id' => $currentSem->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $this->facUserCBA->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 2.00,
        ]);
    }

    $component2 = Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $currentSem->id)
        ->set('selectedTeacherId', $this->facCBA->id);

    $report2 = $component2->get('individualReportData');
    expect($report2->performance_trend)->toBe('deteriorating');
    expect($report2->score_growth)->toBeLessThan(-0.50);

    // 4. Test stationary when scores are identical across semesters
    $facStationary = Employee::create(['employee_number' => 'F-03', 'first_name' => 'Faculty3', 'last_name' => 'Stationary', 'role' => 'faculty', 'status' => 'active', 'department_id' => $this->ccs->id]);
    $facUserStationary = User::create(['name' => 'Faculty3 Stationary', 'email' => 'fac3@example.com', 'employee_id' => $facStationary->id, 'password' => 'password']);
    $facUserStationary->assignRole('faculty');

    foreach ($evalTypes as $item) {
        Evaluation::create([
            'semester_id' => $this->semester->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $facUserStationary->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 4.00,
        ]);

        Evaluation::create([
            'semester_id' => $currentSem->id,
            'evaluator_id' => $item['evaluator_id'],
            'evaluatee_id' => $facUserStationary->id,
            'evaluation_type' => $item['type'],
            'rating_average' => 4.00,
        ]);
    }

    $component3 = Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $currentSem->id)
        ->set('selectedTeacherId', $facStationary->id);

    $report3 = $component3->get('individualReportData');
    expect($report3->performance_trend)->toBe('stationary');
    expect(abs($report3->score_growth))->toBeLessThanOrEqual(0.50);
});

test('reports component exports faculty evaluation summary to excel in alphabetical a-z order', function () {
    $this->actingAs($this->adminUser);

    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'upward_student',
        'rating_average' => 4.60,
        'comments' => 'Clear explanations and punctual.',
    ]);

    $response = Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->call('exportExcel');

    $response->assertFileDownloaded();

    // Capture streamed content
    $streamedResponse = $response->instance()->exportExcel();
    expect($streamedResponse)->not->toBeNull();

    ob_start();
    $streamedResponse->sendContent();
    $csvContent = ob_get_clean();

    // Check exact 10 headers
    expect($csvContent)->toContain('Professor Name')
        ->toContain('Department')
        ->toContain('Overall Rating')
        ->toContain('Descriptive Rating')
        ->toContain('Ranking')
        ->toContain('Dominant Sentiment')
        ->toContain('Top Commendations')
        ->toContain('Growth Areas')
        ->toContain('Performance Trend')
        ->toContain('Status');

    // Check data rows
    expect($csvContent)->toContain('Faculty1 CCS')
        ->toContain('Faculty2 CBA');

    // Faculty1 has 0 submitted forms, target is 1 (self eval) -> Incomplete
    expect($csvContent)->toContain('Incomplete');

    // Check alphabetical order: Faculty1 CCS appears before Faculty2 CBA
    $pos1 = strpos($csvContent, 'Faculty1 CCS');
    $pos2 = strpos($csvContent, 'Faculty2 CBA');
    expect($pos1)->toBeLessThan($pos2);

    // If Faculty1 submits required self evaluation, status turns to Completed
    Evaluation::create([
        'semester_id' => $this->semester->id,
        'evaluator_id' => $this->facUserCCS->id,
        'evaluatee_id' => $this->facUserCCS->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.50,
    ]);

    $streamedResponse2 = $response->instance()->exportExcel();
    ob_start();
    $streamedResponse2->sendContent();
    $csvContent2 = ob_get_clean();
    expect($csvContent2)->toContain('Completed');
});

test('individual report view displays Print PDF button when teacher selected', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('reports')
        ->set('activeTab', 'individual')
        ->set('selectedSemesterId', $this->semester->id)
        ->set('selectedTeacherId', $this->facCCS->id)
        ->assertSee('Print PDF')
        ->assertDontSee('Save as PDF');
});

test('reports component filters teachers strictly to faculty members excluding program heads and deans', function () {
    $this->actingAs($this->adminUser);

    $phEmp = Employee::create([
        'employee_number' => 'PH-01',
        'first_name' => 'ProgramHead',
        'last_name' => 'CCS',
        'role' => 'program head',
        'status' => 'active',
        'department_id' => $this->ccs->id,
    ]);

    $component = Livewire::test('reports')
        ->set('activeTab', 'individual');

    $teachers = $component->get('teachers');

    expect($teachers->pluck('id'))->toContain($this->facCCS->id)
        ->toContain($this->facCBA->id)
        ->not->toContain($this->deanCCS->id)
        ->not->toContain($phEmp->id);
});
