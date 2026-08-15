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

test('admin can access manage questions component and view categories', function () {
    Livewire::withoutLazyLoading();
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Livewire::actingAs($admin)
        ->test('admin.manage-questions')
        ->assertSee('Evaluation Questions Setup')
        ->assertSee('Student')
        ->assertSee('Dean')
        ->assertSee('Program Head')
        ->assertSee('Department Head')
        ->assertSee('Peer')
        ->assertSee('Supervisor')
        ->assertSee('Self');
});
