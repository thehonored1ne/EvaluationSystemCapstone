<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Filter properties
    #[Url]
    public string $selectedRole = ''; // '', 'dean', 'program head', 'faculty', 'staff'

    #[Url]
    public string $selectedDepartmentId = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortDirection = 'asc'; // 'asc' (A-Z) or 'desc' (Z-A)

    // Modal state & Form Fields
    public bool $showModal = false;

    public ?User $editingUser = null;

    public bool $showDeleteModal = false;

    public ?User $deletingUser = null;

    public bool $showImportModal = false;

    public $importFile = null;

    // Form fields for employee creation/edit
    public string $email = '';

    public string $employee_number = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public string $role = 'faculty'; // Default role: faculty, dean, program head, staff

    public string $department_id = '';

    public function updatedSelectedRole()
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartmentId()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSortDirection()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedRole', 'selectedDepartmentId', 'sortDirection']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset([
            'email', 'editingUser',
            'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'department_id',
        ]);
        $this->role = in_array($this->selectedRole, ['admin', 'dean', 'department head', 'program head', 'faculty', 'staff'])
            ? $this->selectedRole
            : 'faculty';
        $this->showModal = true;
    }

    public function with(): array
    {
        $query = User::query()->whereHas('employee');

        if ($this->selectedRole) {
            $query->whereHas('employee', function ($q) {
                $q->where('role', $this->selectedRole);
            });
        }

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
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('employee', function ($sub) {
                        $sub->where('employee_number', 'like', '%'.$this->search.'%')
                            ->orWhere('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'users' => $query->with(['employee.department', 'roles'])->orderBy('name', $orderDirection)->paginate(10),
            'departments' => Department::orderBy('name')->get(),
            'counts' => [
                'all' => User::whereHas('employee')->count(),
                'admin' => User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))->count(),
                'dean' => User::whereHas('employee', fn ($q) => $q->where('role', 'dean'))->count(),
                'department head' => User::whereHas('employee', fn ($q) => $q->where('role', 'department head'))->count(),
                'program head' => User::whereHas('employee', fn ($q) => $q->where('role', 'program head'))->count(),
                'faculty' => User::whereHas('employee', fn ($q) => $q->where('role', 'faculty'))->count(),
                'staff' => User::whereHas('employee', fn ($q) => $q->where('role', 'staff'))->count(),
            ],
        ];
    }

    public function createUser()
    {
        $this->validate([
            'employee_number' => 'required|string|unique:employees,employee_number',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'role' => 'required|in:admin,dean,department head,program head,faculty,staff',
            'department_id' => 'nullable|exists:departments,id',
            'email' => 'required|email|unique:users,email',
        ]);

        DB::transaction(function () {
            $employee = Employee::create([
                'employee_number' => trim($this->employee_number),
                'first_name' => trim($this->first_name),
                'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
                'last_name' => trim($this->last_name),
                'suffix' => $this->suffix ? trim($this->suffix) : null,
                'role' => $this->role,
                'status' => 'active',
                'department_id' => $this->department_id ?: null,
            ]);

            $user = User::create([
                'name' => $employee->formatted_name,
                'email' => strtolower(trim($this->email)),
                'employee_id' => $employee->id,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);

            $user->assignRole($this->role);

            $this->syncDepartmentHeadship($employee, $this->department_id, $this->role);
        });

        $this->showModal = false;
        \Flux::toast(
            heading: 'Employee Created',
            text: 'The employee account has been successfully created.',
            variant: 'success'
        );
    }

    private function syncDepartmentHeadship(Employee $employee, ?string $newDeptId, string $newRole, ?string $oldRole = null)
    {
        $deptIdVal = $newDeptId ? (int) $newDeptId : null;

        // Clear old department leadership if role changed or department changed or set to null
        if ($oldRole === 'program head' && ($newRole !== 'program head' || ! $deptIdVal)) {
            Department::where('program_head_id', $employee->id)->update(['program_head_id' => null]);
        }
        if ($oldRole === 'department head' && ($newRole !== 'department head' || ! $deptIdVal)) {
            Department::where('department_head_id', $employee->id)->update(['department_head_id' => null]);
        }
        if ($oldRole === 'dean' && ($newRole !== 'dean' || ! $deptIdVal)) {
            Department::where('dean_id', $employee->id)->update(['dean_id' => null]);
        }

        if ($newRole === 'program head' && $deptIdVal) {
            Department::where('program_head_id', $employee->id)->where('id', '!=', $deptIdVal)->update(['program_head_id' => null]);
            Department::where('id', $deptIdVal)->update(['program_head_id' => $employee->id]);
        } elseif ($newRole === 'department head' && $deptIdVal) {
            Department::where('department_head_id', $employee->id)->where('id', '!=', $deptIdVal)->update(['department_head_id' => null]);
            Department::where('id', $deptIdVal)->update(['department_head_id' => $employee->id]);
        } elseif ($newRole === 'dean' && $deptIdVal) {
            Department::where('dean_id', $employee->id)->where('id', '!=', $deptIdVal)->update(['dean_id' => null]);
            Department::where('id', $deptIdVal)->update(['dean_id' => $employee->id]);
        }
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->email = $user->email;

        $this->employee_number = $user->employee->employee_number ?? '';
        $this->first_name = $user->employee->first_name ?? '';
        $this->middle_name = $user->employee->middle_name ?? '';
        $this->last_name = $user->employee->last_name ?? '';
        $this->suffix = $user->employee->suffix ?? '';
        $this->role = $user->employee->role ?? 'faculty';
        $this->department_id = (string) ($user->employee->department_id ?? '');

        $this->showModal = true;
    }

    public function updateUser()
    {
        $this->validate([
            'employee_number' => 'required|string|unique:employees,employee_number,'.$this->editingUser->employee_id,
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'role' => 'required|in:admin,dean,department head,program head,faculty,staff',
            'department_id' => 'nullable|exists:departments,id',
            'email' => 'required|email|unique:users,email,'.$this->editingUser->id,
        ]);

        $oldRole = $this->editingUser->employee->role ?? null;

        DB::transaction(function () use ($oldRole) {
            $this->editingUser->employee->update([
                'employee_number' => trim($this->employee_number),
                'first_name' => trim($this->first_name),
                'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
                'last_name' => trim($this->last_name),
                'suffix' => $this->suffix ? trim($this->suffix) : null,
                'role' => $this->role,
                'department_id' => $this->department_id ?: null,
            ]);

            $this->editingUser->update([
                'name' => $this->editingUser->employee->fresh()->formatted_name,
                'email' => strtolower(trim($this->email)),
            ]);

            if ($oldRole && $oldRole !== $this->role) {
                $this->editingUser->syncRoles([$this->role]);
            }

            $this->syncDepartmentHeadship($this->editingUser->employee, $this->department_id, $this->role, $oldRole);
        });

        $this->showModal = false;
        \Flux::toast(
            heading: 'Employee Updated',
            text: 'The employee account has been successfully updated.',
            variant: 'success'
        );
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            \Flux::toast(
                heading: 'Action Restricted',
                text: 'You cannot disable your own currently logged-in account.',
                variant: 'danger'
            );

            return;
        }

        if (strtolower($user->employee->role ?? '') === 'admin' && $user->is_active) {
            $activeAdminCount = User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))
                ->where('is_active', true)
                ->count();
            if ($activeAdminCount <= 1) {
                \Flux::toast(
                    heading: 'Action Restricted',
                    text: 'Cannot disable the last active administrator account in the system.',
                    variant: 'danger'
                );

                return;
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        \Flux::toast(
            heading: $user->is_active ? 'Account Enabled' : 'Account Disabled',
            text: 'The employee account status has been updated.',
            variant: 'success'
        );
    }

    public function confirmDelete(User $user)
    {
        if ($user->id === auth()->id()) {
            \Flux::toast(
                heading: 'Action Restricted',
                text: 'You cannot delete your own currently logged-in account.',
                variant: 'danger'
            );

            return;
        }

        if (strtolower($user->employee->role ?? '') === 'admin') {
            $adminCount = User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))->count();
            if ($adminCount <= 1) {
                \Flux::toast(
                    heading: 'Action Restricted',
                    text: 'Cannot delete the last administrator account in the system.',
                    variant: 'danger'
                );

                return;
            }
        }

        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if (! $this->deletingUser) {
            return;
        }

        if ($this->deletingUser->id === auth()->id()) {
            \Flux::toast(
                heading: 'Action Restricted',
                text: 'You cannot delete your own currently logged-in account.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;

            return;
        }

        DB::transaction(function () {
            $employee = $this->deletingUser->employee;
            $this->deletingUser->delete();
            if ($employee) {
                $employee->delete();
            }
        });

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        \Flux::toast(
            heading: 'Employee Deleted',
            text: 'The employee account has been deleted.',
            variant: 'success'
        );
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employees_template.csv"',
        ];

        $columns = ['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'role', 'department_code', 'status'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            // Sample rows
            fputcsv($file, ['FAC-001', 'Juan', '', 'Dela Cruz', '', 'juan.delacruz@grc.edu.ph', 'faculty', 'CCS', 'active']);
            fputcsv($file, ['STF-001', 'Maria', 'Clara', 'Santos', '', 'maria.santos@grc.edu.ph', 'staff', 'REG', 'active']);
            fputcsv($file, ['PH-001', 'Alan', '', 'Turing', '', 'alan.turing@grc.edu.ph', 'program head', 'CCS', 'active']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEmployees()
    {
        $query = User::query()->whereHas('employee')->with(['employee.department', 'roles']);

        if ($this->selectedRole) {
            $query->whereHas('employee', fn ($q) => $q->where('role', $this->selectedRole));
        }

        if ($this->selectedDepartmentId === 'none') {
            $query->whereHas('employee', fn ($q) => $q->whereNull('department_id'));
        } elseif ($this->selectedDepartmentId) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $this->selectedDepartmentId));
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('employee', function ($sub) {
                        $sub->where('employee_number', 'like', '%'.$this->search.'%')
                            ->orWhere('first_name', 'like', '%'.$this->search.'%')
                            ->orWhere('last_name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $employees = $query->orderBy('name', $orderDirection)->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employees_export_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($employees) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Role', 'Department Code', 'Department Name', 'Status', 'Account Status']);

            foreach ($employees as $user) {
                $e = $user->employee;
                fputcsv($file, [
                    $e?->employee_number ?? '',
                    $e?->first_name ?? '',
                    $e?->middle_name ?? '',
                    $e?->last_name ?? '',
                    $e?->suffix ?? '',
                    $user->email,
                    $e?->role ?? 'faculty',
                    $e?->department?->code ?? 'None',
                    $e?->department?->name ?? 'None',
                    $e?->status ?? 'active',
                    $user->is_active ? 'Active' : 'Disabled',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importEmployees()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $path = $this->importFile->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);
        if (! $header) {
            $this->addError('importFile', 'The CSV file is empty or corrupted.');

            return;
        }

        $rows = [];
        while (($row = fgetcsv($file)) !== false) {
            if (array_filter($row)) {
                $rows[] = $row;
            }
        }
        fclose($file);

        if (empty($rows)) {
            $this->addError('importFile', 'No data rows found in the uploaded file.');

            return;
        }

        $departments = Department::all();
        $departmentsByCode = $departments->keyBy(fn ($d) => strtoupper(trim($d->code)));

        $addedCount = 0;
        $updatedCount = 0;
        $defaultPassword = Hash::make('password');
        $validRoles = ['admin', 'dean', 'department head', 'program head', 'faculty', 'staff'];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $empNumber = trim($row[0] ?? '');
                $firstName = trim($row[1] ?? '');
                $middleName = trim($row[2] ?? '') ?: null;
                $lastName = trim($row[3] ?? '');
                $suffix = trim($row[4] ?? '') ?: null;
                $email = strtolower(trim($row[5] ?? ''));
                $role = strtolower(trim($row[6] ?? 'faculty'));
                $deptCode = strtoupper(trim($row[7] ?? ''));
                $status = strtolower(trim($row[8] ?? 'active')) ?: 'active';

                if (! $empNumber || ! $firstName || ! $lastName) {
                    continue; // Skip invalid row
                }

                if (! in_array($role, $validRoles)) {
                    $role = 'faculty';
                }

                if (! $email) {
                    $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName).'.'.preg_replace('/[^a-zA-Z0-9]/', '', $lastName).'@grc.edu.ph');
                }

                $dept = $departmentsByCode->get($deptCode);
                $deptId = $dept ? $dept->id : null;

                $employee = Employee::where('employee_number', $empNumber)->first();
                if ($employee) {
                    $oldRole = $employee->role;
                    $employee->update([
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                        'suffix' => $suffix,
                        'role' => $role,
                        'department_id' => $deptId ?? $employee->department_id,
                        'status' => $status,
                    ]);

                    if ($employee->user) {
                        $employee->user->update([
                            'name' => $employee->fresh()->formatted_name,
                        ]);
                        if ($oldRole !== $role) {
                            $employee->user->syncRoles([$role]);
                        }
                    }

                    $this->syncDepartmentHeadship($employee, (string) ($deptId ?? $employee->department_id), $role, $oldRole);
                    $updatedCount++;
                } else {
                    $employee = Employee::create([
                        'employee_number' => $empNumber,
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                        'suffix' => $suffix,
                        'role' => $role,
                        'department_id' => $deptId,
                        'status' => $status,
                    ]);

                    $user = User::create([
                        'name' => $employee->formatted_name,
                        'email' => $email,
                        'employee_id' => $employee->id,
                        'password' => $defaultPassword,
                        'is_active' => true,
                    ]);

                    $user->assignRole($role);
                    $this->syncDepartmentHeadship($employee, (string) $deptId, $role);
                    $addedCount++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->addError('importFile', 'Import error on line '.($index + 2).': '.$e->getMessage());

            return;
        }

        $this->reset(['importFile']);
        $this->showImportModal = false;

        activity('admin')
            ->causedBy(auth()->user())
            ->log("Bulk imported {$addedCount} new employees and updated {$updatedCount} employee records via CSV");

        \Flux::toast(
            heading: 'Import Successful',
            text: "Processed employees: {$addedCount} added, {$updatedCount} updated.",
            variant: 'success'
        );
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Manage Employees</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">View, create, edit, and assign roles to deans, program heads, faculty, and staff.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button variant="outline" icon="arrow-down-tray" wire:click="exportEmployees">
                Export CSV
            </flux:button>
            <flux:button variant="outline" icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                Import Employees
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="prepareCreate">
                Add Employee
            </flux:button>
        </div>
    </div>

    <!-- Role Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 dark:border-zinc-700 pb-3">
        @php
            $tabs = [
                '' => ['label' => 'All Employees', 'count' => $counts['all']],
                'admin' => ['label' => 'Admin', 'count' => $counts['admin']],
                'dean' => ['label' => 'Deans', 'count' => $counts['dean']],
                'department head' => ['label' => 'Department Heads', 'count' => $counts['department head']],
                'program head' => ['label' => 'Program Heads', 'count' => $counts['program head']],
                'faculty' => ['label' => 'Faculty / Professors', 'count' => $counts['faculty']],
                'staff' => ['label' => 'Staff', 'count' => $counts['staff']],
            ];
        @endphp

        @foreach($tabs as $roleKey => $tab)
            @php $isActive = ($selectedRole === $roleKey); @endphp
            <button 
                wire:click="$set('selectedRole', '{{ $roleKey }}')" 
                @if($isActive)
                    style="background-color: #9b0000 !important; color: #ffffff !important;"
                    class="px-4 py-2 text-xs font-semibold rounded-lg border border-[#9b0000] shadow-sm cursor-pointer"
                @else
                    class="px-4 py-2 text-xs font-semibold rounded-lg border border-zinc-900 text-zinc-900 hover:bg-zinc-100 dark:border-zinc-300 dark:text-zinc-100 dark:hover:bg-zinc-800 bg-white dark:bg-zinc-900 cursor-pointer"
                @endif
            >
                {{ $tab['label'] }} <span class="ml-1 opacity-90">({{ $tab['count'] }})</span>
            </button>
        @endforeach
    </div>

    <!-- Search & Filters Bar -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 items-center">
        <div class="sm:col-span-2">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or employee ID..." />
        </div>
        <div class="flex items-center gap-2">
            <div class="flex-1">
                <flux:select wire:model.live="selectedDepartmentId" placeholder="Filter by Department">
                    <flux:select.option value="">All Departments</flux:select.option>
                    <flux:select.option value="none">Unassigned (None)</flux:select.option>
                    @foreach($departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Filter Icon Dropdown (A-Z / Z-A) -->
            <flux:dropdown align="end">
                <flux:button variant="outline" icon="funnel" tooltip="Sort Order">
                    {{ $sortDirection === 'desc' ? 'Z-A' : 'A-Z' }}
                </flux:button>

                <flux:menu>
                    <flux:menu.item icon="bars-arrow-down" wire:click="$set('sortDirection', 'asc')" :current="$sortDirection === 'asc'">
                        A to Z (A-Z)
                    </flux:menu.item>
                    <flux:menu.item icon="bars-arrow-up" wire:click="$set('sortDirection', 'desc')" :current="$sortDirection === 'desc'">
                        Z to A (Z-A)
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @if($search || $selectedDepartmentId || $sortDirection !== 'asc')
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="clearFilters" title="Clear filters" />
            @endif
        </div>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[800px]">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-6 py-3.5 font-semibold">Employee ID</th>
                        <th class="px-6 py-3.5 font-semibold">Full Name</th>
                        <th class="px-6 py-3.5 font-semibold">Role</th>
                        <th class="px-6 py-3.5 font-semibold">Department</th>
                        <th class="px-6 py-3.5 font-semibold">Email</th>
                        <th class="px-6 py-3.5 font-semibold">Account Status</th>
                        <th class="px-6 py-3.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($users as $user)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                {{ $user->employee->employee_number ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 font-medium text-zinc-900 dark:text-white">
                                <div class="flex items-center gap-2">
                                    <span>{{ $user->employee->formatted_name ?? $user->name }}</span>
                                    @if($user->id === auth()->id())
                                        <flux:badge color="indigo" size="sm" class="font-bold text-[10px] uppercase">You</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $empRole = strtolower($user->employee->role ?? 'employee');
                                @endphp
                                @if($empRole === 'admin')
                                    <flux:badge color="rose" size="sm" class="capitalize font-semibold">Admin</flux:badge>
                                @elseif($empRole === 'dean')
                                    <flux:badge color="amber" size="sm" class="capitalize font-semibold">Dean</flux:badge>
                                @elseif($empRole === 'program head')
                                    <flux:badge color="purple" size="sm" class="capitalize font-semibold">Program Head</flux:badge>
                                @elseif($empRole === 'faculty')
                                    <flux:badge color="indigo" size="sm" class="capitalize font-semibold">Faculty</flux:badge>
                                @elseif($empRole === 'staff')
                                    <flux:badge color="emerald" size="sm" class="capitalize font-semibold">Staff</flux:badge>
                                @elseif($empRole === 'department head')
                                    <flux:badge color="sky" size="sm" class="capitalize font-semibold">Department Head</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" class="capitalize">{{ $empRole }}</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-zinc-600 dark:text-zinc-300">
                                {{ $user->employee->department->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-zinc-600 dark:text-zinc-400">
                                {{ $user->email }}
                            </td>
                            <td class="px-6 py-4">
                                @if($user->id === auth()->id())
                                    <flux:badge color="emerald" size="sm" title="Active logged-in session">Active</flux:badge>
                                @else
                                    <button wire:click="toggleActive({{ $user->id }})" class="cursor-pointer">
                                        @if($user->is_active)
                                            <flux:badge color="emerald" size="sm">Active</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">Disabled</flux:badge>
                                        @endif
                                    </button>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editUser({{ $user->id }})">
                                            Edit Details
                                        </flux:menu.item>
                                        
                                        @if($user->id !== auth()->id())
                                            <flux:menu.item icon="{{ $user->is_active ? 'pause-circle' : 'play-circle' }}" wire:click="toggleActive({{ $user->id }})">
                                                {{ $user->is_active ? 'Disable Account' : 'Enable Account' }}
                                            </flux:menu.item>

                                            <flux:menu.separator />

                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">
                                                Delete Account
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No employee accounts found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Employee Modal -->
    <flux:modal wire:model="showModal" class="min-w-[480px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingUser ? 'Edit Employee Account' : 'Create New Employee Account' }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Fill in employee details and assign institutional role below.</p>
            </div>

            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="space-y-4">
                <!-- Role Selection -->
                <div>
                    <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Employee Role</label>
                    <select wire:model="role" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="faculty">Faculty / Professor</option>
                        <option value="admin">Administrator (Admin)</option>
                        <option value="dean">Dean</option>
                        <option value="department head">Department Head</option>
                        <option value="program head">Program Head</option>
                        <option value="staff">Staff</option>
                    </select>
                    @error('role') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="middle_name" label="Middle Name (Optional)" type="text" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                    <flux:input wire:model="suffix" label="Suffix (Optional)" type="text" placeholder="e.g. Jr., Sr., III" />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />

                <flux:input wire:model="employee_number" label="Employee Number" type="text" placeholder="e.g. EMP-2026-001" required />

                <div>
                    <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Department</label>
                    <select wire:model="department_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                        <option value="">Unassigned (None)</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                        @endforeach
                    </select>
                    @error('department_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingUser ? 'Save Changes' : 'Create Employee' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Bulk Import Employees Modal -->
    <flux:modal wire:model="showImportModal" class="min-w-[520px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Bulk Import Employees</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Upload a CSV spreadsheet containing faculty, deans, heads, or staff rosters.</p>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2">
                <span class="font-bold text-zinc-800 dark:text-zinc-200 block">CSV File Format Requirements:</span>
                <ul class="list-disc list-inside text-zinc-600 dark:text-zinc-400 space-y-1">
                    <li>Required Columns: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-bold">employee_number, first_name, last_name, role</code></li>
                    <li>Accepted Roles: <code class="font-mono text-zinc-700 dark:text-zinc-300">faculty, dean, department head, program head, staff, admin</code></li>
                    <li>Optional Columns: <code class="font-mono text-zinc-700 dark:text-zinc-300">middle_name, suffix, email, department_code, status</code></li>
                    <li>Existing employee numbers update details and roles; new employee numbers provision login accounts (default password: <code class="font-mono font-bold">password</code>).</li>
                </ul>
            </div>

            <form wire:submit="importEmployees" class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white">Select Spreadsheet (.CSV)</label>
                        <flux:button size="xs" variant="outline" icon="arrow-down-tray" wire:click="downloadTemplate">
                            Download Template
                        </flux:button>
                    </div>
                    <input type="file" wire:model="importFile" accept=".csv,text/csv" class="w-full text-xs text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#9b0000] file:text-white hover:file:bg-[#7a0000] cursor-pointer" required />
                    @error('importFile') <span class="text-xs text-rose-500 mt-1 block font-semibold">{{ $message }}</span> @enderror
                </div>

                <div wire:loading wire:target="importFile" class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                    Uploading and verifying file...
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showImportModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        Upload & Import
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingUser)
    <x-confirmation-modal 
        title="Delete Employee Account" 
        on-confirm="deleteUser" 
        on-cancel="$set('showDeleteModal', false)"
    >
        Are you sure you want to delete this employee account? This action cannot be undone and will remove all login access and permissions.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Full Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->employee?->formatted_name ?? $deletingUser->name }}</span>
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
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Role & Department</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ ucfirst($deletingUser->employee?->role ?: 'Employee') }} • {{ $deletingUser->employee?->department?->name ?: 'Unassigned' }}
                    </span>
                </div>
            </div>
        </x-slot:details>

        @if(\App\Models\Evaluation::where('evaluator_id', $deletingUser->id)->orWhere('evaluatee_id', $deletingUser->id)->exists())
            <x-slot:warning>
                Deleting this employee will permanently remove all associated evaluation records submitted by or for this employee.
            </x-slot:warning>
        @elseif($deletingUser->employee?->managedDepartment()->exists() || $deletingUser->employee?->managedProgram()->exists())
            <x-slot:warning>
                This employee is currently assigned as a Dean or Program Head. Deleting this employee will unassign them from their leadership role.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>
