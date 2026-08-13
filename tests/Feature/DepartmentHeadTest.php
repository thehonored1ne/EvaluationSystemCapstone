<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Livewire::withoutLazyLoading();
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'dean']);
    Role::firstOrCreate(['name' => 'department head']);
    Role::firstOrCreate(['name' => 'program head']);
    Role::firstOrCreate(['name' => 'staff']);
});

test('department head role user can access department head dashboard', function () {
    $dept = Department::create([
        'code' => 'HRAD_TEST',
        'name' => 'Human Resources Test',
        'type' => 'administrative',
    ]);

    $employee = Employee::create([
        'employee_number' => 'DH-TEST-001',
        'first_name' => 'Dept',
        'last_name' => 'Head',
        'role' => 'department head',
        'status' => 'active',
        'department_id' => $dept->id,
    ]);

    $user = User::create([
        'name' => 'Dept Head',
        'email' => 'depthead.test@example.com',
        'employee_id' => $employee->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $user->assignRole('department head');

    $this->actingAs($user)
        ->get('/department-head/dashboard')
        ->assertStatus(200)
        ->assertSee('Department Head Dashboard');
});

test('admin can create administrative department with department head', function () {
    $adminEmp = Employee::create([
        'employee_number' => 'ADM-TEST-001',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'role' => 'admin',
        'status' => 'active',
    ]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin.test@example.com',
        'employee_id' => $adminEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('admin');

    $dhEmp = Employee::create([
        'employee_number' => 'DH-TEST-002',
        'first_name' => 'Head',
        'last_name' => 'Admin',
        'role' => 'department head',
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test('admin.manage-departments')
        ->set('code', 'FINANCE')
        ->set('name', 'Finance Department')
        ->set('type', 'administrative')
        ->set('department_head_id', (string) $dhEmp->id)
        ->call('saveDepartment')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('departments', [
        'code' => 'FINANCE',
        'name' => 'Finance Department',
        'type' => 'administrative',
        'department_head_id' => $dhEmp->id,
    ]);
});

test('admin can filter departments by department type', function () {
    $adminEmp = Employee::create([
        'employee_number' => 'ADM-TEST-002',
        'first_name' => 'Admin',
        'last_name' => 'FilterTest',
        'role' => 'admin',
        'status' => 'active',
    ]);

    $admin = User::create([
        'name' => 'Admin FilterTest',
        'email' => 'admin.filter@example.com',
        'employee_id' => $adminEmp->id,
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $admin->assignRole('admin');

    Department::create(['code' => 'ACAD_DEPT', 'name' => 'Academic College', 'type' => 'academic']);
    Department::create(['code' => 'ADMIN_DEPT', 'name' => 'Support Services', 'type' => 'administrative']);

    Livewire::actingAs($admin)
        ->test('admin.manage-departments')
        ->set('typeFilter', 'academic')
        ->assertSee('Academic College')
        ->assertDontSee('Support Services')
        ->set('typeFilter', 'administrative')
        ->assertSee('Support Services')
        ->assertDontSee('Academic College');
});
