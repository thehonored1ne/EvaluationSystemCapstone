<?php

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
