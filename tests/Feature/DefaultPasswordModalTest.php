<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

test('isUsingDefaultPassword accurately identifies default password status', function () {
    $userWithDefault = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => null,
    ]);

    expect($userWithDefault->isUsingDefaultPassword())->toBeTrue();

    $userWithCustom = User::factory()->create([
        'password' => Hash::make('CustomSecure123!'),
        'password_changed_at' => null,
    ]);

    expect($userWithCustom->isUsingDefaultPassword())->toBeFalse();

    $userWithChangedTimestamp = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => now(),
    ]);

    expect($userWithChangedTimestamp->isUsingDefaultPassword())->toBeFalse();
});

test('default password modal is displayed for users with default password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user);

    $component = Volt::test('default-password-modal');
    $component->assertSee('Default Password In Use')
        ->assertSee('Security Advisory')
        ->assertSee('Change Password Now')
        ->assertSee('Remind Me Later');
});

test('default password modal is not displayed for users who have updated their password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('customPassword123'),
        'password_changed_at' => now(),
    ]);

    $this->actingAs($user);

    $component = Volt::test('default-password-modal');
    $component->assertDontSee('Default Password In Use');
});

test('dismissing the modal sets session flag and hides modal during session', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user);

    Volt::test('default-password-modal')
        ->call('dismissLater');

    expect(session('default_password_modal_dismissed'))->toBeTrue();

    Volt::test('default-password-modal')
        ->assertDontSee('Default Password In Use');
});

test('clicking change password sets session dismissal and redirects to settings.password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user);

    Volt::test('default-password-modal')
        ->call('goToChangePassword')
        ->assertRedirect(route('settings.password'));

    expect(session('default_password_modal_dismissed'))->toBeTrue();
});

test('updating password on settings page updates password_changed_at and resolves modal permanently', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'password_changed_at' => null,
    ]);

    $this->actingAs($user);

    expect($user->isUsingDefaultPassword())->toBeTrue();

    Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'NewSecurePass123!')
        ->set('password_confirmation', 'NewSecurePass123!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->password_changed_at)->not->toBeNull();
    expect($user->isUsingDefaultPassword())->toBeFalse();

    // In a fresh session without session dismissal, modal still should not show
    session()->forget('default_password_modal_dismissed');

    Volt::test('default-password-modal')
        ->assertDontSee('Default Password In Use');
});
