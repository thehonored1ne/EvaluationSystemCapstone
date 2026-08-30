<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'student']);
    Role::firstOrCreate(['name' => 'admin']);
});

test('guest accessing non-existent page receives modern custom 404 error page with Go Back action', function () {
    $response = $this->get('/non-existent-sample-page-xyz');

    $response->assertStatus(404);
    $response->assertSee('404 Error');
    $response->assertSee('Page not found');
    $response->assertSee('Go Back');
    $response->assertSee('GRC-o-Evaluation-LOGO.webp');
});

test('authenticated user accessing non-existent page receives modern 404 with Go Back action', function () {
    $user = User::create([
        'name' => 'Student One',
        'email' => 'student1@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('student');

    $response = $this->actingAs($user)->get('/some-invalid-portal-route-123');

    $response->assertStatus(404);
    $response->assertSee('404 Error');
    $response->assertSee('Page not found');
    $response->assertSee('Go Back');
});

test('unauthorized user accessing admin route receives modern custom 403 error page', function () {
    $user = User::create([
        'name' => 'Student Two',
        'email' => 'student2@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('student');

    $response = $this->actingAs($user)->get('/admin/subjects');

    $response->assertStatus(403);
    $response->assertSee('403 Forbidden');
    $response->assertSee('Access restricted');
    $response->assertSee('Go Back');
});
