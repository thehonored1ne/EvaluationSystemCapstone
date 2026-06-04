<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('admin user can update their employee number in profile', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Admin User')
        ->set('email', 'admin@example.com')
        ->set('employee_number', 'ADMIN-TEST-99')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toEqual('Admin User');
    expect($user->email)->toEqual('admin@example.com');
    expect($user->employee)->not->toBeNull();
    expect($user->employee->employee_number)->toEqual('ADMIN-TEST-99');
    expect($user->employee->role)->toEqual('admin');
    expect($user->employee->department_id)->toBeNull();
});