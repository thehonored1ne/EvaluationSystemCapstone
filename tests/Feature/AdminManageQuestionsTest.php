<?php

use App\Models\AcademicYear;
use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Semester;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'student']);
    Role::firstOrCreate(['name' => 'faculty']);
});

test('admin can access manage questions component and view tabs with live counts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Teaching Delivery',
        'order' => 1,
        'max_points' => 36.00,
    ]);

    EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Displays mastery of subject matter.',
        'order' => 1,
        'is_active' => true,
    ]);

    EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Encourages classroom participation.',
        'order' => 2,
        'is_active' => false,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->assertSee('Evaluation Questions Setup')
        ->assertSee('Teaching Delivery')
        ->assertSee('Displays mastery of subject matter.')
        ->assertSee('Encourages classroom participation.')
        ->assertSee('2 Questions');
});

test('admin can switch tabs and see category context and filtered criteria', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Student Criterion',
        'order' => 1,
        'max_points' => 30.00,
    ]);

    EvaluationCriterion::create([
        'evaluation_type' => 'dean',
        'name' => 'Dean Criterion',
        'order' => 1,
        'max_points' => 20.00,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->assertSee('Student Criterion')
        ->assertDontSee('Dean Criterion')
        ->call('selectTab', 'dean')
        ->assertSet('activeTab', 'dean')
        ->assertSee('Dean Criterion')
        ->assertDontSee('Student Criterion');
});

test('admin can create a new evaluation question with auto-incremented order', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Instructional Quality',
        'order' => 1,
        'max_points' => 40.00,
    ]);

    EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'First Question',
        'order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('openCreateModal')
        ->assertSet('showFormModal', true)
        ->set('criterionId', (string) $criterion->id)
        ->assertSet('order', '2')
        ->set('questionText', 'Explains complex topics clearly.')
        ->call('saveQuestion')
        ->assertSet('showFormModal', false);

    expect(EvaluationQuestion::where('question_text', 'Explains complex topics clearly.')->exists())->toBeTrue();
    $newQ = EvaluationQuestion::where('question_text', 'Explains complex topics clearly.')->first();
    expect($newQ->order)->toBe(2);
    expect($newQ->is_active)->toBeTrue();
});

test('admin can edit an existing question', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Assessment',
        'order' => 1,
        'max_points' => 25.00,
    ]);

    $question = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Old Question Prompt',
        'order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('openEditModal', $question->id)
        ->assertSet('showFormModal', true)
        ->assertSet('questionText', 'Old Question Prompt')
        ->set('questionText', 'Updated Question Prompt with More Clarity')
        ->call('saveQuestion')
        ->assertSet('showFormModal', false);

    expect($question->fresh()->question_text)->toBe('Updated Question Prompt with More Clarity');
});

test('admin can toggle question active status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Punctuality',
        'order' => 1,
        'max_points' => 10.00,
    ]);

    $question = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Starts and ends class on time.',
        'order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('toggleStatus', $question->id);

    expect($question->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('toggleStatus', $question->id);

    expect($question->fresh()->is_active)->toBeTrue();
});

test('admin can reorder questions using moveUp and moveDown', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Classroom Management',
        'order' => 1,
        'max_points' => 30.00,
    ]);

    $q1 = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Question 1',
        'order' => 1,
        'is_active' => true,
    ]);

    $q2 = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Question 2',
        'order' => 2,
        'is_active' => true,
    ]);

    // Move q2 up
    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('moveUp', $q2->id);

    expect($q2->fresh()->order)->toBe(1);
    expect($q1->fresh()->order)->toBe(2);

    // Move q2 back down
    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('moveDown', $q2->id);

    expect($q2->fresh()->order)->toBe(2);
    expect($q1->fresh()->order)->toBe(1);
});

test('question with submitted evaluation answers cannot be deleted and warns admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $evaluator = User::factory()->create();
    $evaluator->assignRole('student');
    $evaluatee = User::factory()->create();
    $evaluatee->assignRole('faculty');

    $ay = AcademicYear::create(['name' => '2025-2026', 'is_active' => true]);
    $sem = Semester::create([
        'academic_year_id' => $ay->id,
        'name' => '1st Semester',
        'is_active' => true,
    ]);

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Grading Integrity',
        'order' => 1,
        'max_points' => 30.00,
    ]);

    $question = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Grades fairly according to syllabus.',
        'order' => 1,
        'is_active' => true,
    ]);

    $evaluation = Evaluation::create([
        'evaluator_id' => $evaluator->id,
        'evaluatee_id' => $evaluatee->id,
        'semester_id' => $sem->id,
        'evaluation_type' => 'student',
        'rating_average' => 4.50,
        'raw_score' => 28.00,
        'max_score' => 30.00,
        'weighted_score' => 30.00,
    ]);

    EvaluationAnswer::create([
        'evaluation_id' => $evaluation->id,
        'question_id' => $question->id,
        'rating' => 5,
    ]);

    // Attempting to delete
    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('confirmDelete', $question->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingQuestionHasAnswers', true)
        ->call('deleteQuestion')
        ->assertSet('showDeleteModal', false);

    // Question must still exist
    expect($question->fresh())->not->toBeNull();
    expect($question->answers()->count())->toBe(1);

    // Admin can safely deactivate from modal instead
    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('confirmDelete', $question->id)
        ->call('deactivateFromModal')
        ->assertSet('showDeleteModal', false);

    expect($question->fresh()->is_active)->toBeFalse();
});

test('question without answers can be deleted and subsequent orders are normalized', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'General Ethics',
        'order' => 1,
        'max_points' => 20.00,
    ]);

    $q1 = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Question To Delete',
        'order' => 1,
        'is_active' => true,
    ]);

    $q2 = EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'Question To Shift Down',
        'order' => 2,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->call('confirmDelete', $q1->id)
        ->assertSet('deletingQuestionHasAnswers', false)
        ->call('deleteQuestion')
        ->assertSet('showDeleteModal', false);

    expect(EvaluationQuestion::find($q1->id))->toBeNull();
    expect($q2->fresh()->order)->toBe(1);
});

test('searching questions displays matching questions or global empty state', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $criterion = EvaluationCriterion::create([
        'evaluation_type' => 'student',
        'name' => 'Syllabus Coverage',
        'order' => 1,
        'max_points' => 20.00,
    ]);

    EvaluationQuestion::create([
        'criterion_id' => $criterion->id,
        'question_text' => 'UniqueKeywordQuestion appears here.',
        'order' => 1,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->set('search', 'UniqueKeyword')
        ->assertSee('UniqueKeywordQuestion appears here.')
        ->set('search', 'NonExistentXYZSearch')
        ->assertSee('No questions found matching')
        ->assertSee('NonExistentXYZSearch')
        ->assertSee('Clear Search');
});
