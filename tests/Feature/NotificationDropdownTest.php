<?php

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->ay = AcademicYear::create([
        'name' => '2026-2027',
        'is_active' => true,
    ]);

    $this->sem = Semester::create([
        'academic_year_id' => $this->ay->id,
        'name' => '1st Semester',
        'is_active' => true,
        'is_evaluation_open' => true,
        'evaluation_starts_at' => now()->subDay(),
        'evaluation_ends_at' => now()->addDays(7),
    ]);

    $this->user = User::factory()->create([
        'is_active' => true,
    ]);
});

test('user can view notifications in the dropdown component', function () {
    $this->actingAs($this->user);

    $component = Volt::test('notification-dropdown');
    $component->assertSee('Evaluations are Open');
});

test('user can dismiss a single notification', function () {
    $this->actingAs($this->user);

    $notifs = $this->user->getNotifications();
    expect($notifs)->not->toBeEmpty();
    $firstId = $notifs[0]->id;

    $component = Volt::test('notification-dropdown');
    $component->call('dismiss', $firstId);

    $this->user->refresh();
    expect($this->user->dismissed_notifications)->toContain($firstId);
    expect($this->user->getNotifications())->toBeEmpty();
});

test('user can clear all notifications at once', function () {
    $this->actingAs($this->user);

    expect($this->user->getNotifications())->not->toBeEmpty();

    $component = Volt::test('notification-dropdown');
    $component->call('clearAll');

    $this->user->refresh();
    expect($this->user->getNotifications())->toBeEmpty();
    expect($this->user->notifications_last_viewed_at)->not->toBeNull();
});
