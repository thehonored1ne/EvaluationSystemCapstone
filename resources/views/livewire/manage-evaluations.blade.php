<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Employee;
use App\Models\User;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    public string $activeTab = 'student';
    public string $search = '';
    public string $selectedDepartmentId = '';
    public string $selectedStatus = 'all';

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getDepartmentsProperty()
    {
        return Department::orderBy('name')->get();
    }

    // Reset page when filters change
    public function updatedSearch() { }
    public function updatedSelectedDepartmentId() { }
    public function updatedSelectedStatus() { }

    public function getClassesProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return collect();

        $user = auth()->user();
        $query = AcademicClass::where('semester_id', $sem->id)
            ->with(['subject', 'teacher.department', 'students']);

        if ($user->hasRole('program head') && $user->employee) {
            $deptId = $user->employee->department_id;
            $query->whereHas('teacher', fn($q) => $q->where('department_id', $deptId));
        } elseif ($user->hasRole('dean') && $user->employee) {
            $deptId = $this->selectedDepartmentId ?: $user->employee->department_id;
            if ($deptId) {
                $query->whereHas('teacher', fn($q) => $q->where('department_id', $deptId));
            }
        } elseif ($user->hasRole('admin')) {
            if ($this->selectedDepartmentId) {
                $query->whereHas('teacher', fn($q) => $q->where('department_id', $this->selectedDepartmentId));
            }
        }

        $allClasses = $query->get()->map(function ($class) {
            $enrolled = $class->students->count();
            $evaluated = Evaluation::where('class_id', $class->id)->count();
            $percentage = $enrolled > 0 ? min(100, round(($evaluated / $enrolled) * 100)) : 0;

            $status = 'pending';
            if ($percentage === 100) {
                $status = 'completed';
            } elseif ($percentage > 0) {
                $status = 'in_progress';
            }

            return (object) [
                'id' => $class->id,
                'subject' => $class->subject,
                'teacher' => $class->teacher,
                'section' => $class->section,
                'department' => $class->teacher?->department,
                'enrolled' => $enrolled,
                'evaluated' => $evaluated,
                'percentage' => $percentage,
                'status' => $status,
            ];
        });

        // Filter by search & status
        return $allClasses->filter(function ($c) {
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $codeMatch = str_contains(strtolower($c->subject?->code ?? ''), $searchLower);
                $titleMatch = str_contains(strtolower($c->subject?->name ?? ''), $searchLower);
                $teacherMatch = str_contains(strtolower($c->teacher?->full_name ?? ''), $searchLower);
                $sectionMatch = str_contains(strtolower($c->section ?? ''), $searchLower);

                if (!$codeMatch && !$titleMatch && !$teacherMatch && !$sectionMatch) {
                    return false;
                }
            }

            if ($this->selectedStatus !== 'all' && $c->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        });
    }

    public function getSupervisorTrackingProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return collect();

        $query = Employee::whereIn('role', ['dean', 'program head'])
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($sem) {
            $user = $emp->user;
            if (!$user) return null;

            // Target ratees in their department
            $facultyCount = Employee::where('department_id', $emp->department_id)
                ->where('role', 'faculty')
                ->where('id', '!=', $emp->id)
                ->count();

            $submittedCount = Evaluation::where('evaluator_id', $user->id)
                ->where('semester_id', $sem->id)
                ->where('evaluation_type', 'downward')
                ->count();

            $pct = $facultyCount > 0 ? min(100, round(($submittedCount / $facultyCount) * 100)) : 0;
            $status = ($pct === 100 && $facultyCount > 0) ? 'completed' : ($submittedCount > 0 ? 'in_progress' : 'pending');

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'role' => ucfirst($emp->role),
                'department' => $emp->department,
                'target_count' => $facultyCount,
                'submitted_count' => $submittedCount,
                'percentage' => $pct,
                'status' => $status,
            ];
        })->filter()->values();
    }

    public function getSelfTrackingProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return collect();

        $query = Employee::where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($sem) {
            $user = $emp->user;
            $submitted = false;
            $submittedAt = null;

            if ($user) {
                $eval = Evaluation::where('evaluator_id', $user->id)
                    ->where('semester_id', $sem->id)
                    ->where('evaluation_type', 'self')
                    ->first();
                if ($eval) {
                    $submitted = true;
                    $submittedAt = $eval->created_at;
                }
            }

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'role' => ucfirst($emp->role),
                'department' => $emp->department,
                'submitted' => $submitted,
                'submitted_at' => $submittedAt,
                'status' => $submitted ? 'completed' : 'pending',
            ];
        })->filter(function ($e) {
            if ($this->search) {
                return str_contains(strtolower($e->name), strtolower($this->search));
            }
            return true;
        })->values();
    }

    public function sendReminderToast()
    {
        \Flux::toast(
            heading: 'Reminders Broadcasted',
            text: 'Evaluation submission reminders have been sent to pending evaluators.',
            variant: 'success'
        );
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1" class="text-left">Completion Tracking</flux:heading>
            <flux:subheading class="text-left">
                Real-time evaluation submission progress & completion tracking across all roles.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            @if(auth()->user()->hasAnyRole(['admin', 'dean']))
                <div class="w-48">
                    <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <flux:button variant="primary" icon="paper-airplane" wire:click="sendReminderToast" size="sm">
                Send Reminders
            </flux:button>
        </div>
    </div>

    <!-- Active Semester Indicator -->
    @if($this->activeSemester)
        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="size-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">Active Period</span>
                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                    A.Y. {{ $this->activeSemester->academicYear->name }} — {{ $this->activeSemester->name }}
                </span>
            </div>
            <flux:badge variant="{{ $this->activeSemester->is_evaluation_open ? 'success' : 'danger' }}" size="sm" class="font-bold">
                {{ $this->activeSemester->is_evaluation_open ? 'Evaluations Open' : 'Evaluations Closed' }}
            </flux:badge>
        </div>
    @endif

    <!-- Top 4 Summary Stat Cards (with 5px dark red #800000 left border & odometer) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        @php
            $classes = $this->classes;
            $totalClasses = $classes->count();
            $avgStudentProgress = $totalClasses > 0 ? round($classes->sum('percentage') / $totalClasses) : 0;
            $totalSubmissions = Evaluation::where('semester_id', $this->activeSemester?->id)->count();

            $supervisors = $this->supervisorTracking;
            $superCount = $supervisors->count();
            $avgSuperProgress = $superCount > 0 ? round($supervisors->sum('percentage') / $superCount) : 0;

            $selfTrack = $this->selfTracking;
            $selfTotal = $selfTrack->count();
            $selfDone = $selfTrack->where('submitted', true)->count();
            $selfPct = $selfTotal > 0 ? round(($selfDone / $selfTotal) * 100) : 0;
        @endphp

        <!-- Card 1: Total Submissions Recorded -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2" style="border-left: 5px solid #800000 !important;">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Total Submissions Received</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                    <x-odometer :value="$totalSubmissions" />
                </span>
                <flux:icon icon="clipboard-document-check" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-[11px] text-zinc-400">Across all 5 evaluation perspectives</span>
        </div>

        <!-- Card 2: Student Evaluation Completion -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2" style="border-left: 5px solid #800000 !important;">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student Progress</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    <x-odometer :value="$avgStudentProgress" suffix="%" />
                </span>
                <flux:icon icon="academic-cap" class="size-6 text-indigo-500" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-indigo-600 h-1.5 rounded-full transition-all duration-300" style="width: {{ $avgStudentProgress }}%"></div>
            </div>
        </div>

        <!-- Card 3: Supervisor Ratings Completion -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2" style="border-left: 5px solid #800000 !important;">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Supervisor Ratings</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">
                    <x-odometer :value="$avgSuperProgress" suffix="%" />
                </span>
                <flux:icon icon="user-group" class="size-6 text-emerald-500" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-emerald-600 h-1.5 rounded-full transition-all duration-300" style="width: {{ $avgSuperProgress }}%"></div>
            </div>
        </div>

        <!-- Card 4: Self Appraisals Submitted -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2" style="border-left: 5px solid #800000 !important;">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Self Appraisals Done</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-amber-600 dark:text-amber-400">
                    <x-odometer :value="$selfDone" /> / <x-odometer :value="$selfTotal" />
                </span>
                <flux:icon icon="user" class="size-6 text-amber-500" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-300" style="width: {{ $selfPct }}%"></div>
            </div>
        </div>
    </div>

    <!-- Perspective Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800 flex gap-2">
        <button 
            type="button"
            wire:click="$set('activeTab', 'student')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'student' ? 'border-[#800000] text-[#800000] dark:border-red-500 dark:text-red-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="academic-cap" class="size-4" />
            Student Upward Progress
        </button>

        <button 
            type="button"
            wire:click="$set('activeTab', 'supervisor')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'supervisor' ? 'border-[#800000] text-[#800000] dark:border-red-500 dark:text-red-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="briefcase" class="size-4" />
            Supervisor & Executive Ratings
        </button>

        <button 
            type="button"
            wire:click="$set('activeTab', 'self')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'self' ? 'border-[#800000] text-[#800000] dark:border-red-500 dark:text-red-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="user" class="size-4" />
            Self Appraisals
        </button>
    </div>

    <!-- TAB 1: Student Upward Class Progress -->
    @if($activeTab === 'student')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search subject, section, or professor..." 
                        clearable
                    />
                </div>

                <div class="flex gap-3">
                    <flux:select wire:model.live="selectedStatus" class="w-40">
                        <flux:select.option value="all">All Statuses</flux:select.option>
                        <flux:select.option value="completed">100% Completed</flux:select.option>
                        <flux:select.option value="in_progress">In Progress</flux:select.option>
                        <flux:select.option value="pending">Pending (0%)</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @if($classes->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="clipboard-document-list" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No classes match your search or filter criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Subject & Class</th>
                                <th class="px-6 py-3.5">Section</th>
                                <th class="px-6 py-3.5">Professor</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Submissions</th>
                                <th class="px-6 py-3.5">Completion Rate</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($classes as $c)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $c->subject?->code }}</div>
                                        <div class="text-xs text-zinc-500">{{ $c->subject?->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $c->section }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $c->teacher?->full_name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $c->teacher?->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded">
                                            {{ $c->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $c->evaluated }} / {{ $c->enrolled }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $c->percentage === 100 ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ $c->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $c->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($c->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">100% Completed</flux:badge>
                                        @elseif($c->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: Supervisor & Executive Ratings -->
    @if($activeTab === 'supervisor')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-3.5">Supervisor Name</th>
                            <th class="px-6 py-3.5">Role</th>
                            <th class="px-6 py-3.5">Department</th>
                            <th class="px-6 py-3.5">Ratings Completed</th>
                            <th class="px-6 py-3.5">Completion Progress</th>
                            <th class="px-6 py-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @forelse($this->supervisorTracking as $sup)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $sup->name }}
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge variant="neutral" size="sm" class="font-bold">{{ $sup->role }}</flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded">
                                        {{ $sup->department?->code ?: 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ $sup->submitted_count }} / {{ $sup->target_count }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $sup->percentage }}%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $sup->percentage }}%</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($sup->status === 'completed')
                                        <flux:badge variant="success" size="sm" class="font-bold">Completed</flux:badge>
                                    @elseif($sup->status === 'in_progress')
                                        <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                    @else
                                        <flux:badge variant="neutral" size="sm" class="font-bold">Not Started</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-zinc-400">
                                    No department supervisors found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- TAB 3: Self Appraisals -->
    @if($activeTab === 'self')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-3.5">Employee Name</th>
                            <th class="px-6 py-3.5">Role</th>
                            <th class="px-6 py-3.5">Department</th>
                            <th class="px-6 py-3.5">Submission Date</th>
                            <th class="px-6 py-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @forelse($this->selfTracking as $self)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $self->name }}
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge variant="neutral" size="sm" class="font-bold">{{ $self->role }}</flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded">
                                        {{ $self->department?->code ?: 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $self->submitted_at ? $self->submitted_at->format('M d, Y h:i A') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($self->submitted)
                                        <flux:badge variant="success" size="sm" class="font-bold">Submitted</flux:badge>
                                    @else
                                        <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-zinc-400">
                                    No active employees found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
