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

    public function updatedSelectedProgramId()
    {
        $this->resetPage();
    }

    public function updatedSelectedYearLevel()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedSortDirection()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedProgramId', 'selectedYearLevel', 'statusFilter', 'sortDirection']);
        $this->resetPage();
    }

    public function prepareCreate()
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

        return [
            'users' => $query->with(['student.program', 'roles'])
                ->orderBy('students.last_name', $orderDirection)
                ->orderBy('students.first_name', $orderDirection)
                ->paginate(10),
            'programs' => Program::orderBy('name')->get(),
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
                'is_active' => true,
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

            $this->editingUser->update([
                'name' => $this->editingUser->student->fresh()->formatted_name,
                'email' => strtolower(trim($this->email)),
            ]);
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

<div class="w-full flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">Manage Students</flux:heading>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button variant="outline" icon="arrow-down-tray" wire:click="exportStudents">
                Export CSV
            </flux:button>
            <flux:button variant="outline" icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                Import Students
            </flux:button>
            <flux:button variant="primary" wire:click="prepareCreate" icon="plus">
                Create Student
            </flux:button>
        </div>
    </div>
    
    <!-- Filters Bar -->
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[260px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or student ID..." />
        </div>
        
        <div class="w-full md:w-52">
            <flux:select wire:model.live="selectedProgramId" placeholder="Filter by Program">
                <flux:select.option value="">All Programs</flux:select.option>
                <flux:select.option value="none">Unassigned (None)</flux:select.option>
                @foreach($programs as $prog)
                    <flux:select.option value="{{ $prog->id }}">{{ $prog->code }} - {{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-40">
            <flux:select wire:model.live="selectedYearLevel" placeholder="Filter by Year Level">
                <flux:select.option value="">All Year Levels</flux:select.option>
                <flux:select.option value="1">1st Year</flux:select.option>
                <flux:select.option value="2">2nd Year</flux:select.option>
                <flux:select.option value="3">3rd Year</flux:select.option>
                <flux:select.option value="4">4th Year</flux:select.option>
            </flux:select>
        </div>

        <div class="w-full md:w-44">
            <flux:select wire:model.live="statusFilter" placeholder="Filter by Status">
                <flux:select.option value="">All Statuses</flux:select.option>
                <flux:select.option value="regular">Regular</flux:select.option>
                <flux:select.option value="irregular">Irregular</flux:select.option>
                <flux:select.option value="loa">Leave of Absence (LOA)</flux:select.option>
                <flux:select.option value="dropped">Dropped</flux:select.option>
                <flux:select.option value="graduated">Graduated</flux:select.option>
                <flux:select.option value="inactive">Inactive</flux:select.option>
            </flux:select>
        </div>
        
        <div class="flex items-center gap-2">
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
                <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
            @endif
        </div>
    </div>
    
    <div wire:loading wire:target="search, selectedProgramId, selectedYearLevel, statusFilter, sortDirection, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="7" />
    </div>

    <div wire:loading.remove wire:target="search, selectedProgramId, selectedYearLevel, statusFilter, sortDirection, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700 shadow-2xs">
            <table class="w-full min-w-[850px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th class="w-[14%] min-w-[120px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Student ID</th>
                        <th class="w-[20%] min-w-[170px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Full Name</th>
                        <th class="w-[18%] min-w-[150px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Email</th>
                        <th class="w-[14%] min-w-[130px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Program & Section</th>
                        <th class="w-[10%] min-w-[90px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-center whitespace-nowrap">Year</th>
                        <th class="w-[12%] min-w-[110px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-center whitespace-nowrap">Enrollment Status</th>
                        <th class="w-[12%] min-w-[90px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($users as $user)
                        <tr wire:key="{{ $user->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-4 py-3 dark:text-zinc-300 font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                {{ $user->student?->student_number }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-300 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $user->student?->formatted_name ?? $user->name }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-400 text-xs">
                                {{ $user->email }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-300 text-xs whitespace-nowrap">
                                <span class="font-bold block text-zinc-900 dark:text-zinc-100">{{ $user->student?->program?->code ?: 'Unassigned' }}</span>
                                @if($user->student?->section)
                                    <span class="text-zinc-500 dark:text-zinc-400 font-mono text-[11px] block">{{ $user->student->section }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
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
                                <flux:badge variant="neutral" size="sm" class="font-semibold whitespace-nowrap">
                                    {{ $yrLabel }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @php
                                    $st = $user->student?->status ?? 'regular';
                                    $stBadge = match($st) {
                                        'regular' => ['variant' => 'success', 'label' => 'Regular'],
                                        'irregular' => ['variant' => 'warning', 'label' => 'Irregular'],
                                        'loa' => ['variant' => 'neutral', 'label' => 'LOA'],
                                        'dropped' => ['variant' => 'danger', 'label' => 'Dropped'],
                                        'graduated' => ['variant' => 'primary', 'label' => 'Graduated'],
                                        default => ['variant' => 'neutral', 'label' => ucfirst($st)]
                                    };
                                @endphp
                                <flux:badge :variant="$stBadge['variant']" size="sm" class="font-bold">
                                    {{ $stBadge['label'] }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
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
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
                                No student accounts found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $users->links() }}
        </div>
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
</div>
