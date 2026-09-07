<?php

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-students-skeleton');
    }

    // Fields for Create/Edit Modal
    public string $email = '';

    public bool $showModal = false;

    public ?User $editingUser = null;

    public bool $showDeleteModal = false;

    public ?User $deletingUser = null;

    public bool $deletingUserHasHistory = false;

    // Student specific
    public string $student_number = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $suffix = '';

    public string $program_id = '';

    public string $year_level = '';

    public string $section = '';

    public string $status = 'regular';

    // Import properties
    public $importFile = null;

    public bool $showImportModal = false;

    // Filters
    public string $selectedProgramId = '';

    public string $selectedYearLevel = '';

    public string $statusFilter = '';

    public string $search = '';

    public string $sortDirection = 'asc'; // 'asc' (A-Z) or 'desc' (Z-A)

    // Bulk Operations
    /** @var array<int, string> */
    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showBulkStatusModal = false;

    public string $bulkStatus = 'regular';

    public bool $showBulkYearModal = false;

    public string $bulkYearLevel = '1';

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
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'))
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
        $currentPageIds = $this->getCurrentPageStudentIds();
        $this->selectAll = ! empty($currentPageIds) && empty(array_diff($currentPageIds, $this->selectedIds));
    }

    public function updatedSelectAll($value): void
    {
        $currentPageIds = $this->getCurrentPageStudentIds();

        if ($value) {
            $this->selectedIds = array_values(array_unique(array_merge($this->selectedIds, $currentPageIds)));
        } else {
            $this->selectedIds = array_values(array_diff($this->selectedIds, $currentPageIds));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function getCurrentPageStudentIds(): array
    {
        return $this->getFilteredStudentsQuery()
            ->paginate(10)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    protected function getFilteredStudentsQuery()
    {
        $query = User::query()
            ->join('students', 'students.id', '=', 'users.student_id')
            ->select('users.*');

        if ($this->selectedProgramId === 'none') {
            $query->whereNull('students.program_id');
        } elseif ($this->selectedProgramId) {
            $query->where('students.program_id', $this->selectedProgramId);
        }

        if ($this->selectedYearLevel) {
            $query->where('students.year_level', $this->selectedYearLevel);
        }

        if ($this->statusFilter) {
            $query->where('students.status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('users.name', 'like', '%'.$this->search.'%')
                    ->orWhere('users.email', 'like', '%'.$this->search.'%')
                    ->orWhere('students.student_number', 'like', '%'.$this->search.'%')
                    ->orWhere('students.first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('students.last_name', 'like', '%'.$this->search.'%')
                    ->orWhere('students.section', 'like', '%'.$this->search.'%');
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return $query->orderBy('students.last_name', $orderDirection)
            ->orderBy('students.first_name', $orderDirection);
    }

    public function updatedSelectedProgramId(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedYearLevel(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortDirection(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'selectedProgramId', 'selectedYearLevel', 'statusFilter', 'sortDirection']);
        $this->resetPage();
    }

    public function bulkSetStatus(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->validate([
            'bulkStatus' => 'required|string|in:regular,irregular,loa,dropped,graduated,inactive',
        ]);

        $count = count($this->selectedIds);

        DB::transaction(function () {
            $users = User::whereIn('id', $this->selectedIds)->with('student')->get();
            $studentIds = $users->pluck('student_id')->filter()->toArray();

            Student::whereIn('id', $studentIds)->update(['status' => $this->bulkStatus]);

            $shouldDeactivate = in_array($this->bulkStatus, ['dropped', 'graduated', 'inactive']);
            if ($shouldDeactivate) {
                User::whereIn('id', $this->selectedIds)->update(['is_active' => false]);
            }
        });

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk updated status to '{$this->bulkStatus}' for {$count} student(s)");

        $this->deselectAll();
        $this->showBulkStatusModal = false;

        \Flux::toast(
            heading: 'Status Updated',
            text: "Successfully updated status for {$count} student(s).",
            variant: 'success'
        );
    }

    public function bulkSetYearLevel(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $this->validate([
            'bulkYearLevel' => 'required|integer|between:1,4',
        ]);

        $count = count($this->selectedIds);

        DB::transaction(function () {
            $users = User::whereIn('id', $this->selectedIds)->with('student')->get();
            $studentIds = $users->pluck('student_id')->filter()->toArray();

            Student::whereIn('id', $studentIds)->update(['year_level' => (int) $this->bulkYearLevel]);
        });

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk updated year level to Year {$this->bulkYearLevel} for {$count} student(s)");

        $this->deselectAll();
        $this->showBulkYearModal = false;

        \Flux::toast(
            heading: 'Year Level Updated',
            text: "Successfully updated year level for {$count} student(s).",
            variant: 'success'
        );
    }

    public function bulkSetActive(bool $status): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $count = count($this->selectedIds);

        User::whereIn('id', $this->selectedIds)->update(['is_active' => $status]);

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log(($status ? 'Bulk enabled login access' : 'Bulk disabled login access')." for {$count} student(s)");

        $this->deselectAll();

        \Flux::toast(
            heading: $status ? 'Accounts Enabled' : 'Accounts Disabled',
            text: "Successfully updated login access for {$count} student(s).",
            variant: 'success'
        );
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $evaluatorIds = DB::table('evaluations')
            ->whereIn('evaluator_id', $this->selectedIds)
            ->pluck('evaluator_id')
            ->map(fn ($id) => (string) $id);

        $evaluateeIds = DB::table('evaluations')
            ->whereIn('evaluatee_id', $this->selectedIds)
            ->pluck('evaluatee_id')
            ->map(fn ($id) => (string) $id);

        $usersWithHistory = $evaluatorIds->concat($evaluateeIds)->unique()->toArray();

        $this->bulkDeleteEligibleIds = array_values(array_diff($this->selectedIds, $usersWithHistory));
        $this->bulkDeleteEligibleCount = count($this->bulkDeleteEligibleIds);
        $this->bulkDeleteBlockedCount = count($this->selectedIds) - $this->bulkDeleteEligibleCount;

        $this->showBulkDeleteModal = true;
    }

    public function bulkDelete(): void
    {
        if (empty($this->bulkDeleteEligibleIds)) {
            $this->showBulkDeleteModal = false;
            \Flux::toast(
                heading: 'Deletion Blocked',
                text: 'All selected students have historical evaluation records that must be preserved.',
                variant: 'danger'
            );

            return;
        }

        DB::transaction(function () {
            $users = User::whereIn('id', $this->bulkDeleteEligibleIds)->get();
            $studentIds = $users->pluck('student_id')->filter()->toArray();

            User::whereIn('id', $this->bulkDeleteEligibleIds)->delete();
            Student::whereIn('id', $studentIds)->delete();
        });

        $deletedCount = $this->bulkDeleteEligibleCount;
        $blockedCount = $this->bulkDeleteBlockedCount;

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk deleted {$deletedCount} student account(s)");

        $this->deselectAll();
        $this->showBulkDeleteModal = false;
        $this->bulkDeleteEligibleIds = [];

        $msg = "Deleted {$deletedCount} student account(s).";
        if ($blockedCount > 0) {
            $msg .= " ({$blockedCount} preserved due to evaluation history).";
        }

        \Flux::toast(
            heading: 'Bulk Deletion Complete',
            text: $msg,
            variant: 'success'
        );
    }

    public function bulkDeactivateSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        DB::transaction(function () {
            $users = User::whereIn('id', $this->selectedIds)->get();
            $studentIds = $users->pluck('student_id')->filter()->toArray();

            User::whereIn('id', $this->selectedIds)->update(['is_active' => false]);
            Student::whereIn('id', $studentIds)->update(['status' => 'inactive']);
        });

        $count = count($this->selectedIds);

        activity('admin')
            ->causedBy(auth()->user())
            ->event('bulk_updated')
            ->log("Bulk deactivated {$count} student account(s) instead of deleting");

        $this->deselectAll();
        $this->showBulkDeleteModal = false;

        \Flux::toast(
            heading: 'Accounts Deactivated',
            text: "Successfully deactivated {$count} student account(s). Historical records are safely preserved.",
            variant: 'success'
        );
    }

    public function prepareCreate(): void
    {
        $this->reset([
            'email', 'editingUser',
            'student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'program_id', 'year_level', 'section', 'status',
        ]);
        $this->status = 'regular';
        $this->showModal = true;
    }

    public function with(): array
    {
        $users = $this->getFilteredStudentsQuery()
            ->with(['student.program', 'roles'])
            ->paginate(10);

        $currentPageIds = $users->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->selectAll = ! empty($currentPageIds) && empty(array_diff($currentPageIds, $this->selectedIds));

        $hasSelectedInactive = false;
        $hasSelectedActive = false;

        if (! empty($this->selectedIds)) {
            $selectedStatuses = User::whereIn('id', $this->selectedIds)->pluck('is_active');
            $hasSelectedInactive = $selectedStatuses->contains(false);
            $hasSelectedActive = $selectedStatuses->contains(true);
        }

        $selectedUsersList = ! empty($this->selectedIds)
            ? User::whereIn('id', $this->selectedIds)->with('student.program')->get()
            : collect();

        return [
            'users' => $users,
            'programs' => Program::orderBy('name')->get(),
            'hasSelectedInactive' => $hasSelectedInactive,
            'hasSelectedActive' => $hasSelectedActive,
            'selectedUsersList' => $selectedUsersList,
        ];
    }

    public function createUser()
    {
        $this->validate([
            'student_number' => 'required|string|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'status' => 'required|string|in:regular,irregular,loa,dropped,graduated,inactive',
            'email' => 'required|email|unique:users,email',
        ]);

        DB::transaction(function () {
            $student = Student::create([
                'student_number' => trim($this->student_number),
                'first_name' => trim($this->first_name),
                'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
                'last_name' => trim($this->last_name),
                'suffix' => $this->suffix ? trim($this->suffix) : null,
                'program_id' => $this->program_id,
                'year_level' => $this->year_level,
                'section' => $this->section ? trim($this->section) : null,
                'status' => $this->status,
            ]);

            $user = User::create([
                'name' => $student->formatted_name,
                'email' => strtolower(trim($this->email)),
                'student_id' => $student->id,
                'password' => Hash::make('password'),
                'is_active' => ! in_array($this->status, ['dropped', 'graduated', 'inactive']),
            ]);

            $user->assignRole('student');
        });

        $this->showModal = false;
        \Flux::toast(
            heading: 'Student Created',
            text: 'The student account has been successfully created.',
            variant: 'success'
        );
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->email = $user->email;

        $this->student_number = $user->student->student_number ?? '';
        $this->first_name = $user->student->first_name ?? '';
        $this->middle_name = $user->student->middle_name ?? '';
        $this->last_name = $user->student->last_name ?? '';
        $this->suffix = $user->student->suffix ?? '';
        $this->program_id = (string) ($user->student->program_id ?? '');
        $this->year_level = (string) ($user->student->year_level ?? '');
        $this->section = $user->student->section ?? '';
        $this->status = $user->student->status ?? 'regular';

        $this->showModal = true;
    }

    public function updateUser()
    {
        $this->validate([
            'student_number' => 'required|string|unique:students,student_number,'.$this->editingUser->student_id,
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'status' => 'required|string|in:regular,irregular,loa,dropped,graduated,inactive',
            'email' => 'required|email|unique:users,email,'.$this->editingUser->id,
        ]);

        DB::transaction(function () {
            $this->editingUser->student->update([
                'student_number' => trim($this->student_number),
                'first_name' => trim($this->first_name),
                'middle_name' => $this->middle_name ? trim($this->middle_name) : null,
                'last_name' => trim($this->last_name),
                'suffix' => $this->suffix ? trim($this->suffix) : null,
                'program_id' => $this->program_id,
                'year_level' => $this->year_level,
                'section' => $this->section ? trim($this->section) : null,
                'status' => $this->status,
            ]);

            $userData = [
                'name' => $this->editingUser->student->fresh()->formatted_name,
                'email' => strtolower(trim($this->email)),
            ];

            if (in_array($this->status, ['dropped', 'graduated', 'inactive'])) {
                $userData['is_active'] = false;
            }

            $this->editingUser->update($userData);
        });

        $this->showModal = false;
        \Flux::toast(
            heading: 'Student Updated',
            text: 'The student account has been successfully updated.',
            variant: 'success'
        );
    }

    public function toggleActive(User $user)
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        \Flux::toast(
            heading: $user->is_active ? 'Account Enabled' : 'Account Disabled',
            text: 'The student account status has been updated.',
            variant: 'success'
        );
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->deletingUserHasHistory = DB::table('evaluations')
            ->where('evaluator_id', $user->id)
            ->orWhere('evaluatee_id', $user->id)
            ->exists();
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if (! $this->deletingUser) {
            return;
        }

        if ($this->deletingUserHasHistory) {
            $this->showDeleteModal = false;
            \Flux::toast(
                heading: 'Deletion Blocked',
                text: 'This student has historical evaluation records that must be preserved. Deactivate their account or update their status to Graduated/Inactive instead.',
                variant: 'danger'
            );

            return;
        }

        DB::transaction(function () {
            $student = $this->deletingUser->student;
            $this->deletingUser->delete();
            if ($student) {
                $student->delete();
            }
        });

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        \Flux::toast(
            heading: 'Student Deleted',
            text: 'The student account has been successfully deleted.',
            variant: 'success'
        );
    }

    public function deactivateUserInstead()
    {
        if (! $this->deletingUser) {
            return;
        }

        $this->deletingUser->update(['is_active' => false]);
        if ($this->deletingUser->student) {
            $this->deletingUser->student->update(['status' => 'inactive']);
        }

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        \Flux::toast(
            heading: 'Account Deactivated',
            text: 'The student account has been deactivated. Historical evaluation records remain safely preserved.',
            variant: 'success'
        );
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_template.csv"',
        ];

        $columns = ['student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'program_code', 'year_level', 'section', 'status'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            // Sample sample row
            fputcsv($file, ['2026-01-0001', 'Juan', 'Protacio', 'Dela Cruz', '', 'juan.delacruz@grc.edu.ph', 'BSIT', '1', 'BSIT-1A', 'regular']);
            fputcsv($file, ['2026-01-0002', 'Maria', 'Clara', 'Santos', '', 'maria.santos@grc.edu.ph', 'BSA', '2', 'BSA-2A', 'regular']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportStudents()
    {
        $query = User::query()
            ->join('students', 'students.id', '=', 'users.student_id')
            ->select('users.*')
            ->with('student.program');

        if ($this->selectedProgramId === 'none') {
            $query->whereNull('students.program_id');
        } elseif ($this->selectedProgramId) {
            $query->where('students.program_id', $this->selectedProgramId);
        }

        if ($this->selectedYearLevel) {
            $query->where('students.year_level', $this->selectedYearLevel);
        }

        if ($this->statusFilter) {
            $query->where('students.status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('users.name', 'like', '%'.$this->search.'%')
                    ->orWhere('users.email', 'like', '%'.$this->search.'%')
                    ->orWhere('students.student_number', 'like', '%'.$this->search.'%')
                    ->orWhere('students.first_name', 'like', '%'.$this->search.'%')
                    ->orWhere('students.last_name', 'like', '%'.$this->search.'%');
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $students = $query->orderBy('students.last_name', $orderDirection)
            ->orderBy('students.first_name', $orderDirection)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="students_export_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student Number', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Email', 'Program Code', 'Program Name', 'Year Level', 'Section', 'Status', 'Account Status']);

            foreach ($students as $user) {
                $s = $user->student;
                fputcsv($file, [
                    $s?->student_number ?? '',
                    $s?->first_name ?? '',
                    $s?->middle_name ?? '',
                    $s?->last_name ?? '',
                    $s?->suffix ?? '',
                    $user->email,
                    $s?->program?->code ?? 'None',
                    $s?->program?->name ?? 'None',
                    $s?->year_level ?? '',
                    $s?->section ?? '',
                    $s?->status ?? 'regular',
                    $user->is_active ? 'Active' : 'Disabled',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importStudents()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $path = $this->importFile->getRealPath();
        $ext = strtolower($this->importFile->getClientOriginalExtension());

        $rows = [];
        if (in_array($ext, ['csv', 'txt'])) {
            $file = fopen($path, 'r');
            $header = fgetcsv($file);
            if (! $header) {
                $this->addError('importFile', 'The CSV file is empty or corrupted.');

                return;
            }

            // Normalize headers
            $header = array_map(fn ($h) => strtolower(trim(str_replace([' ', '_'], '', $h))), $header);

            while (($row = fgetcsv($file)) !== false) {
                if (array_filter($row)) {
                    $rows[] = $row;
                }
            }
            fclose($file);
        } else {
            // Fallback for CSV
            $this->addError('importFile', 'Please upload a valid CSV file.');

            return;
        }

        if (empty($rows)) {
            $this->addError('importFile', 'No data rows found in the uploaded file.');

            return;
        }

        // Cache programs by code and name
        $programs = Program::all();
        $programsByCode = $programs->keyBy(fn ($p) => strtoupper(trim($p->code)));

        $addedCount = 0;
        $updatedCount = 0;
        $defaultPassword = Hash::make('password');

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $studentNumber = trim($row[0] ?? '');
                $firstName = trim($row[1] ?? '');
                $middleName = trim($row[2] ?? '') ?: null;
                $lastName = trim($row[3] ?? '');
                $suffix = trim($row[4] ?? '') ?: null;
                $email = strtolower(trim($row[5] ?? ''));
                $progCode = strtoupper(trim($row[6] ?? ''));
                $yearLevel = (int) trim($row[7] ?? '1');
                $section = trim($row[8] ?? '') ?: null;
                $status = strtolower(trim($row[9] ?? 'regular')) ?: 'regular';

                if (! $studentNumber || ! $firstName || ! $lastName) {
                    continue; // Skip invalid row
                }

                // If email empty, autogenerate
                if (! $email) {
                    $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName).'.'.preg_replace('/[^a-zA-Z0-9]/', '', $lastName).'@grc.edu.ph');
                }

                $program = $programsByCode->get($progCode);
                $programId = $program ? $program->id : null;

                $validStatus = in_array($status, ['regular', 'irregular', 'loa', 'dropped', 'graduated', 'inactive']) ? $status : 'regular';
                $validYear = ($yearLevel >= 1 && $yearLevel <= 4) ? $yearLevel : 1;

                $student = Student::where('student_number', $studentNumber)->first();
                if ($student) {
                    $student->update([
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                        'suffix' => $suffix,
                        'program_id' => $programId ?? $student->program_id,
                        'year_level' => $validYear,
                        'section' => $section ?? $student->section,
                        'status' => $validStatus,
                    ]);

                    if ($student->user) {
                        $student->user->update([
                            'name' => $student->fresh()->formatted_name,
                        ]);
                    }
                    $updatedCount++;
                } else {
                    $student = Student::create([
                        'student_number' => $studentNumber,
                        'first_name' => $firstName,
                        'middle_name' => $middleName,
                        'last_name' => $lastName,
                        'suffix' => $suffix,
                        'program_id' => $programId,
                        'year_level' => $validYear,
                        'section' => $section,
                        'status' => $validStatus,
                    ]);

                    $user = User::create([
                        'name' => $student->formatted_name,
                        'email' => $email,
                        'student_id' => $student->id,
                        'password' => $defaultPassword,
                        'is_active' => true,
                    ]);

                    $user->assignRole('student');
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
            ->log("Bulk imported {$addedCount} new students and updated {$updatedCount} student records via CSV");

        \Flux::toast(
            heading: 'Import Successful',
            text: "Processed students: {$addedCount} added, {$updatedCount} updated.",
            variant: 'success'
        );
    }
}; ?>

<div class="space-y-6"
    x-data="{
        storageKey: 'selected_students_admin_{{ auth()->id() ?? 'guest' }}',
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
>
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Manage Students</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button variant="outline" icon="arrow-down-tray" wire:click="exportStudents">
                Export CSV
            </flux:button>
            <flux:button variant="outline" icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                Import Students
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="prepareCreate">
                Add Student
            </flux:button>
        </div>
    </div>
    
    <!-- Search & Filters Bar -->
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <!-- Search -->
        <div class="flex-1 min-w-0">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or student ID..." />
        </div>
        
        <!-- Program Filter Dropdown -->
        <div class="w-full sm:w-48 shrink-0">
            <flux:select wire:model.live="selectedProgramId" placeholder="All Programs">
                <flux:select.option value="">All Programs</flux:select.option>
                <flux:select.option value="none">Unassigned (None)</flux:select.option>
                @foreach($programs as $prog)
                    <flux:select.option value="{{ $prog->id }}">{{ $prog->code }} - {{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <!-- Year Level Filter Dropdown -->
        <div class="w-full sm:w-36 shrink-0">
            <flux:select wire:model.live="selectedYearLevel" placeholder="All Years">
                <flux:select.option value="">All Years</flux:select.option>
                <flux:select.option value="1">1st Year</flux:select.option>
                <flux:select.option value="2">2nd Year</flux:select.option>
                <flux:select.option value="3">3rd Year</flux:select.option>
                <flux:select.option value="4">4th Year</flux:select.option>
            </flux:select>
        </div>

        <!-- Enrollment Status Filter Dropdown -->
        <div class="w-full sm:w-40 shrink-0">
            <flux:select wire:model.live="statusFilter" placeholder="All Student Types">
                <flux:select.option value="">All Student Types</flux:select.option>
                <flux:select.option value="regular">Regular</flux:select.option>
                <flux:select.option value="irregular">Irregular</flux:select.option>
                <flux:select.option value="loa">Leave of Absence (LOA)</flux:select.option>
                <flux:select.option value="dropped">Dropped</flux:select.option>
                <flux:select.option value="graduated">Graduated</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
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

            @if($search || $selectedProgramId || $selectedYearLevel || $statusFilter || $sortDirection !== 'asc')
                <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="clearFilters" title="Clear filters" />
            @endif
        </div>
    </div>
    
    <!-- Bulk Actions Bar -->
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
                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600" icon="arrow-path-rounded-square" wire:click="$set('showBulkStatusModal', true)">
                    Change Student Type
                </flux:button>

                <flux:button size="xs" variant="outline" class="shrink-0 !bg-zinc-800 !text-zinc-100 !border-zinc-700 hover:!bg-zinc-700 dark:!bg-zinc-700 dark:hover:!bg-zinc-600" icon="academic-cap" wire:click="$set('showBulkYearModal', true)">
                    Set Year Level
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
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:checked:bg-zinc-200 cursor-pointer" aria-label="Select all students on current page" />
                        </th>
                        <th class="py-3.5 px-4 w-28 lg:w-[11%] font-semibold">Student ID</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[19%] font-semibold">Full Name</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[17%] font-semibold">Email</th>
                        <th class="py-3.5 px-4 w-40 lg:w-[15%] font-semibold">Program & Section</th>
                        <th class="py-3.5 px-4 w-24 lg:w-[10%] font-semibold">Year Level</th>
                        <th class="py-3.5 px-4 w-24 lg:w-[10%] font-semibold">Student Type</th>
                        <th class="py-3.5 px-4 w-20 lg:w-[8%] font-semibold">Status</th>
                        <th class="py-3.5 px-4 w-16 lg:w-[6%] font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($users as $user)
                        <tr wire:key="student-user-{{ $user->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors {{ in_array((string)$user->id, $selectedIds) ? 'bg-zinc-50/80 dark:bg-zinc-800/40' : '' }}">
                            <td class="py-3.5 px-3 text-center">
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $user->id }}" class="rounded border-zinc-300 text-zinc-900 focus:ring-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:checked:bg-zinc-200 cursor-pointer" aria-label="Select student {{ $user->student?->student_number ?? $user->name }}" />
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs font-semibold text-zinc-900 dark:text-white truncate">
                                {{ $user->student?->student_number ?? 'N/A' }}
                            </td>
                            <td class="py-3.5 px-4 font-medium text-zinc-900 dark:text-white truncate">
                                <span class="truncate">{{ $user->student?->formatted_name ?? $user->name }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ $user->email }}">
                                {{ $user->email }}
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 dark:text-zinc-300 truncate">
                                <div class="truncate">
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $user->student?->program?->code ?: 'Unassigned' }}</span>
                                    @if($user->student?->section)
                                        <span class="text-zinc-400 dark:text-zinc-500 font-mono text-xs ml-1.5">{{ $user->student->section }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-zinc-600 dark:text-zinc-300 font-medium truncate">
                                @php
                                    $yr = $user->student?->year_level;
                                    $yrLabel = match((int)$yr) {
                                        1 => '1st Year',
                                        2 => '2nd Year',
                                        3 => '3rd Year',
                                        4 => '4th Year',
                                        default => $yr ? "Year {$yr}" : '—'
                                    };
                                @endphp
                                {{ $yrLabel }}
                            </td>
                            <td class="py-3.5 px-4">
                                @php
                                    $st = strtolower($user->student?->status ?? 'regular');
                                @endphp
                                @if($st === 'regular')
                                    <flux:badge color="emerald" size="sm" class="font-semibold">Regular</flux:badge>
                                @elseif($st === 'irregular')
                                    <flux:badge color="amber" size="sm" class="font-semibold">Irregular</flux:badge>
                                @elseif($st === 'loa')
                                    <flux:badge color="zinc" size="sm" class="font-semibold">LOA</flux:badge>
                                @elseif($st === 'dropped')
                                    <flux:badge color="rose" size="sm" class="font-semibold">Dropped</flux:badge>
                                @elseif($st === 'graduated')
                                    <flux:badge color="purple" size="sm" class="font-semibold">Graduated</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm" class="font-semibold capitalize">{{ $st }}</flux:badge>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <flux:badge size="sm" :color="$user->is_active ? 'emerald' : 'zinc'" class="font-semibold">
                                    {{ $user->is_active ? 'Active' : 'Disabled' }}
                                </flux:badge>
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
                                        
                                        <flux:menu.item icon="{{ $user->is_active ? 'pause-circle' : 'play-circle' }}" wire:click="toggleActive({{ $user->id }})">
                                            {{ $user->is_active ? 'Disable Account' : 'Enable Account' }}
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">
                                            Delete Account
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                No student accounts found matching your filters.
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

    <!-- Create / Edit Student Modal -->
    <flux:modal wire:model="showModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingUser ? 'Edit Student Account' : 'Create New Student Account' }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Fill in student details, enrollment status, and academic program assignment.</p>
            </div>

            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="middle_name" label="Middle Name (Optional)" type="text" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                    <flux:input wire:model="suffix" label="Suffix (Optional)" type="text" placeholder="e.g. Jr., Sr., III" />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />

                <flux:input wire:model="student_number" label="Student Number" type="text" placeholder="e.g. 2026-01-0001" required />

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Academic Program</label>
                        <select wire:model="program_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" required>
                            <option value="">Select Program...</option>
                            @foreach($programs as $prog)
                                <option value="{{ $prog->id }}">{{ $prog->code }} - {{ $prog->name }}</option>
                            @endforeach
                        </select>
                        @error('program_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Year Level</label>
                        <select wire:model="year_level" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" required>
                            <option value="">Select Year...</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                        @error('year_level') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Enrollment Status</label>
                        <select wire:model="status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" required>
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular</option>
                            <option value="loa">Leave of Absence (LOA)</option>
                            <option value="dropped">Dropped</option>
                            <option value="graduated">Graduated</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('status') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <flux:input wire:model="section" label="Section (Optional)" type="text" placeholder="e.g. BSIT-3A" />

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingUser ? 'Save Changes' : 'Create Student' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Bulk Import Students Modal -->
    <flux:modal wire:model="showImportModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-xl !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Bulk Import Students</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Upload a CSV spreadsheet containing new student admissions and enrollment rosters.</p>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2">
                <span class="font-bold text-zinc-800 dark:text-zinc-200 block">CSV File Format Requirements:</span>
                <ul class="list-disc list-inside text-zinc-600 dark:text-zinc-400 space-y-1">
                    <li>Required Columns: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-bold">student_number, first_name, last_name</code></li>
                    <li>Optional Columns: <code class="font-mono text-zinc-700 dark:text-zinc-300">middle_name, suffix, email, program_code, year_level, section, status</code></li>
                    <li>Existing student numbers will update profile information; new student numbers will automatically provision User login accounts (default password: <code class="font-mono font-bold">password</code>).</li>
                </ul>
            </div>

            <form wire:submit="importStudents" class="space-y-4">
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
        :title="$deletingUserHasHistory ? 'Account Cannot Be Deleted' : 'Delete Student Account'" 
        :on-confirm="$deletingUserHasHistory ? 'deactivateUserInstead' : 'deleteUser'" 
        on-cancel="$set('showDeleteModal', false)"
        :confirm-text="$deletingUserHasHistory ? 'Deactivate Account Instead' : 'Delete Account'"
        :variant="$deletingUserHasHistory ? 'primary' : 'danger'"
    >
        @if($deletingUserHasHistory)
            This student has submitted or received evaluation records. To protect institutional audit data, historical ratings, and past semester reports, this account cannot be deleted. You can safely deactivate the account to disable login access.
        @else
            Are you sure you want to delete this student account? This action cannot be undone and will remove all login access.
        @endif

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Full Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->student?->formatted_name ?? $deletingUser->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Student ID</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->student?->student_number ?: 'None' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Email Address</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->email }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program & Section</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ $deletingUser->student?->program?->code ?: 'None' }}
                        @if($deletingUser->student?->section)
                            - {{ $deletingUser->student->section }}
                        @endif
                    </span>
                </div>
            </div>
        </x-slot:details>

        @if($deletingUserHasHistory)
            <x-slot:warning>
                Audit Protection: Historical evaluation records are permanently preserved. Deactivating will immediately revoke login access.
            </x-slot:warning>
        @elseif($deletingUser->student?->classes()->exists())
            <x-slot:warning>
                This student is enrolled in {{ $deletingUser->student->classes()->count() }} class(es). Deleting this student will remove them from these classes.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif

    <!-- Bulk Change Status Modal -->
    <flux:modal wire:model="showBulkStatusModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    Update Student Type for {{ count($selectedIds) }} Students
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Select the new student type to apply to all selected students.
                </p>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="bulkStatus" label="New Student Type">
                    <flux:select.option value="regular">Regular</flux:select.option>
                    <flux:select.option value="irregular">Irregular</flux:select.option>
                    <flux:select.option value="loa">Leave of Absence (LOA)</flux:select.option>
                    <flux:select.option value="dropped">Dropped (Deactivates Account)</flux:select.option>
                    <flux:select.option value="graduated">Graduated (Deactivates Account)</flux:select.option>
                    <flux:select.option value="inactive">Inactive (Deactivates Account)</flux:select.option>
                </flux:select>

                @if(in_array($bulkStatus, ['dropped', 'graduated', 'inactive']))
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-300">
                        <strong>Security Notice:</strong> Setting student type to <strong>{{ ucfirst($bulkStatus) }}</strong> will automatically deactivate login access for these accounts while preserving all historical records.
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkStatusModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="bulkSetStatus">
                    Apply Student Type
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bulk Change Year Level Modal -->
    <flux:modal wire:model="showBulkYearModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    Update Year Level for {{ count($selectedIds) }} Students
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Select the target academic year level for all selected students.
                </p>
            </div>

            <div class="space-y-4">
                <flux:select wire:model="bulkYearLevel" label="Target Year Level">
                    <flux:select.option value="1">1st Year</flux:select.option>
                    <flux:select.option value="2">2nd Year</flux:select.option>
                    <flux:select.option value="3">3rd Year</flux:select.option>
                    <flux:select.option value="4">4th Year</flux:select.option>
                </flux:select>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                <flux:button variant="ghost" wire:click="$set('showBulkYearModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="bulkSetYearLevel">
                    Apply Year Level
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Bulk Delete Confirmation Modal -->
    <flux:modal wire:model="showBulkDeleteModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    Bulk Delete Students
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Review accounts eligible for deletion.
                </p>
            </div>

            <div class="space-y-3 text-sm">
                @if($bulkDeleteEligibleCount > 0)
                    <p class="text-zinc-700 dark:text-zinc-300">
                        You have selected <strong class="font-semibold text-zinc-900 dark:text-white">{{ $bulkDeleteEligibleCount }}</strong> account(s) that have zero evaluation history and can be safely deleted.
                    </p>
                @endif

                @if($bulkDeleteBlockedCount > 0)
                    <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-800 dark:text-amber-300 space-y-1">
                        <p class="font-semibold">Audit Protection Notice:</p>
                        <p>{{ $bulkDeleteBlockedCount }} of the selected student(s) have historical evaluation records. To protect academic audit integrity, those accounts will <strong>not</strong> be deleted.</p>
                    </div>
                @endif

                @if($bulkDeleteEligibleCount === 0)
                    <p class="text-rose-600 dark:text-rose-400 font-medium">
                        None of the selected students can be deleted because all of them have historical evaluations. You can deactivate their accounts instead.
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

    <!-- Review Selected Students Modal -->
    <flux:modal wire:model="showReviewSelectionModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-3xl !p-4 sm:!p-6">
        <div class="space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800 pr-10">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white flex items-center gap-2">
                    <span>Selected Students</span>
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
                                <th class="py-2.5 px-3 font-semibold text-xs w-28">Student ID</th>
                                <th class="py-2.5 px-3 font-semibold text-xs">Full Name</th>
                                <th class="py-2.5 px-3 font-semibold text-xs">Program & Section</th>
                                <th class="py-2.5 px-3 font-semibold text-xs w-28">Student Type</th>
                                <th class="py-2.5 px-3 font-semibold text-xs w-14 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @forelse($selectedUsersList as $selUser)
                                <tr wire:key="selected-preview-{{ $selUser->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-2.5 px-3 font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                        {{ $selUser->student?->student_number ?: 'N/A' }}
                                    </td>
                                    <td class="py-2.5 px-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-zinc-900 dark:text-white truncate">
                                                {{ $selUser->student?->formatted_name ?? $selUser->name }}
                                            </p>
                                            <p class="text-xs text-zinc-500 dark:text-zinc-400 truncate">
                                                {{ $selUser->email }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-xs text-zinc-600 dark:text-zinc-300">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $selUser->student?->program?->code ?: 'Unassigned' }}</span>
                                        @if($selUser->student?->section)
                                            <span class="text-zinc-400 dark:text-zinc-500 font-mono ml-1">{{ $selUser->student->section }}</span>
                                        @endif
                                        @if($selUser->student?->year_level)
                                            <span class="text-zinc-500 dark:text-zinc-400">· Yr {{ $selUser->student->year_level }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @php
                                            $selSt = strtolower($selUser->student?->status ?? 'regular');
                                        @endphp
                                        @if($selSt === 'regular')
                                            <flux:badge color="emerald" size="sm">Regular</flux:badge>
                                        @elseif($selSt === 'irregular')
                                            <flux:badge color="amber" size="sm">Irregular</flux:badge>
                                        @elseif($selSt === 'loa')
                                            <flux:badge color="zinc" size="sm">LOA</flux:badge>
                                        @elseif($selSt === 'dropped')
                                            <flux:badge color="rose" size="sm">Dropped</flux:badge>
                                        @elseif($selSt === 'graduated')
                                            <flux:badge color="purple" size="sm">Graduated</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm" class="capitalize">{{ $selSt }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                        <flux:button size="xs" variant="ghost" icon="x-mark" class="text-zinc-400 hover:text-rose-600 dark:hover:text-rose-400" wire:click="removeSelected({{ $selUser->id }})" title="Remove from selection" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-xs text-zinc-500 dark:text-zinc-400">
                                        No students are currently selected.
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
