<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Livewire::withoutLazyLoading();
    // Roles
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'faculty']);

    $this->adminUser = User::create(['name' => 'Admin', 'email' => 'admin.ai@example.com', 'password' => 'password']);
    $this->adminUser->assignRole('admin');

    $this->dept = Department::create(['code' => 'CCS', 'name' => 'Computer Studies']);
    $this->facEmp = Employee::create(['employee_number' => 'FAC-90', 'first_name' => 'John', 'last_name' => 'Doe', 'role' => 'faculty', 'department_id' => $this->dept->id]);
    $this->facUser = User::create(['name' => 'John Doe', 'email' => 'john.ai@example.com', 'employee_id' => $this->facEmp->id, 'password' => 'password']);

    $this->ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $this->semester = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);
});

test('admin can override sentiment label manually in manage-ai component', function () {
    $this->actingAs($this->adminUser);

    $evaluation = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 3.50,
        'comments' => 'Nice class.',
    ]);

    $sentiment = EvaluationSentiment::create([
        'evaluation_id' => $evaluation->id,
        'vader_score' => 0.40,
        'vader_label' => 'positive',
        'dt_label' => 'positive',
    ]);

    expect($sentiment->manual_label)->toBeNull();
    expect($sentiment->active_label)->toBe('positive');

    // Call setManualLabel on the manage-ai component
    Volt::test('admin.manage-ai')
        ->call('setManualLabel', $evaluation->id, 'negative');

    $sentiment->refresh();
    expect($sentiment->manual_label)->toBe('negative');
    expect($sentiment->active_label)->toBe('negative');
});

test('ai:train command submits manual labels and rating averages to Flask and caches metrics', function () {
    $this->actingAs($this->adminUser);

    $evaluation = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.80,
        'comments' => 'Brilliant teaching!',
    ]);

    $sentiment = EvaluationSentiment::create([
        'evaluation_id' => $evaluation->id,
        'vader_score' => 0.90,
        'vader_label' => 'positive',
        'dt_label' => 'positive',
        'manual_label' => 'positive',
    ]);

    Http::fake([
        'http://127.0.0.1:5001/train' => Http::response([
            'status' => 'success',
            'samples_trained' => 23,
            'db_samples' => 1,
            'seed_samples' => 22,
            'metrics' => [
                'accuracy' => 0.95,
                'confusion_matrix' => [
                    'positive' => ['positive' => 5, 'neutral' => 0, 'negative' => 0],
                    'neutral' => ['positive' => 0, 'neutral' => 3, 'negative' => 0],
                    'negative' => ['positive' => 0, 'neutral' => 0, 'negative' => 4],
                ],
            ],
        ], 200),
    ]);

    // Run the training command
    $this->artisan('ai:train')
        ->assertExitCode(0);

    // Verify metrics are cached in storage
    $metricsPath = storage_path('app/ai_metrics.json');
    expect(file_exists($metricsPath))->toBeTrue();

    $metrics = json_decode(file_get_contents($metricsPath), true);
    expect($metrics['accuracy'])->toBe(0.95);
    expect($metrics['confusion_matrix']['positive']['positive'])->toBe(5);

    // Assert that correct payload structure was sent to Flask
    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'http://127.0.0.1:5001/train' &&
               isset($data['samples']) &&
               count($data['samples']) === 1 &&
               $data['samples'][0]['comment'] === 'Brilliant teaching!' &&
               $data['samples'][0]['rating'] === 4.80 &&
               $data['samples'][0]['manual_label'] === 'positive';
    });
});

test('evaluation sentiment calculates confidence level and detects conflicts accurately', function () {
    $evalNormal = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.50,
        'comments' => 'Outstanding instruction and clarity.',
    ]);

    $sentimentNormal = EvaluationSentiment::create([
        'evaluation_id' => $evalNormal->id,
        'vader_score' => 0.85,
        'vader_label' => 'positive',
        'dt_label' => 'positive',
    ]);

    expect($sentimentNormal->confidence_level)->toBe('High');
    expect($sentimentNormal->is_conflicted)->toBeFalse();

    // Conflicted evaluation: VADER says negative, but DT says positive
    $evalConflicted = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.80,
        'comments' => 'Worst professor, completely unapproachable.',
    ]);

    $sentimentConflicted = EvaluationSentiment::create([
        'evaluation_id' => $evalConflicted->id,
        'vader_score' => -0.75,
        'vader_label' => 'negative',
        'dt_label' => 'positive',
    ]);

    expect($sentimentConflicted->is_conflicted)->toBeTrue();
    expect($sentimentConflicted->confidence_level)->toBe('Low (Conflict)');

    // Once manually overridden, confidence is Human Verified
    $sentimentConflicted->update(['manual_label' => 'negative']);
    expect($sentimentConflicted->confidence_level)->toBe('Human Verified');
    expect($sentimentConflicted->is_conflicted)->toBeFalse();
});

