<?php

use App\Models\Profanity;
use App\Models\User;
use App\Services\ProfanityFilterService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'student']);
});

test('guest is redirected away from manage profanity page', function () {
    $this->get(route('admin.profanity'))
        ->assertRedirect(route('login'));
});

test('non-admin user is forbidden from accessing manage profanity page', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $this->actingAs($student)
        ->get(route('admin.profanity'))
        ->assertForbidden();
});

test('admin can access manage profanity page and view library stats and words', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Profanity::firstOrCreate(
        ['word' => 'testcustomslur'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->assertSee('Profanity Filter Library')
        ->assertSee('Interactive Filter Sandbox')
        ->assertSee('testcustomslur');
});

test('admin can create a single new profanity word', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->set('newWord', 'bawalnasalita')
        ->set('newCategory', 'custom')
        ->set('newIsActive', true)
        ->call('createSingle')
        ->assertHasNoErrors();

    expect(Profanity::where('word', 'bawalnasalita')->exists())->toBeTrue()
        ->and(Profanity::getActiveWords())->toContain('bawalnasalita');
});

test('admin cannot create duplicate profanity words', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Profanity::firstOrCreate(
        ['word' => 'salitangbawal'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->set('newWord', 'salitangbawal')
        ->set('newCategory', 'custom')
        ->call('createSingle')
        ->assertHasErrors(['newWord']);
});

test('admin can bulk import multiple profanity words with deduplication', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Profanity::firstOrCreate(
        ['word' => 'existingword'],
        ['category' => 'custom', 'is_active' => true]
    );

    $bulkInput = "existingword, newwordone\nnewwordtwo; newwordthree";

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->set('bulkWords', $bulkInput)
        ->set('bulkCategory', 'custom')
        ->call('createBulk')
        ->assertHasNoErrors();

    expect(Profanity::where('word', 'newwordone')->exists())->toBeTrue()
        ->and(Profanity::where('word', 'newwordtwo')->exists())->toBeTrue()
        ->and(Profanity::where('word', 'newwordthree')->exists())->toBeTrue();
});

test('admin can edit an existing profanity word', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $profanity = Profanity::firstOrCreate(
        ['word' => 'lumangsalita'],
        ['category' => 'tagalog', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->call('openEditModal', $profanity->id)
        ->assertSet('editWord', 'lumangsalita')
        ->set('editWord', 'bagongsalita')
        ->set('editCategory', 'custom')
        ->call('updateProfanity')
        ->assertHasNoErrors();

    expect(Profanity::where('word', 'bagongsalita')->exists())->toBeTrue()
        ->and(Profanity::where('word', 'lumangsalita')->exists())->toBeFalse();
});

test('admin can toggle active status of a profanity word', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $profanity = Profanity::firstOrCreate(
        ['word' => 'toggletestword'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->call('toggleStatus', $profanity->id);

    expect($profanity->fresh()->is_active)->toBeFalse()
        ->and(Profanity::getActiveWords())->not->toContain('toggletestword');

    // Toggle back to active
    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->call('toggleStatus', $profanity->id);

    expect($profanity->fresh()->is_active)->toBeTrue()
        ->and(Profanity::getActiveWords())->toContain('toggletestword');
});

test('admin can delete a profanity word', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $profanity = Profanity::firstOrCreate(
        ['word' => 'deletemetestword'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->call('confirmDelete', $profanity->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteProfanity');

    expect(Profanity::where('word', 'deletemetestword')->exists())->toBeFalse()
        ->and(Profanity::getActiveWords())->not->toContain('deletemetestword');
});

test('interactive sandbox detects prohibited words and cleans input in real time', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Profanity::firstOrCreate(
        ['word' => 'testinsult'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->set('testInput', 'You are a testinsult in this class')
        ->assertSet('testHasProfanity', true)
        ->assertSet('testMatchedWords', ['testinsult'])
        ->assertSet('testCleanedText', 'You are a in this class');
});

test('profanity filter service dynamically recognizes newly added active database words', function () {
    Profanity::firstOrCreate(
        ['word' => 'dynamictestbadword'],
        ['category' => 'custom', 'is_active' => true]
    );

    $service = app(ProfanityFilterService::class);
    $input = 'This class has dynamictestbadword content';

    expect($service->hasProfanity($input))->toBeTrue()
        ->and($service->clean($input))->toBe('This class has content');
});

test('profanity filter service ignores deactivated database words', function () {
    $word = Profanity::firstOrCreate(
        ['word' => 'inactivecustomword'],
        ['category' => 'custom', 'is_active' => true]
    );
    $word->update(['is_active' => false]);

    $service = app(ProfanityFilterService::class);
    $input = 'This sentence contains inactivecustomword and should not be filtered';

    expect($service->hasProfanity($input))->toBeFalse()
        ->and($service->clean($input))->toBe($input);
});

test('admin can filter words by structure type single word versus phrase', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Profanity::firstOrCreate(
        ['word' => 'soloword'],
        ['category' => 'custom', 'is_active' => true]
    );

    Profanity::firstOrCreate(
        ['word' => 'multi word phrase'],
        ['category' => 'custom', 'is_active' => true]
    );

    Livewire::actingAs($admin)
        ->test('admin.manage-profanity')
        ->set('typeFilter', 'single')
        ->assertSee('soloword')
        ->assertDontSee('multi word phrase')
        ->set('typeFilter', 'phrase')
        ->assertSee('multi word phrase')
        ->assertDontSee('soloword');
});
