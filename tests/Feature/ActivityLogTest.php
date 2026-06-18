<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

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