test('manage-ai component filters by needs_review and overridden correctly', function () {
    $this->actingAs($this->adminUser);

    // Normal review
    $evalNormal = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 5.0,
        'comments' => 'Very clear and inspiring.',
    ]);
    EvaluationSentiment::create([
        'evaluation_id' => $evalNormal->id,
        'vader_score' => 0.8,
        'vader_label' => 'positive',
        'dt_label' => 'positive',
    ]);

    // Conflicted review (VADER negative, DT positive)
    $evalConflicted = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 4.9,
        'comments' => 'Unacceptable behavior in class.',
    ]);
    EvaluationSentiment::create([
        'evaluation_id' => $evalConflicted->id,
        'vader_score' => -0.8,
        'vader_label' => 'negative',
        'dt_label' => 'positive',
    ]);

    // Overridden review
    $evalOverridden = Evaluation::create([
        'evaluator_id' => $this->facUser->id,
        'evaluatee_id' => $this->facUser->id,
        'semester_id' => $this->semester->id,
        'evaluation_type' => 'self',
        'rating_average' => 3.0,
        'comments' => 'Just standard lectures.',
    ]);
    EvaluationSentiment::create([
        'evaluation_id' => $evalOverridden->id,
        'vader_score' => 0.0,
        'vader_label' => 'neutral',
        'dt_label' => 'positive',
        'manual_label' => 'neutral',
    ]);

    // Filter by needs_review
    Volt::test('admin.manage-ai')
        ->set('selectedFilter', 'needs_review')
        ->assertSee('Unacceptable behavior in class.')
        ->assertDontSee('Very clear and inspiring.')
        ->assertDontSee('Just standard lectures.');

    // Filter by overridden
    Volt::test('admin.manage-ai')
        ->set('selectedFilter', 'overridden')
        ->assertSee('Just standard lectures.')
        ->assertDontSee('Very clear and inspiring.')
        ->assertDontSee('Unacceptable behavior in class.');

    // Filter by misclassified
    Volt::test('admin.manage-ai')
        ->set('selectedFilter', 'misclassified');
});

test('admin can upload benchmark dataset and evaluate model in real-time with zero database persistence', function () {
    $this->actingAs($this->adminUser);

    Http::fake([
        config('services.ai.url').'/analyze' => Http::response([
            'results' => [
                [
                    'comment' => 'Great teaching style.',
                    'dt_label' => 'positive',
                    'vader_label' => 'positive',
                    'confidence' => 'High',
                ],
                [
                    'comment' => 'Always late to class.',
                    'dt_label' => 'negative',
                    'vader_label' => 'negative',
                    'confidence' => 'High',
                ],
            ],
        ], 200),
    ]);

    $csvContent = "comment,rating,ground_truth\n\"Great teaching style.\",4.8,positive\n\"Always late to class.\",1.5,negative\n";
    $file = UploadedFile::fake()->createWithContent('benchmark_test.csv', $csvContent);

    $initialEvaluationCount = Evaluation::count();
    $initialSentimentCount = EvaluationSentiment::count();

    Volt::test('admin.manage-ai')
        ->set('benchmarkFile', $file)
        ->call('runBenchmark')
        ->assertHasNoErrors()
        ->assertSee('Benchmark Accuracy')
        ->assertSee('100.0%') // 2/2 correct
        ->assertSee('MATCH')
        ->assertSee('Great teaching style.')
        ->assertSee('Always late to class.')
        ->call('resetBenchmark')
        ->assertSet('benchmarkFile', null)
        ->assertSet('benchmarkResults', null);

    // Verify zero database pollution (100% ephemeral)
    expect(Evaluation::count())->toBe($initialEvaluationCount);
    expect(EvaluationSentiment::count())->toBe($initialSentimentCount);
});

test('admin can download sample benchmark template even if file does not exist on disk', function () {
    $this->actingAs($this->adminUser);

    $templatePath = storage_path('app/benchmark_template.csv');
    if (File::exists($templatePath)) {
        File::delete($templatePath);
    }

    $response = Volt::test('admin.manage-ai')
        ->call('downloadTemplate');

    $response->assertFileDownloaded('benchmark_template.csv');

    // Self-healing check: verifies file was written to disk as fallback
    expect(File::exists($templatePath))->toBeTrue();
});
