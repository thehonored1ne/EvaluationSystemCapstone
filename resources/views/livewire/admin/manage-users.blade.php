<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\User;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Common fields
    public string $email = '';
    public string $password = '';
    public string $userType = 'student'; // student or employee
    public bool $showModal = false;
    public ?User $editingUser = null;

    // Student fields
    public string $student_number = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $program_id = '';
    public string $year_level = '';
    public string $section = '';

    // Employee fields
    public string $employee_number = '';
    public string $employee_role = 'faculty';

    public string $search = '';
    public string $filterRole = '';
    public string $filterStatus = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedFilterRole() { $this->resetPage(); }
    public function updatedFilterStatus() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'filterRole', 'filterStatus']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset([
            'email', 'password', 'userType', 'editingUser',
            'student_number', 'first_name', 'last_name', 'program_id', 'year_level', 'section',
            'employee_number', 'employee_role'
        ]);
        $this->userType = 'student';
        $this->employee_role = 'faculty';
        $this->showModal = true;
    }

    public function with(): array
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhereHas('student', function ($sub) {
                      $sub->where('student_number', 'like', '%' . $this->search . '%')
                          ->orWhere('section', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('employee', function ($sub) {
                      $sub->where('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterRole) {
            $query->role($this->filterRole);
        }

        if ($this->filterStatus !== '') {
            $query->where('is_active', $this->filterStatus === 'active');
        }

        return [
            'users' => $query->orderBy('id')->paginate(10),
            'roles' => \Spatie\Permission\Models\Role::all(),
            'programs' => \App\Models\Program::orderBy('name')->get(),
        ];
    }

    public function createUser()
    {
        if ($this->userType === 'student') {
            $this->validate([
                'student_number' => 'required|string|unique:students,student_number',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'program_id' => 'required|exists:programs,id',
                'year_level' => 'required|integer|between:1,4',
                'section' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8',
            ]);

            $student = \App\Models\Student::create([
                'student_number' => $this->student_number,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'program_id' => $this->program_id,
                'year_level' => $this->year_level,
                'section' => $this->section ?: null,
                'status' => 'regular',
            ]);

            $user = User::create([
                'name' => $this->first_name . ' ' . $this->last_name,
                'email' => $this->email,
                'student_id' => $student->id,
                'password' => bcrypt($this->password),
                'is_active' => true,
            ]);

            $user->assignRole('student');
        } else {
            $this->validate([
                'employee_number' => 'required|string|unique:employees,employee_number',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'employee_role' => 'required|in:admin,dean,program head,faculty,staff',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:8',
            ]);

            $employee = \App\Models\Employee::create([
                'employee_number' => $this->employee_number,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'role' => $this->employee_role,
                'status' => 'active',
            ]);

            $user = User::create([
                'name' => $this->first_name . ' ' . $this->last_name,
                'email' => $this->email,
                'employee_id' => $employee->id,
                'password' => bcrypt($this->password),
                'is_active' => true,
            ]);

            $user->assignRole($this->employee_role);
        }

        $this->reset([
            'email', 'password', 'userType', 'showModal',
            'student_number', 'first_name', 'last_name', 'program_id', 'year_level', 'section',
            'employee_number', 'employee_role'
        ]);
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->email = $user->email;
        $this->password = '';

        if ($user->student_id) {
            $this->userType = 'student';
            $this->student_number = $user->student->student_number ?? '';
            $this->first_name = $user->student->first_name ?? '';
            $this->last_name = $user->student->last_name ?? '';
            $this->program_id = $user->student->program_id ?? '';
            $this->year_level = $user->student->year_level ?? '';
            $this->section = $user->student->section ?? '';
        } else {
            $this->userType = 'employee';
            $this->employee_number = $user->employee->employee_number ?? '';
            $this->first_name = $user->employee->first_name ?? '';
            $this->last_name = $user->employee->last_name ?? '';
            $this->employee_role = $user->employee->role ?? 'faculty';
        }
        $this->showModal = true;
    }

    public function updateUser()
    {
        if ($this->userType === 'student') {
            $this->validate([
                'student_number' => 'required|string|unique:students,student_number,' . $this->editingUser->student_id,
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'program_id' => 'required|exists:programs,id',
                'year_level' => 'required|integer|between:1,4',
                'section' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
                'password' => 'nullable|min:8',
            ]);

            $this->editingUser->student->update([
                'student_number' => $this->student_number,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'program_id' => $this->program_id,
                'year_level' => $this->year_level,
                'section' => $this->section ?: null,
            ]);

            $this->editingUser->update([
                'name' => $this->first_name . ' ' . $this->last_name,
                'email' => $this->email,
            ]);
        } else {
            $this->validate([
                'employee_number' => 'required|string|unique:employees,employee_number,' . $this->editingUser->employee_id,
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'employee_role' => 'required|in:admin,dean,program head,faculty,staff',
                'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
                'password' => 'nullable|min:8',
            ]);

            $this->editingUser->employee->update([
                'employee_number' => $this->employee_number,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'role' => $this->employee_role,
            ]);

            $this->editingUser->update([
                'name' => $this->first_name . ' ' . $this->last_name,
                'email' => $this->email,
            ]);

            // Sync Spatie role
            $this->editingUser->roles()->detach();
            $this->editingUser->assignRole($this->employee_role);
        }

        if ($this->password) {
            $this->editingUser->update(['password' => bcrypt($this->password)]);
        }

        $this->reset([
            'email', 'password', 'userType', 'showModal', 'editingUser',
            'student_number', 'first_name', 'last_name', 'program_id', 'year_level', 'section',
            'employee_number', 'employee_role'
        ]);
    }

    public function toggleActive(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl" level="1">Manage Users</flux:heading>
        <flux:button variant="primary" wire:click="prepareCreate">Create User</flux:button>
    </div>
    
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or ID..." />
        </div>
        
        <div class="w-full md:w-48">
            <flux:select wire:model.live="filterRole" placeholder="All Roles">
                <flux:select.option value="">All Roles</flux:select.option>
                @foreach($roles as $role)
                    <flux:select.option value="{{ $role->name }}">{{ ucfirst($role->name) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-48">
            <flux:select wire:model.live="filterStatus" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="disabled">Disabled</flux:select.option>
            </flux:select>
        </div>

        <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
    </div>
    
    <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700">
        <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
            <thead class="bg-gray-50 dark:bg-zinc-800">
                <tr>
                    <th class="w-[35%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Name</th>
                    <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">ID</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Roles</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Status</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                @foreach ($users as $user)
                    <tr wire:key="{{ $user->id }}">
                        <td class="px-4 py-3 dark:text-zinc-300">{{ $user->name }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300">
                            @if($user->student)
                                {{ $user->student->student_number }}
                                @if($user->student->section)
                                    <span class="text-xs text-zinc-500">({{ $user->student->section }})</span>
                                @endif
                            @else
                                {{ $user->employee?->employee_number }}
                            @endif
                        </td>
                        <td class="px-4 py-3 dark:text-zinc-300">{{ $user->getRoleNames()->join(', ') }}</td>
                        <td class="px-4 py-3">
                            <flux:badge variant="{{ $user->is_active ? 'success' : 'danger' }}" size="sm">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 flex gap-2">
                            <flux:button size="sm" variant="ghost" wire:click="editUser({{ $user->id }})">
                                Edit
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $user->id }})">
                                {{ $user->is_active ? 'Disable' : 'Enable' }}
                            </flux:button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div>
        {{ $users->links() }}
    </div>

    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh]">
            <flux:heading size="lg" class="mb-4">{{ $editingUser ? 'Edit User' : 'Create User' }}</flux:heading>
            
            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="flex flex-col gap-4">
                
                @if(!$editingUser)
                    <!-- User Type Selection (Only when creating) -->
                    <flux:select wire:model.live="userType" label="User Type" required>
                        <flux:select.option value="student">Student</flux:select.option>
                        <flux:select.option value="employee">Employee (Faculty, Staff, Admin, Dean, Head)</flux:select.option>
                    </flux:select>
                @else
                    <div class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">
                        Editing {{ ucfirst($userType) }} Account
                    </div>
                @endif

                <!-- Shared User Profile Details -->
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />

                @if($userType === 'student')
                    <!-- Student Specific Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="student_number" label="Student Number / ID" type="text" placeholder="e.g. STU-1111" required />
                        <flux:input wire:model="section" label="Section / Class Block" type="text" placeholder="e.g. BSIT-3A, 101" />
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <flux:select wire:model="program_id" label="Program / Department" required>
                            <flux:select.option value="">Select Program</flux:select.option>
                            @foreach($programs as $program)
                                <flux:select.option value="{{ $program->id }}">{{ $program->code }} - {{ $program->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="year_level" label="Year Level" required>
                            <flux:select.option value="">Select Year</flux:select.option>
                            <flux:select.option value="1">1st Year</flux:select.option>
                            <flux:select.option value="2">2nd Year</flux:select.option>
                            <flux:select.option value="3">3rd Year</flux:select.option>
                            <flux:select.option value="4">4th Year</flux:select.option>
                        </flux:select>
                    </div>
                @else
                    <!-- Employee Specific Fields -->
                    <flux:input wire:model="employee_number" label="Employee Number / ID" type="text" placeholder="e.g. FAC-1111" required />
                    
                    <flux:select wire:model="employee_role" label="Employee Role" required>
                        <flux:select.option value="faculty">Faculty / Professor</flux:select.option>
                        <flux:select.option value="program head">Program Head</flux:select.option>
                        <flux:select.option value="dean">Dean</flux:select.option>
                        <flux:select.option value="staff">Staff</flux:select.option>
                        <flux:select.option value="admin">System Administrator</flux:select.option>
                    </flux:select>
                @endif

                <flux:input wire:model="password" label="Password" type="password" :placeholder="$editingUser ? 'Leave blank to keep current' : 'Password'" :required="!$editingUser" />

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
