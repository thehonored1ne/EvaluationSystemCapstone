<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('creating a user logs activity', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'description' => 'created',
        'log_name' => 'user',
    ]);

    $activity = Activity::latest()->first();
    expect($activity->properties['attributes']['name'])->toBe('John Doe')
        ->and($activity->properties['attributes']['email'])->toBe('john@example.com');
});

test('updating a user logs only dirty attributes and ignores password updates', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
    ]);

    // Clear previous logs to isolate update logs
    Activity::truncate();

    // Perform update on name (dirty attribute) and password (should be ignored)
    $user->update([
        'name' => 'Jane Doe',
        'password' => 'newsecret123',
    ]);

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'description' => 'updated',
        'log_name' => 'user',
    ]);

    $activity = Activity::latest()->first();

    // It should have logged the new name
    expect($activity->properties['attributes']['name'])->toBe('Jane Doe');

    // It should NOT log the password change (ignored attribute)
    expect($activity->properties['old'])->not->toHaveKey('password')
        ->and($activity->properties['attributes'])->not->toHaveKey('password');
});

test('admin dashboard renders audit log card with activities and timestamps', function () {
    Role::firstOrCreate(['name' => 'admin']);
    $admin = User::factory()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@grc.edu.ph',
    ]);
    $admin->assignRole('admin');

    // Create an audited department action
    Department::create([
        'name' => 'College of Engineering',
        'code' => 'COE',
        'type' => 'academic',
    ]);

    Livewire::withoutLazyLoading()
        ->actingAs($admin)
        ->test('admin.dashboard')
        ->assertSee('Audit Log')
        ->assertSee('System Activity & Progress Logs', false)
        ->assertSee('Created Department')
        ->assertSee('Added new department: College of Engineering (COE)');
});

test('user model ignores notification viewed timestamp and dismissed notification changes', function () {
    $user = User::factory()->create([
        'name' => 'Admin Test',
        'email' => 'admintest@grc.edu.ph',
    ]);

    Activity::truncate();

    $user->update([
        'notifications_last_viewed_at' => now(),
        'dismissed_notifications' => ['notif-1'],
    ]);

    expect(Activity::count())->toBe(0);
});
