<?php

use App\Models\AcademicYear;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use App\Models\Semester;
use App\Models\User;
use App\Services\ThematicAnalysisService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    $this->ay = AcademicYear::create(['name' => '2026-2027', 'is_active' => true]);
});

test('thematic analysis service gracefully handles empty semester', function () {
    $sem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $drivers = ThematicAnalysisService::getThematicDrivers($sem->id);

    expect($drivers['has_data'])->toBeFalse()
        ->and($drivers['total_analyzed'])->toBe(0)
        ->and($drivers['positive_drivers'])->toBeEmpty()
        ->and($drivers['constructive_drivers'])->toBeEmpty();
});

test('thematic analysis service identifies recurring praise and action items', function () {
    $sem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $evaluator = User::factory()->create();
    $evaluatee = User::factory()->create();

    // Create 6 positive evaluations
    for ($i = 0; $i < 6; $i++) {
        $eval = Evaluation::create([
            'evaluator_id' => $evaluator->id,
            'evaluatee_id' => $evaluatee->id,
            'semester_id' => $sem->id,
            'evaluation_type' => 'upward_student',
            'comments' => 'Always prepared, very clear explanations, and patient with questions.',
            'rating_average' => 4.8,
            'raw_score' => 96,
            'max_score' => 100,
            'weighted_score' => 4.8,
        ]);

        EvaluationSentiment::create([
            'evaluation_id' => $eval->id,
            'vader_score' => 0.75,
            'vader_label' => 'positive',
            'dt_label' => 'positive',
        ]);
    }

    // Create 6 constructive evaluations
    for ($i = 0; $i < 6; $i++) {
        $eval = Evaluation::create([
            'evaluator_id' => $evaluator->id,
            'evaluatee_id' => $evaluatee->id,
            'semester_id' => $sem->id,
            'evaluation_type' => 'upward_student',
            'comments' => 'Delayed return of quizzes and class pacing was a bit too fast.',
            'rating_average' => 2.5,
            'raw_score' => 50,
            'max_score' => 100,
            'weighted_score' => 2.5,
        ]);

        EvaluationSentiment::create([
            'evaluation_id' => $eval->id,
            'vader_score' => -0.45,
            'vader_label' => 'negative',
            'dt_label' => 'negative',
        ]);
    }

    $drivers = ThematicAnalysisService::getThematicDrivers($sem->id);

    expect($drivers['has_data'])->toBeTrue()
        ->and($drivers['total_analyzed'])->toBe(12)
        ->and(count($drivers['positive_drivers']))->toBeGreaterThan(0)
        ->and(count($drivers['constructive_drivers']))->toBeGreaterThan(0);
});

test('admin dashboard renders thematic drivers section when data is available', function () {
    $sem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $evaluatee = User::factory()->create();

    for ($i = 0; $i < 6; $i++) {
        $eval = Evaluation::create([
            'evaluator_id' => $admin->id,
            'evaluatee_id' => $evaluatee->id,
            'semester_id' => $sem->id,
            'evaluation_type' => 'upward_student',
            'comments' => 'Clear explanations and always prepared.',
            'rating_average' => 4.5,
            'raw_score' => 90,
            'max_score' => 100,
            'weighted_score' => 4.5,
        ]);

        EvaluationSentiment::create([
            'evaluation_id' => $eval->id,
            'vader_score' => 0.65,
            'vader_label' => 'positive',
            'dt_label' => 'positive',
        ]);
    }

    $this->actingAs($admin);

    Livewire::test('admin.dashboard')
        ->assertSee('Key Feedback Highlights')
        ->assertSee('Top Strengths');
});
