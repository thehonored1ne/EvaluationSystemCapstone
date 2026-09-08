<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-employees-skeleton');
    }

    // Filter properties
    #[Url]
    public string $selectedRole = ''; // '', 'dean', 'program head', 'faculty', 'staff'

    #[Url]
    public string $selectedEmploymentType = ''; // '', 'full_time', 'part_time'

    #[Url]
    public string $selectedStatus = ''; // '', 'active', 'disabled', 'on_leave', 'resigned', 'retired'

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

    public bool $deletingUserHasHistory = false;

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

    public string $employment_type = 'full_time'; // 'full_time', 'part_time'

    public string $status = 'active'; // 'active', 'on_leave', 'resigned', 'retired'

    public string $department_id = '';

    // Bulk Operations
    /** @var array<int, string> */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showBulkStatusModal = false;

    public string $bulkStatus = 'active';

    public bool $showBulkDeptModal = false;

    public string $bulkDepartmentId = '';

    public bool $showBulkEmploymentModal = false;

    public string $bulkEmploymentType = 'full_time';

    public bool $showBulkDeleteModal = false;

    public int $bulkDeleteEligibleCount = 0;

    public int $bulkDeleteBlockedCount = 0;

    /** @var array<int, string> */
    public array $bulkDeleteEligibleIds = [];

    public bool $showReviewSelectionModal = false;

    public function deselectAll(): void
    {
        $this->selectedIds = [];
        $this->selectAll = false;
        $this->showReviewSelectionModal = false;
        $this->dispatch('clear-selected-storage');
    }

    /**
     * @param array<int, string|int> $ids
     */
    public function restoreSelectedIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $validIds = User::whereIn('id', $ids)
            ->whereNotNull('employee_id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $this->selectedIds = $validIds;
        $this->updatedSelectedIds();
    }

    public function removeSelected($userId): void
    {
        $this->selectedIds = array_values(array_diff($this->selectedIds, [(string) $userId]));
        $this->updatedSelectedIds();

        if (empty($this->selectedIds)) {
            $this->showReviewSelectionModal = false;
        }
    }

    public function updatedSelectedIds(): void
    {
        $currentPageIds = $this->getCurrentPageEmployeeIds();
        $this->selectAll = ! empty($currentPageIds) && empty(array_diff($currentPageIds, $this->selectedIds));
    }

    public function updatedSelectAll($value): void
    {
        $currentPageIds = $this->getCurrentPageEmployeeIds();

        if ($value) {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $currentPageIds)));
        } else {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $currentPageIds));
        }
    }

    protected function getCurrentPageEmployeeIds(): array
    {
        $query = $this->getFilteredUsersQuery();
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $query->paginate(10);

        return collect($paginator->items())
            ->filter(fn ($u) => ! $u->hasRole('admin'))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function getHasSelectedInactiveProperty(): bool
    {
        if (empty($this->selectedIds)) {
            return false;
        }

        return User::whereIn('id', $this->selectedIds)->where('is_active', false)->exists();
    }

    public function getHasSelectedActiveProperty(): bool
    {
        if (empty($this->selectedIds)) {
            return false;
        }

        return User::whereIn('id', $this->selectedIds)->where('is_active', true)->exists();
    }

    public function getSelectedUsersListProperty()
    {
        if (empty($this->selectedIds)) {
            return collect();
        }

        return User::whereIn('id', $this->selectedIds)
            ->with(['employee.department'])
            ->get();
    }

    public function updatedSelectedRole()
    {
        $this->resetPage();
    }

    public function updatedSelectedEmploymentType()
    {
        $this->resetPage();
    }

    public function updatedSelectedStatus()
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
        $this->reset(['search', 'selectedRole', 'selectedEmploymentType', 'selectedStatus', 'selectedDepartmentId', 'sortDirection']);
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
        $this->employment_type = in_array($this->selectedEmploymentType, ['full_time', 'part_time'])
            ? $this->selectedEmploymentType
            : 'full_time';
        $this->status = 'active';
        $this->showModal = true;
    }

    protected function getFilteredUsersQuery()
    {
        $query = User::query()
            ->join('employees', 'employees.id', '=', 'users.employee_id')
            ->select('users.*')
            ->with(['employee.department', 'employee.supervisedDepartments', 'roles']);

        if ($this->selectedRole) {
            $query->where('employees.role', $this->selectedRole);
        }

        if ($this->selectedEmploymentType) {
            $query->where('employees.employment_type', $this->selectedEmploymentType);
        }

        if ($this->selectedStatus === 'active') {
            $query->where('employees.status', 'active')->where('users.is_active', true);
        } elseif ($this->selectedStatus === 'disabled') {
            $query->where('users.is_active', false)->whereNotIn('employees.status', ['resigned', 'retired']);
        } elseif ($this->selectedStatus === 'on_leave') {
            $query->where('employees.status', 'on_leave');
        } elseif ($this->selectedStatus === 'resigned') {
            $query->where('employees.status', 'resigned');
        } elseif ($this->selectedStatus === 'retired') {
            $query->where('employees.status', 'retired');
        }

        if ($this->selectedDepartmentId === 'none') {
            $query->whereNull('employees.department_id');
        } elseif ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('users.name', 'like', '%'.$this->search.'%')
                    ->orWhere('users.email', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.employee_number', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.last_name', 'like', '%'.$this->search.'%');
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('employees.last_name', $orderDirection)
            ->orderBy('employees.first_name', $orderDirection);
    }

    public function with(): array
    {
        $roleCounts = Employee::selectRaw('role, count(*) as count')->groupBy('role')->pluck('count', 'role');
        $allCount = (int) $roleCounts->sum();

        $hasSelectedInactive = false;
        $hasSelectedActive = false;

        if (! empty($this->selectedIds)) {
            $selectedStatuses = User::whereIn('id', $this->selectedIds)->pluck('is_active');
            $hasSelectedInactive = $selectedStatuses->contains(false);
            $hasSelectedActive = $selectedStatuses->contains(true);
        }

        $selectedUsersList = ! empty($this->selectedIds)
            ? User::whereIn('id', $this->selectedIds)->with(['employee.department'])->get()
            : collect();

        return [
            'users' => $this->getFilteredUsersQuery()->paginate(10),
            'departments' => Department::orderBy('name')->get(),
            'counts' => [
                'all' => $allCount,
                'admin' => (int) ($roleCounts['admin'] ?? 0),
                'dean' => (int) ($roleCounts['dean'] ?? 0),
                'department head' => (int) ($roleCounts['department head'] ?? 0),
                'program head' => (int) ($roleCounts['program head'] ?? 0),
                'faculty' => (int) ($roleCounts['faculty'] ?? 0),
                'staff' => (int) ($roleCounts['staff'] ?? 0),
            ],
            'hasSelectedInactive' => $hasSelectedInactive,
            'hasSelectedActive' => $hasSelectedActive,
            'selectedUsersList' => $selectedUsersList,
        ];
    }

    public function bulkSetStatus(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->validate([
            'bulkStatus' => 'required|string|in:active,on_leave,resigned,retired,inactive',
        ]);

        $count = count($this->selectedIds);

        DB::transaction(function () {
            $users = User::whereIn('id', $this->selectedIds)->with('employee')->get();
            $employeeIds = $users->pluck('employee_id')->filter()->toArray();

            Employee::whereIn('id', $employeeIds)->update(['status' => $this->bulkStatus]);

            if (in_array($this->bulkStatus, ['resigned', 'retired', 'inactive'])) {
                $safeUserIds = $users->filter(fn ($u) => ! $u->hasRole('admin'))->pluck('id')->toArray();
                User::whereIn('id', $safeUserIds)->update(['is_active' => false]);
            }
        });

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk updated status to '{$this->bulkStatus}' for {$count} employee(s)");

        $this->deselectAll();
        $this->showBulkStatusModal = false;

        Flux::toast(
            heading: 'Status Updated',
            text: "Successfully updated status for {$count} employee(s).",
            variant: 'success'
        );
    }

    public function bulkSetDepartment(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->validate([
            'bulkDepartmentId' => 'nullable|string',
        ]);

        $deptId = ($this->bulkDepartmentId === 'none' || empty($this->bulkDepartmentId)) ? null : (int) $this->bulkDepartmentId;
        $count = count($this->selectedIds);

        DB::transaction(function () use ($deptId) {
            $users = User::whereIn('id', $this->selectedIds)->with('employee')->get();
            $employeeIds = $users->pluck('employee_id')->filter()->toArray();

            Employee::whereIn('id', $employeeIds)->update(['department_id' => $deptId]);
        });

        $deptName = $deptId ? (Department::find($deptId)?->code ?? 'Selected') : 'Unassigned';

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk assigned department '{$deptName}' to {$count} employee(s)");

        $this->deselectAll();
        $this->showBulkDeptModal = false;

        Flux::toast(
            heading: 'Department Assigned',
            text: "Successfully assigned department to {$count} employee(s).",
            variant: 'success'
        );
    }

    public function bulkSetEmploymentType(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->validate([
            'bulkEmploymentType' => 'required|string|in:full_time,part_time',
        ]);

        $count = count($this->selectedIds);

        DB::transaction(function () {
            $users = User::whereIn('id', $this->selectedIds)->with('employee')->get();
            $employeeIds = $users->pluck('employee_id')->filter()->toArray();

            Employee::whereIn('id', $employeeIds)->update(['employment_type' => $this->bulkEmploymentType]);
        });

        $typeLabel = $this->bulkEmploymentType === 'part_time' ? 'Part-Time' : 'Full-Time';

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk updated employment type to '{$typeLabel}' for {$count} employee(s)");

        $this->deselectAll();
        $this->showBulkEmploymentModal = false;

        Flux::toast(
            heading: 'Employment Type Updated',
            text: "Successfully set employment type to {$typeLabel} for {$count} employee(s).",
            variant: 'success'
        );
    }

    public function bulkSetActive(bool $active): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $targetIds = $this->selectedIds;
        if (! $active) {
            $targetIds = User::whereIn('id', $targetIds)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
                ->where('id', '!=', auth()->id())
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $count = count($targetIds);
        if ($count === 0) {
            $this->deselectAll();

            return;
        }

        DB::transaction(function () use ($targetIds, $active) {
            User::whereIn('id', $targetIds)->update(['is_active' => $active]);

            if ($active) {
                $users = User::whereIn('id', $targetIds)->with('employee')->get();
                $employeeIds = $users->pluck('employee_id')->filter()->toArray();

                Employee::whereIn('id', $employeeIds)
                    ->whereIn('status', ['inactive', 'resigned', 'retired'])
                    ->update(['status' => 'active']);
            }
        });

        $actionName = $active ? 'enabled' : 'disabled';
        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk {$actionName} login access for {$count} employee(s)");

        $this->deselectAll();

        Flux::toast(
            heading: 'Access Updated',
            text: "Successfully {$actionName} login access for {$count} employee(s).",
            variant: 'success'
        );
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $users = User::whereIn('id', $this->selectedIds)->with('employee')->get();

        $this->bulkDeleteEligibleIds = [];
        $this->bulkDeleteBlockedCount = 0;

        foreach ($users as $user) {
            if ($user->id === auth()->id() || $user->hasRole('admin')) {
                $this->bulkDeleteBlockedCount++;
                continue;
            }

            $hasEvals = DB::table('evaluations')
                ->where('evaluator_id', $user->id)
                ->orWhere('evaluatee_id', $user->id)
                ->exists();

            $hasClasses = false;
            if ($user->employee) {
                $hasClasses = DB::table('classes')->where('teacher_id', $user->employee->id)->exists();
            }

            if ($hasEvals || $hasClasses) {
                $this->bulkDeleteBlockedCount++;
            } else {
                $this->bulkDeleteEligibleIds[] = (string) $user->id;
            }
        }

        $this->bulkDeleteEligibleCount = count($this->bulkDeleteEligibleIds);
        $this->showBulkDeleteModal = true;
    }

    public function bulkDelete(): void
    {
        if (empty($this->bulkDeleteEligibleIds)) {
            $this->showBulkDeleteModal = false;

            return;
        }

        $count = count($this->bulkDeleteEligibleIds);

        DB::transaction(function () {
            $users = User::whereIn('id', $this->bulkDeleteEligibleIds)
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
                ->with('employee')
                ->get();
            $safeIds = $users->pluck('id')->toArray();
            $employeeIds = $users->pluck('employee_id')->filter()->toArray();

            User::whereIn('id', $safeIds)->delete();
            Employee::whereIn('id', $employeeIds)->delete();
        });

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk deleted {$count} employee account(s)");

        $this->deselectAll();
        $this->showBulkDeleteModal = false;

        Flux::toast(
            heading: 'Employees Deleted',
            text: "Successfully deleted {$count} employee account(s).",
            variant: 'success'
        );
    }

    public function bulkDeactivateSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $targetIds = array_values(array_filter($this->selectedIds, fn ($id) => (int) $id !== (int) auth()->id()));
        $count = count($targetIds);

        if ($count > 0) {
            DB::transaction(function () use ($targetIds) {
                $users = User::whereIn('id', $targetIds)->with('employee')->get();
                $employeeIds = $users->pluck('employee_id')->filter()->toArray();

                User::whereIn('id', $targetIds)->update(['is_active' => false]);
                Employee::whereIn('id', $employeeIds)->update(['status' => 'inactive']);
            });

            activity('admin')
                ->causedBy(auth()->user())
                ->event('bulk_updated')
                ->log("Bulk deactivated {$count} employee account(s)");

            Flux::toast(
                heading: 'Accounts Deactivated',
                text: "Successfully deactivated {$count} employee account(s). Historical records are preserved.",
                variant: 'success'
            );
        }

        $this->deselectAll();
        $this->showBulkDeleteModal = false;
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
            'employment_type' => 'required|in:full_time,part_time',
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
                'employment_type' => $this->employment_type,
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
        Flux::toast(
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
        $this->employment_type = $user->employee->employment_type ?? 'full_time';
        $this->status = $user->employee->status ?? 'active';
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
            'employment_type' => 'required|in:full_time,part_time',
            'status' => 'required|in:active,on_leave,resigned,retired',
            'department_id' => 'nullable|exists:departments,id',
            'email' => 'required|email|unique:users,email,'.$this->editingUser->id,
        ]);

        $oldRole = $this->editingUser->employee->role ?? null;

        // Guard: Prevent disabling own account or last active administrator via status change
        if (in_array($this->status, ['resigned', 'retired'])) {
            if ($this->editingUser->id === auth()->id()) {
                Flux::toast(
                    heading: 'Action Restricted',
                    text: 'You cannot mark your own currently logged-in account as resigned or retired.',
                    variant: 'danger'
                );

                return;
            }

            if (strtolower($oldRole ?? '') === 'admin' || $this->editingUser->hasRole('admin')) {
                $activeAdminCount = User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))
                    ->where('is_active', true)
                    ->count();
                if ($activeAdminCount <= 1) {
                    Flux::toast(
                        heading: 'Action Restricted',
                        text: 'Cannot mark the last active administrator as resigned or retired.',
                        variant: 'danger'
                    );

                    return;
                }
            }
        }

        // Guard: Prevent demoting the last administrator account
        if ($oldRole === 'admin' && $this->role !== 'admin') {
            $adminCount = User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))->count();
            if ($adminCount <= 1) {
                Flux::toast(
                    heading: 'Action Restricted',
                    text: 'Cannot change the role of the last administrator account in the system.',
                    variant: 'danger'
                );

                return;
            }
        }

        DB::transaction(function () use ($oldRole) {
            $this->editingUser->employee->update([
                'employee_number' => trim($this->employee_number),
                'first_name' => trim($this->first_name),
                'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
                'last_name' => trim($this->last_name),
                'suffix' => $this->suffix ? trim($this->suffix) : null,
                'role' => $this->role,
                'employment_type' => $this->employment_type,
                'status' => $this->status,
                'department_id' => $this->department_id ?: null,
            ]);

            $userData = [
                'name' => $this->editingUser->employee->fresh()->formatted_name,
                'email' => strtolower(trim($this->email)),
            ];

            // If an employee is marked resigned or retired, automatically disable their login
            if (in_array($this->status, ['resigned', 'retired'])) {
                $userData['is_active'] = false;
            }

            $this->editingUser->update($userData);

            if ($oldRole && $oldRole !== $this->role) {
                $this->editingUser->syncRoles([$this->role]);
            }

            $this->syncDepartmentHeadship($this->editingUser->employee, $this->department_id, $this->role, $oldRole);
        });

        $this->showModal = false;
        Flux::toast(
            heading: 'Employee Updated',
            text: 'The employee account has been successfully updated.',
            variant: 'success'
        );
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            Flux::toast(
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
                Flux::toast(
                    heading: 'Action Restricted',
                    text: 'Cannot disable the last active administrator account in the system.',
                    variant: 'danger'
                );

                return;
            }
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        Flux::toast(
            heading: $user->is_active ? 'Account Enabled' : 'Account Disabled',
            text: 'The employee account status has been updated.',
            variant: 'success'
        );
    }

    public function confirmDelete(User $user)
    {
        if ($user->id === auth()->id()) {
            Flux::toast(
                heading: 'Action Restricted',
                text: 'You cannot delete your own currently logged-in account.',
                variant: 'danger'
            );

            return;
        }

        if (strtolower($user->employee->role ?? '') === 'admin') {
            $adminCount = User::whereHas('employee', fn ($q) => $q->where('role', 'admin'))->count();
            if ($adminCount <= 1) {
                Flux::toast(
                    heading: 'Action Restricted',
                    text: 'Cannot delete the last administrator account in the system.',
                    variant: 'danger'
                );

                return;
            }
        }

        $this->deletingUser = $user;
        $hasEvals = DB::table('evaluations')
            ->where('evaluator_id', $user->id)
            ->orWhere('evaluatee_id', $user->id)
            ->exists();
        $hasClasses = false;
        if ($user->employee) {
            $hasClasses = DB::table('classes')->where('teacher_id', $user->employee->id)->exists();
        }
        $this->deletingUserHasHistory = $hasEvals || $hasClasses;
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if (! $this->deletingUser) {
            return;
        }

        if ($this->deletingUser->id === auth()->id()) {
            Flux::toast(
                heading: 'Action Restricted',
                text: 'You cannot delete your own currently logged-in account.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;

            return;
        }

        if ($this->deletingUserHasHistory) {
            $this->showDeleteModal = false;
            Flux::toast(
                heading: 'Deletion Blocked',
                text: 'This employee has historical academic classes or evaluation records that must be preserved. Deactivate their account or update their status to Inactive/Resigned instead.',
                variant: 'danger'
            );

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

        Flux::toast(
            heading: 'Employee Deleted',
            text: 'The employee account has been deleted.',
            variant: 'success'
        );
    }

    public function deactivateUserInstead()
    {
        if (! $this->deletingUser) {
            return;
        }

        $this->deletingUser->update(['is_active' => false]);
        if ($this->deletingUser->employee) {
            $this->deletingUser->employee->update(['status' => 'inactive']);
        }

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        Flux::toast(
            heading: 'Account Deactivated',
            text: 'The employee account has been deactivated. Historical classes and evaluation records remain safely preserved.',
            variant: 'success'
        );
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employees_template.csv"',
        ];

        $columns = ['employee_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'role', 'employment_type', 'department_code', 'status'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            // Sample rows
            fputcsv($file, ['FAC-001', 'Juan', '', 'Dela Cruz', '', 'juan.delacruz@grc.edu.ph', 'faculty', 'full_time', 'CCS', 'active']);
            fputcsv($file, ['STF-001', 'Maria', 'Clara', 'Santos', '', 'maria.santos@grc.edu.ph', 'staff', 'full_time', 'REG', 'active']);
            fputcsv($file, ['PH-001', 'Alan', '', 'Turing', '', 'alan.turing@grc.edu.ph', 'program head', 'full_time', 'CCS', 'active']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportEmployees()
    {
        $query = User::query()
            ->join('employees', 'employees.id', '=', 'users.employee_id')
            ->select('users.*')
            ->with(['employee.department', 'roles']);

        if ($this->selectedRole) {
            $query->where('employees.role', $this->selectedRole);
        }

        if ($this->selectedEmploymentType) {
            $query->where('employees.employment_type', $this->selectedEmploymentType);
        }

        if ($this->selectedStatus === 'active') {
            $query->where('employees.status', 'active')->where('users.is_active', true);
        } elseif ($this->selectedStatus === 'disabled') {
            $query->where('users.is_active', false)->whereNotIn('employees.status', ['resigned', 'retired']);
        } elseif ($this->selectedStatus === 'on_leave') {
            $query->where('employees.status', 'on_leave');
        } elseif ($this->selectedStatus === 'resigned') {
            $query->where('employees.status', 'resigned');
        } elseif ($this->selectedStatus === 'retired') {
            $query->where('employees.status', 'retired');
        }

        if ($this->selectedDepartmentId === 'none') {
            $query->whereNull('employees.department_id');
        } elseif ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('users.name', 'like', '%'.$this->search.'%')
                    ->orWhere('users.email', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.employee_number', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('employees.last_name', 'like', '%'.$this->search.'%');
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $employees = $query->orderBy('employees.last_name', $orderDirection)
            ->orderBy('employees.first_name', $orderDirection)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="employees_export_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($employees) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Employee Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Role', 'Employment Type', 'Department Code', 'Department Name', 'Status', 'Account Status']);

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
                    $e?->employment_type === 'part_time' ? 'Part-Time' : 'Full-Time',
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
        $rawHeader = fgetcsv($file);
        if (! $rawHeader) {
            $this->addError('importFile', 'The CSV file is empty or corrupted.');

            return;
        }

        $headerMap = [];
        foreach ($rawHeader as $idx => $h) {
            $cleaned = strtolower(trim(str_replace([' ', '-'], '_', $h)));
            $headerMap[$cleaned] = $idx;
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
                $getVal = function ($keys, $fallbackIdx = null) use ($row, $headerMap) {
                    foreach ((array) $keys as $k) {
                        if (isset($headerMap[$k]) && isset($row[$headerMap[$k]])) {
                            return trim($row[$headerMap[$k]]);
                        }
                    }

                    return ($fallbackIdx !== null && isset($row[$fallbackIdx])) ? trim($row[$fallbackIdx]) : '';
                };

                $empNumber = $getVal('employee_number', 0);
                $firstName = $getVal('first_name', 1);
                $middleName = $getVal('middle_name', 2) ?: null;
                $lastName = $getVal('last_name', 3);
                $suffix = $getVal('suffix', 4) ?: null;
                $email = strtolower($getVal('email', 5));
                $role = strtolower($getVal('role', 6) ?: 'faculty');
                $rawEmpType = strtolower($getVal('employment_type'));
                $deptCode = strtoupper($getVal(['department_code', 'department'], 7));
                $status = strtolower($getVal('status', 8) ?: 'active');

                if (! $empNumber || ! $firstName || ! $lastName) {
                    continue; // Skip invalid row
                }

                if (! in_array($role, $validRoles)) {
                    $role = 'faculty';
                }

                $empType = in_array(str_replace(['-', ' '], '_', $rawEmpType), ['part_time', 'parttime', 'part']) ? 'part_time' : 'full_time';
                if (! in_array($status, ['active', 'on_leave', 'resigned', 'retired', 'inactive'])) {
                    $status = 'active';
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
                        'employment_type' => $empType,
                        'department_id' => $deptId ?? $employee->department_id,
                        'status' => $status,
                    ]);

                    if ($employee->user) {
                        $userData = [
                            'name' => $employee->fresh()->formatted_name,
                        ];
                        if (in_array($status, ['resigned', 'retired', 'inactive'])) {
                            $userData['is_active'] = false;
                        }
                        $employee->user->update($userData);

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
                        'employment_type' => $empType,
                        'department_id' => $deptId,
                        'status' => $status,
                    ]);

                    $user = User::create([
                        'name' => $employee->formatted_name,
                        'email' => $email,
                        'employee_id' => $employee->id,
                        'password' => $defaultPassword,
                        'is_active' => ! in_array($status, ['resigned', 'retired', 'inactive']),
                    ]);

                    $user->assignRole($role);
                    $this->syncDepartmentHeadship($employee, (string) $deptId, $role);
                    $addedCount++;
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            $this->addError('importFile', 'Import error on line '.($index + 2).': '.$e->getMessage());

            return;
        }

        $this->reset(['importFile']);
        $this->showImportModal = false;

        activity('admin')
            ->causedBy(auth()->user())
            ->log("Bulk imported {$addedCount} new employees and updated {$updatedCount} employee records via CSV");

        Flux::toast(
            heading: 'Import Successful',
            text: "Processed employees: {$addedCount} added, {$updatedCount} updated.",
            variant: 'success'
        );
    }
}; ?>

<div class="space-y-6">
    {{-- SessionStorage Persistence for Bulk Selection --}}
    <div
        x-data="{
            storageKey: 'selected_employees_admin_{{ auth()->id() ?? 'guest' }}',
            init() {
                const saved = sessionStorage.getItem(this.storageKey);
                if (saved) {
                    try {
                        const parsed = JSON.parse(saved);
                        if (Array.isArray(parsed) && parsed.length > 0) {
                            $wire.restoreSelectedIds(parsed);
                        }
                    } catch (e) {
                        sessionStorage.removeItem(this.storageKey);
                    }
                }

                if (typeof $wire !== 'undefined' && $wire.$watch) {
                    $wire.$watch('selectedIds', (ids) => {
                        if (Array.isArray(ids) && ids.length > 0) {
                            sessionStorage.setItem(this.storageKey, JSON.stringify(ids));
                        } else {
                            sessionStorage.removeItem(this.storageKey);
                        }
                    });
                }
            }
        }"
        @clear-selected-storage.window="sessionStorage.removeItem(storageKey)"
    ></div>

    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Manage Employees</h1>
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

    <!-- Search & Filters Bar -->
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <!-- Search -->
        <div class="flex-1 min-w-0">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or employee ID..." />
        </div>

        <!-- Role Filter Dropdown -->
        <div class="w-full sm:w-44 shrink-0">
            <flux:select wire:model.live="selectedRole" placeholder="All Roles">
                <flux:select.option value="">All Roles ({{ $counts['all'] }})</flux:select.option>
                <flux:select.option value="faculty">Faculty ({{ $counts['faculty'] }})</flux:select.option>
                <flux:select.option value="dean">Dean ({{ $counts['dean'] }})</flux:select.option>
                <flux:select.option value="department head">Dept Head ({{ $counts['department head'] }})</flux:select.option>
                <flux:select.option value="program head">Prog Head ({{ $counts['program head'] }})</flux:select.option>
                <flux:select.option value="staff">Staff ({{ $counts['staff'] }})</flux:select.option>
                <flux:select.option value="admin">Admin ({{ $counts['admin'] }})</flux:select.option>
            </flux:select>
        </div>

        <!-- Employment Type Filter Dropdown -->
        <div class="w-full sm:w-36 shrink-0">
            <flux:select wire:model.live="selectedEmploymentType" placeholder="All Types">
                <flux:select.option value="">All Types</flux:select.option>
                <flux:select.option value="full_time">Full-Time</flux:select.option>
                <flux:select.option value="part_time">Part-Time</flux:select.option>
            </flux:select>
        </div>

        <!-- Status Filter Dropdown -->
        <div class="w-full sm:w-36 shrink-0">
            <flux:select wire:model.live="selectedStatus" placeholder="All Status">
                <flux:select.option value="">All Status</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="disabled">Disabled</flux:select.option>
                <flux:select.option value="on_leave">On Leave</flux:select.option>
                <flux:select.option value="resigned">Resigned</flux:select.option>
                <flux:select.option value="retired">Retired</flux:select.option>
            </flux:select>
        </div>

        <!-- Department Filter Dropdown -->
        <div class="w-full sm:w-48 shrink-0">
            <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments">
                <flux:select.option value="">All Departments</flux:select.option>
                <flux:select.option value="none">Unassigned (None)</flux:select.option>
                @foreach($departments as $dept)
                    <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <!-- Sort Order & Clear Action Group -->
        <div class="flex items-center gap-2 shrink-0">
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

            @if($search || $selectedRole || $selectedEmploymentType || $selectedStatus || $selectedDepartmentId || $sortDirection !== 'asc')
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="clearFilters" title="Clear filters" />
            @endif
        </div>
    </div>

    <!-- Bulk Actions Floating Bar -->
    @if(count($selectedIds) > 0)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-3 bg-zinc-900 text-white dark:bg-zinc-800 dark:border dark:border-zinc-700 px-3.5 sm:px-4 py-2.5 sm:py-3 rounded-xl shadow-lg animate-in fade-in slide-in-from-top-2 duration-200">
            <div class="flex items-center justify-between sm:justify-start gap-2.5 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center bg-zinc-800 dark:bg-zinc-700 text-zinc-100 font-mono text-xs font-bold px-2.5 py-1 rounded-lg border border-zinc-700 dark:border-zinc-600 tabular-nums">
                        {{ count($selectedIds) }} Selected
                    </span>
                    <flux:button size="xs" variant="ghost" class="!text-zinc-300 hover:!text-white underline underline-offset-4 text-xs font-semibold" wire:click="$set('showReviewSelectionModal', true)">
                        Review Selection
                    </flux:button>
                </div>

                <div class="sm:hidden">
                    <flux:button size="xs" variant="ghost" class="!text-zinc-400 hover:!text-white !px-2" wire:click="deselectAll" icon="x-mark">
                        Deselect
                    </flux:button>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto pb-1 sm:pb-0 scrollbar-none sm:flex-wrap sm:justify-end -mx-1 px-1 sm:mx-0 sm:px-0">
                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600" icon="identification" wire:click="$set('showBulkStatusModal', true)">
                    Change Status
                </flux:button>

                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600" icon="building-office-2" wire:click="$set('showBulkDeptModal', true)">
                    Assign Dept
                </flux:button>

                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600" icon="briefcase" wire:click="$set('showBulkEmploymentModal', true)">
                    Set Employment
                </flux:button>

                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600 disabled:opacity-40 disabled:cursor-not-allowed" icon="play-circle" wire:click="bulkSetActive(true)" :disabled="!$hasSelectedInactive">
                    Enable Access
                </flux:button>

                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600 disabled:opacity-40 disabled:cursor-not-allowed" icon="pause-circle" wire:click="bulkSetActive(false)" :disabled="!$hasSelectedActive">
                    Disable Access
                </flux:button>

                <flux:button size="xs" variant="danger" class="shrink-0" icon="trash" wire:click="confirmBulkDelete">
                    Delete
                </flux:button>

                <flux:button size="xs" variant="ghost" class="hidden sm:inline-flex shrink-0 !text-zinc-400 hover:!text-white" wire:click="deselectAll">
                    Deselect
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Table -->
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[920px] lg:min-w-0 lg:table-fixed">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <tr>
                        <th class="py-3.5 px-3 w-10 lg:w-[4%] text-center">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:checked:bg-zinc-200 cursor-pointer" aria-label="Select all employees on current page" />
                        </th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Employee ID</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[18%] font-semibold">Full Name</th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Role</th>
                        <th class="py-3.5 px-4 w-52 lg:w-[19%] font-semibold">Department</th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Employment</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[16%] font-semibold">Email</th>
                        <th class="py-3.5 px-4 w-20 lg:w-[7%] font-semibold">Status</th>
                        <th class="py-3.5 px-4 w-16 lg:w-[6%] font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($users as $user)
                        <tr wire:key="emp-user-{{ $user->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ in_array((string)$user->id, $selectedIds) ? 'bg-zinc-50/80 dark:bg-zinc-800/40' : '' }}">
                            <td class="py-3.5 px-3 text-center">
                                @if($user->hasRole('admin'))
                                    <span class="inline-flex items-center justify-center text-zinc-400 dark:text-zinc-600" title="Administrator accounts are protected from bulk operations">
                                        <flux:icon icon="lock-closed" class="size-4" />
                                    </span>
                                @else
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $user->id }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:checked:bg-zinc-200 cursor-pointer" aria-label="Select employee {{ $user->employee?->employee_number ?? $user->name }}" />
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                {{ $user->employee->employee_number ?? 'N/A' }}
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                <div class="flex items-center gap-2 truncate">
                                    <span class="truncate">{{ $user->employee->formatted_name ?? $user->name }}</span>
                                    @if($user->id === auth()->id())
                                        <flux:badge color="indigo" size="sm" class="font-bold text-[10px] uppercase shrink-0">You</flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-zinc-100 capitalize truncate">
                                {{ $user->employee->role ?? 'employee' }}
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 dark:text-zinc-300 truncate">
                                @php
                                    $emp = $user->employee;
                                    $homeDept = $emp?->department?->name;
                                    $supervised = $emp?->supervisedDepartments;
                                @endphp

                                @if($emp && $emp->role === 'dean' && $supervised && $supervised->isNotEmpty())
                                    <div class="truncate">
                                        @if($supervised->count() === 1)
                                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $supervised->first()->code }}</span>
                                            <span class="text-[11px] text-indigo-600 dark:text-indigo-400 block font-medium">Supervising Dean</span>
                                        @else
                                            <flux:badge 
                                                color="indigo" 
                                                size="sm" 
                                                class="font-medium cursor-help"
                                                title="Supervised Departments: {{ $supervised->pluck('code')->join(', ') }}"
                                            >
                                                {{ $supervised->count() }} Supervised Depts
                                            </flux:badge>
                                        @endif
                                    </div>
                                @elseif($homeDept)
                                    <span class="truncate" title="{{ $homeDept }}">{{ $homeDept }}</span>
                                @else
                                    <span class="text-zinc-400 italic">Unassigned</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 dark:text-zinc-300 font-medium truncate">
                                {{ ($user->employee->employment_type ?? 'full_time') === 'part_time' ? 'Part-Time' : 'Full-Time' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ $user->email }}">
                                {{ $user->email }}
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $empStatus = strtolower($user->employee->status ?? 'active');
                                @endphp
                                @if($empStatus === 'resigned')
                                    <flux:badge color="rose" size="sm" class="font-semibold">Resigned</flux:badge>
                                @elseif($empStatus === 'retired')
                                    <flux:badge color="purple" size="sm" class="font-semibold">Retired</flux:badge>
                                @elseif($empStatus === 'on_leave')
                                    <flux:badge color="amber" size="sm" class="font-semibold">On Leave</flux:badge>
                                @else
                                    @if($user->id === auth()->id())
                                        <flux:badge color="emerald" size="sm" title="Active logged-in session">Active</flux:badge>
                                    @else
                                        <button wire:click="toggleActive({{ $user->id }})" class="cursor-pointer" title="{{ $user->is_active ? 'Click to disable' : 'Click to enable' }}">
                                            @if($user->is_active)
                                                <flux:badge color="emerald" size="sm">Active</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">Disabled</flux:badge>
                                            @endif
                                        </button>
                                    @endif
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-right">
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
                            <td colspan="9" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
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
    <flux:modal wire:model="showModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                    {{ $editingUser ? 'Edit Employee Account' : 'Create New Employee Account' }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Fill in employee details and assign institutional role below.</p>
            </div>

            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Role Selection -->
                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Employee Role</label>
                        <select wire:model="role" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="faculty">Faculty / Professor</option>
                            <option value="admin">Administrator (Admin)</option>
                            <option value="dean">Dean</option>
                            <option value="department head">Department Head</option>
                            <option value="program head">Program Head</option>
                            <option value="staff">Staff</option>
                        </select>
                        @error('role') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Employment Type -->
                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Employment Type</label>
                        <select wire:model="employment_type" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="full_time">Full-Time</option>
                            <option value="part_time">Part-Time</option>
                        </select>
                        @error('employment_type') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if($editingUser)
                    <!-- Employment Status (Edit Mode Only) -->
                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Employment Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="active">Active</option>
                            <option value="on_leave">On Leave</option>
                            <option value="resigned">Resigned</option>
                            <option value="retired">Retired</option>
                        </select>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">Setting status to Resigned or Retired automatically deactivates user login while preserving all historical evaluation records.</p>
                        @error('status') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="middle_name" label="Middle Name (Optional)" type="text" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                    <flux:input wire:model="suffix" label="Suffix (Optional)" type="text" placeholder="e.g. Jr., Sr., III" />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />

                <flux:input wire:model="employee_number" label="Employee Number" type="text" placeholder="e.g. EMP-2026-001" required />

                <div>
                    <label class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100 mb-1">Department</label>
                    <select wire:model="department_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
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
    <flux:modal wire:model="showImportModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-xl !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Bulk Import Employees</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Upload a CSV spreadsheet containing faculty, deans, heads, or staff rosters.</p>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2">
                <span class="font-bold text-zinc-800 dark:text-zinc-200 block">CSV File Format Requirements:</span>
                <ul class="list-disc list-inside text-zinc-600 dark:text-zinc-400 space-y-1">
                    <li>Required Columns: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-bold">employee_number, first_name, last_name, role</code></li>
                    <li>Accepted Roles: <code class="font-mono text-zinc-700 dark:text-zinc-300">faculty, dean, department head, program head, staff, admin</code></li>
                    <li>Optional Columns: <code class="font-mono text-zinc-700 dark:text-zinc-300">middle_name, suffix, email, employment_type (full_time / part_time), department_code, status</code></li>
                    <li>Existing employee numbers update details and roles; new employee numbers provision login accounts (default password: <code class="font-mono font-bold">password</code>).</li>
                </ul>
            </div>

            <form wire:submit="importEmployees" class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-zinc-100">Select Spreadsheet (.CSV)</label>
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
        :title="$deletingUserHasHistory ? 'Employee Cannot Be Deleted' : 'Delete Employee Account'" 
        :on-confirm="$deletingUserHasHistory ? 'deactivateUserInstead' : 'deleteUser'" 
        on-cancel="$set('showDeleteModal', false)"
        :confirm-text="$deletingUserHasHistory ? 'Deactivate Account Instead' : 'Delete Account'"
        :variant="$deletingUserHasHistory ? 'primary' : 'danger'"
    >
        @if($deletingUserHasHistory)
            This employee has assigned academic classes or evaluation records. To protect institutional audit data, historical scorecard ratings, and past reports, this account cannot be deleted. You can safely deactivate the account to disable login access.
        @else
            Are you sure you want to delete this employee account? This action cannot be undone and will remove all login access and permissions.
        @endif

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

        @if($deletingUserHasHistory)
            <x-slot:warning>
                Audit Protection: Historical classes and evaluation records are permanently preserved. Deactivating will immediately revoke login access.
            </x-slot:warning>
        @elseif($deletingUser->employee?->managedDepartment()->exists() || $deletingUser->employee?->managedProgram()->exists())
            <x-slot:warning>
                This employee is currently assigned as a Dean or Program Head. Deleting this employee will unassign them from their leadership role.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif

    <!-- Bulk Status Modal -->
    <flux:modal wire:model="showBulkStatusModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Change Employee Status</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Apply a new employment status to all <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ count($selectedIds) }}</span> selected employee(s).
                </p>
            </div>

            <div class="space-y-3">
                <flux:field>
                    <flux:label>Employment Status</flux:label>
                    <flux:select wire:model="bulkStatus">
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="on_leave">On Leave</flux:select.option>
                        <flux:select.option value="resigned">Resigned</flux:select.option>
                        <flux:select.option value="retired">Retired</flux:select.option>
                        <flux:select.option value="inactive">Inactive</flux:select.option>
                    </flux:select>
                    <flux:error name="bulkStatus" />
                </flux:field>

                <p class="text-xs text-amber-600 dark:text-amber-400">
                    Note: Setting status to Resigned, Retired, or Inactive will automatically disable login access.
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkStatusModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="bulkSetStatus">Update Status</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bulk Department Modal -->
    <flux:modal wire:model="showBulkDeptModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Assign Department</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Assign a department to all <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ count($selectedIds) }}</span> selected employee(s).
                </p>
            </div>

            <div class="space-y-3">
                <flux:field>
                    <flux:label>Department</flux:label>
                    <flux:select wire:model="bulkDepartmentId" placeholder="Select Department">
                        <flux:select.option value="none">Unassigned (None)</flux:select.option>
                        @foreach($departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="bulkDepartmentId" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkDeptModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="bulkSetDepartment">Assign Department</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bulk Employment Type Modal -->
    <flux:modal wire:model="showBulkEmploymentModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Set Employment Type</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Update employment classification for <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ count($selectedIds) }}</span> selected employee(s).
                </p>
            </div>

            <div class="space-y-3">
                <flux:field>
                    <flux:label>Employment Classification</flux:label>
                    <flux:select wire:model="bulkEmploymentType">
                        <flux:select.option value="full_time">Full-Time</flux:select.option>
                        <flux:select.option value="part_time">Part-Time</flux:select.option>
                    </flux:select>
                    <flux:error name="bulkEmploymentType" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkEmploymentModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="bulkSetEmploymentType">Update Classification</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bulk Delete Confirmation Modal -->
    <flux:modal wire:model="showBulkDeleteModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-4">
            <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                <flux:icon name="exclamation-triangle" class="size-6 shrink-0" />
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Delete Selected Employees</h2>
            </div>

            <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-2">
                <p>
                    You have selected <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ count($selectedIds) }}</span> employee account(s).
                </p>
                @if($bulkDeleteBlockedCount > 0)
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-200">
                        <span class="font-bold">{{ $bulkDeleteBlockedCount }} account(s)</span> cannot be deleted because they have historical classes, evaluation records, or include your active session. Their historical audit records must be preserved.
                    </div>
                @endif
                @if($bulkDeleteEligibleCount > 0)
                    <p>
                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $bulkDeleteEligibleCount }}</span> account(s) have no linked historical records and will be permanently deleted.
                    </p>
                @endif
                @if($bulkDeleteEligibleCount === 0)
                    <p class="text-rose-600 dark:text-rose-400 font-medium">
                        None of the selected employees can be deleted due to audit protection constraints. You can deactivate their accounts instead.
                    </p>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkDeleteModal', false)">Cancel</flux:button>
                @if($bulkDeleteBlockedCount > 0)
                    <flux:button variant="subtle" wire:click="bulkDeactivateSelected">
                        Deactivate Accounts Instead
                    </flux:button>
                @endif
                @if($bulkDeleteEligibleCount > 0)
                    <flux:button variant="danger" wire:click="bulkDelete">
                        Delete {{ $bulkDeleteEligibleCount }} Account(s)
                    </flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    <!-- Review Selected Employees Modal -->
    <flux:modal wire:model="showReviewSelectionModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-3xl !p-4 sm:!p-6">
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800 pr-10">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <span>Selected Employees</span>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 tabular-nums">
                        {{ count($selectedIds) }}
                    </span>
                </h2>
                @if(count($selectedIds) > 0)
                    <flux:button size="xs" variant="subtle" icon="trash" class="mr-6" wire:click="deselectAll">
                        Clear All
                    </flux:button>
                @endif
            </div>

            <div class="max-h-[380px] overflow-y-auto overscroll-contain rounded-lg border border-zinc-200 dark:border-zinc-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm min-w-[580px]">
                        <thead class="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-800/90 backdrop-blur-xs border-b border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400">
                            <tr>
                                <th class="py-2.5 px-3 font-semibold text-xs w-28">Employee ID</th>
                                <th class="py-2.5 px-3 font-semibold text-xs">Full Name</th>
                                <th class="py-2.5 px-3 font-semibold text-xs">Department</th>
                                <th class="py-2.5 px-3 font-semibold text-xs w-24">Role</th>
                                <th class="py-2.5 px-3 font-semibold text-xs w-20">Status</th>
                                <th class="py-2.5 px-3 font-semibold text-xs w-14 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @forelse($selectedUsersList as $selUser)
                                <tr wire:key="selected-emp-preview-{{ $selUser->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                        {{ $selUser->employee?->employee_number ?: 'N/A' }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                                {{ $selUser->employee?->formatted_name ?? $selUser->name }}
                                            </p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                                {{ $selUser->email }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ $selUser->employee?->department?->code ?? 'Unassigned' }}
                                    </td>
                                    <td class="py-2.5 px-3 capitalize text-xs font-medium text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                                        {{ $selUser->employee?->role ?? 'employee' }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        <flux:badge size="sm" :color="$selUser->is_active ? 'emerald' : 'zinc'">
                                            {{ $selUser->is_active ? 'Active' : 'Disabled' }}
                                        </flux:badge>
                                    </td>
                                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                        <flux:button size="xs" variant="ghost" icon="x-mark" class="text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400" wire:click="removeSelected({{ $selUser->id }})" title="Remove from selection" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                        No employees are currently selected.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showReviewSelectionModal', false)">
                    Done
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
