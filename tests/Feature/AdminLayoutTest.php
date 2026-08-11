<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'student']);
});

test('admin dashboard renders admin navbar and footer', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertSee('Logged as Admin');
    $response->assertSee('Academic Evaluation System');
    $response->assertSee('Notifications');
    $response->assertSee('Global Reciprocal Colleges');
});

test('evaluators see their role navbar badge and footer', function () {
    $student = User::factory()->create();
    $student->assignRole('student');

    $response = $this->actingAs($student)->get(route('student.dashboard'));

    $response->assertStatus(200);
    $response->assertDontSee('Logged as Admin');
    $response->assertSee('Logged as Student');
    $response->assertSee('Academic Evaluation System');
    $response->assertSee('Global Reciprocal Colleges');
});
