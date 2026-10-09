<?php

use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('identifier', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('users can authenticate using student number', function () {
    $student = Student::create([
        'student_number' => '2026-01-0001',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $user = User::factory()->create([
        'student_id' => $student->id,
        'password' => bcrypt('password'),
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('identifier', '2026-01-0001')
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users can authenticate using employee number', function () {
    $employee = Employee::create([
        'employee_number' => 'FAC-001',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'role' => 'faculty',
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
        'password' => bcrypt('password'),
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('identifier', 'FAC-001')
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('login with non-existent student or employee identifier fails gracefully without 500 error', function () {
    $response = LivewireVolt::test('auth.login')
        ->set('identifier', 'UNKNOWN-ID-999')
        ->set('password', 'password')
        ->call('login');

    $response->assertHasErrors(['identifier']);
    $this->assertGuest();
});
