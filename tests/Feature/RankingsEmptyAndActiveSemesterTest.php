<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();

    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'faculty']);

    $this->ay = AcademicYear::create(['name' => '2027-2028', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '2nd Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
    ]);

    $this->ccs = Department::create(['code' => 'CCS', 'name' => 'College of Computer Studies', 'type' => 'academic']);
    $this->coa = Department::create(['code' => 'COA', 'name' => 'College of Accountancy', 'type' => 'academic']);

    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    $this->fac1 = Employee::create([
        'employee_number' => 'FAC-001',
        'first_name' => 'Henry',
        'last_name' => 'Corrales',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->coa->id,
    ]);
    $this->facUser1 = User::create([
        'name' => 'Henry Corrales',
        'email' => 'henry@example.com',
        'employee_id' => $this->fac1->id,
        'password' => 'password',
    ]);
    $this->facUser1->assignRole('faculty');

    $this->fac2 = Employee::create([
        'employee_number' => 'FAC-002',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'role' => 'faculty',
        'status' => 'active',
        'department_id' => $this->ccs->id,
    ]);
    $this->facUser2 = User::create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'employee_id' => $this->fac2->id,
        'password' => 'password',
    ]);
    $this->facUser2->assignRole('faculty');
});

test('rankings component shows pending empty state when active semester has no evaluations', function () {
    $this->actingAs($this->adminUser);

    Livewire::test('rankings')
        ->assertSee('Rankings')
        ->assertSee('A.Y. 2027-2028 — 2nd Semester')
        ->assertSee('No evaluations submitted yet')
        ->assertSee('No Evaluations')
        ->assertSee('0 reviews')
        ->assertDontSee('Albert Einstein')
        ->assertDontSee('4.94');
});

test('rankings component calculates real evaluations when evaluations exist in active semester', function () {
    $this->actingAs($this->adminUser);

    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser1->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.80,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    Livewire::test('rankings')
        ->assertSee('Henry Corrales')
        ->assertSee('4.80')
        ->assertSee('1 review')
        ->assertSee('Outstanding')
        ->assertSee('COA');
});

test('top performing card stays highest rated and table ranks preserve true performance when sorted by lowest', function () {
    $this->actingAs($this->adminUser);

    // Henry: 4.80 (Rank 1)
    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser1->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.80,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    // Alice: 3.20 (Rank 2)
    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser2->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 3.20,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    $component = Livewire::test('rankings');

    // Default sort: highest
    $component->assertSee('Top Performing Faculty')
        ->assertSee('Henry Corrales');

    // Switch sort to 'lowest'
    $component->set('sortBy', 'lowest');

    // Card 1 must still display Henry as Top Performing Faculty
    $component->assertSee('Top Performing Faculty')
        ->assertSee('Henry Corrales');

    // In the rankings property, Alice is first in the list but retains rank 2
    $faculty = $component->get('facultyRankings');
    expect($faculty->first()->name)->toBe('Alice Smith');
    expect($faculty->first()->rank)->toBe(2);
    expect($faculty->last()->name)->toBe('Henry Corrales');
    expect($faculty->last()->rank)->toBe(1);
});

test('faculty leaderboard correctly paginates and preserves global institutional ranks across pages', function () {
    $this->actingAs($this->adminUser);

    // Evaluate the 2 faculty members from beforeEach
    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser1->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.95,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser2->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.90,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    // Create 12 additional faculty members with descending scores
    for ($i = 1; $i <= 12; $i++) {
        $emp = Employee::create([
            'employee_number' => sprintf('FAC-%03d', $i + 10),
            'first_name' => 'Faculty',
            'last_name' => "Member {$i}",
            'role' => 'faculty',
            'status' => 'active',
            'department_id' => $this->ccs->id,
        ]);

        $user = User::create([
            'name' => "Faculty Member {$i}",
            'email' => "fac{$i}@example.com",
            'employee_id' => $emp->id,
            'password' => 'password',
        ]);
        $user->assignRole('faculty');

        Evaluation::create([
            'evaluator_id' => $this->adminUser->id,
            'evaluatee_id' => $user->id,
            'semester_id' => $this->semester->id,
            'evaluation_type' => 'downward',
            'rating_average' => 4.80 - ($i * 0.1),
            'is_completed' => true,
            'submitted_at' => now(),
        ]);
    }

    $component = Livewire::test('rankings');

    // Page 1: 10 items per page (14 total evaluated faculty)
    $page1 = $component->get('facultyRankings');
    expect($page1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page1->total())->toBe(14);
    expect($page1->count())->toBe(10);
    expect($page1->first()->rank)->toBe(1);

    // Page 2: remaining 4 items, ranks must preserve global order 11..14
    $component->call('gotoPage', 2);
    $page2 = $component->get('facultyRankings');
    expect($page2->count())->toBe(4);
    expect($page2->first()->rank)->toBe(11);
    expect($page2->last()->rank)->toBe(14);
});

test('certificate modal can be triggered for top performing faculty', function () {
    $this->actingAs($this->adminUser);

    Evaluation::create([
        'evaluator_id' => $this->adminUser->id,
        'evaluatee_id' => $this->facUser1->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'downward',
        'rating_average' => 4.90,
        'is_completed' => true,
        'submitted_at' => now(),
    ]);

    Livewire::test('rankings')
        ->assertSet('showCertificateModal', false)
        ->call('previewCertificate', $this->fac1->id)
        ->assertSet('showCertificateModal', true)
        ->assertSet('certificateFacultyId', $this->fac1->id)
        ->assertSee('Certificate of')
        ->assertSee('Teaching Excellence')
        ->assertSee('Henry Corrales')
        ->assertSee('Institutional Rank #1')
        ->assertSee('Global Reciprocal Colleges');
});
