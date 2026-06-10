<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Fields
    public string $email = '';
    public string $password = '';
    public bool $showModal = false;
    public ?User $editingUser = null;
    public bool $showDeleteModal = false;
    public ?User $deletingUser = null;

    // Employee specific
    public string $employee_number = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $department_id = '';

    public string $selectedDepartmentId = '';
    public string $search = '';

    public function updatedSelectedDepartmentId() { $this->resetPage(); }

    public function updatedSearch() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedDepartmentId']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset([
            'email', 'password', 'editingUser',
            'employee_number', 'first_name', 'last_name', 'department_id'
        ]);
        $this->showModal = true;
    }

    public function with(): array
    {
        $query = User::query()->whereHas('employee', function ($q) {
            $q->where('role', 'dean');
        });

        if ($this->selectedDepartmentId === 'none') {
            $query->whereHas('employee', function ($q) {
                $q->whereNull('department_id');
            });
        } elseif ($this->selectedDepartmentId) {
            $query->whereHas('employee', function ($q) {
                $q->where('department_id', $this->selectedDepartmentId);
            });
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhereHas('employee', function ($sub) {
                      $sub->where('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return [
            'users' => $query->orderBy('id', 'desc')->paginate(10),
            'departments' => Department::orderBy('name')->get(),
        ];
    }

    public function createUser()
    {
        $this->validate([
            'employee_number' => 'required|string|unique:employees,employee_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        $employee = Employee::create([
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'role' => 'dean',
            'status' => 'active',
            'department_id' => $this->department_id ?: null,
        ]);

        $user = User::create([
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'employee_id' => $employee->id,
            'password' => bcrypt($this->password),
            'is_active' => true,
        ]);

        $user->assignRole('dean');

        $this->showModal = false;
        \Flux::toast(
            heading: 'Dean Created',
            text: 'The dean account has been successfully created.',
            variant: 'success'
        );
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->email = $user->email;
        $this->password = '';

        $this->employee_number = $user->employee->employee_number ?? '';
        $this->first_name = $user->employee->first_name ?? '';
        $this->last_name = $user->employee->last_name ?? '';
        $this->department_id = $user->employee->department_id ?? '';

        $this->showModal = true;
    }

    public function updateUser()
    {
        $this->validate([
            'employee_number' => 'required|string|unique:employees,employee_number,' . $this->editingUser->employee_id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
            'password' => 'nullable|min:8',
        ]);

        $this->editingUser->employee->update([
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'department_id' => $this->department_id ?: null,
        ]);

        $this->editingUser->update([
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
        ]);

        if ($this->password) {
            $this->editingUser->update(['password' => bcrypt($this->password)]);
        }

        $this->showModal = false;
        \Flux::toast(
            heading: 'Dean Updated',
            text: 'The dean account has been successfully updated.',
            variant: 'success'
        );
    }

    public function toggleActive(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();

        \Flux::toast(
            heading: $user->is_active ? 'Account Enabled' : 'Account Disabled',
            text: "The user account status has been updated.",
            variant: 'success'
        );
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if (!$this->deletingUser) return;

        \Illuminate\Support\Facades\DB::transaction(function () {
            $employee = $this->deletingUser->employee;
            $this->deletingUser->delete();
            if ($employee) {
                $employee->delete();
            }
        });

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        \Flux::toast(
            heading: 'Dean Deleted',
            text: 'The dean account has been successfully deleted.',
            variant: 'success'
        );
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl" level="1">Manage Deans</flux:heading>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Create Dean</flux:button>
    </div>
    
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or ID..." />
        </div>
        
        <div class="w-full md:w-64">
            <flux:select wire:model.live="selectedDepartmentId" placeholder="Filter by Department">
                <flux:select.option value="">All Departments</flux:select.option>
                <flux:select.option value="none">None</flux:select.option>
                @foreach($departments as $dept)
                    <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        
        <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
    </div>
    
    <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700">
        <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
            <thead class="bg-gray-50 dark:bg-zinc-800">
                <tr>
                    <th class="w-[30%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Name</th>
                    <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Employee ID</th>
                    <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Email Address</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Department</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Status</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                @forelse ($users as $user)
                    <tr wire:key="{{ $user->id }}">
                        <td class="px-4 py-3 dark:text-zinc-300 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 font-mono text-xs">{{ $user->employee?->employee_number }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 text-xs">{{ $user->email }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 font-semibold text-xs">
                            {{ $user->employee?->department?->code ?: 'None' }}
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge variant="{{ $user->is_active ? 'success' : 'danger' }}" size="sm">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="editUser({{ $user->id }})">
                                    Edit
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $user->id }})">
                                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" class="text-red-500 hover:text-red-600 dark:hover:text-red-400" wire:click="confirmDelete({{ $user->id }})">
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
                            No dean accounts found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $users->links() }}
    </div>

    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh]">
            <flux:heading size="lg" class="mb-4">{{ $editingUser ? 'Edit Dean' : 'Create Dean' }}</flux:heading>
            
            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="flex flex-col gap-4">
                
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />
                
                <flux:input wire:model="employee_number" label="Employee Number" type="text" placeholder="e.g. DEC-001" required />

                <flux:select wire:model="department_id" label="Department / College">
                    <flux:select.option value="">Select Department (Optional)</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="password" label="Password" type="password" :placeholder="$editingUser ? 'Leave blank to keep current' : 'Password'" :required="!$editingUser" />

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingUser)
    <x-confirmation-modal 
        title="Delete Dean Account" 
        on-confirm="deleteUser" 
        on-cancel="$set('showDeleteModal', false)"
    >
        Are you sure you want to delete this dean account? This action cannot be undone and will remove all login access.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Employee ID</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->employee?->employee_number ?: 'None' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Email Address</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->email }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->employee?->department?->name ?: 'None' }}</span>
                </div>
            </div>
        </x-slot:details>

        @if(\App\Models\Evaluation::where('evaluator_id', $deletingUser->id)->orWhere('evaluatee_id', $deletingUser->id)->exists())
            <x-slot:warning>
                Deleting this dean will permanently delete all evaluations they submitted or received.
            </x-slot:warning>
        @elseif($deletingUser->employee?->managedDepartment()->exists())
            <x-slot:warning>
                This dean is currently managing the department: {{ $deletingUser->employee->managedDepartment->name }}. Deleting them will leave the department without a dean.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>
