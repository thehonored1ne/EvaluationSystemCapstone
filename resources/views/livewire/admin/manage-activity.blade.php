<?php

use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'audit'; // 'audit' | 'submissions'

    // Audit Log Filters
    #[Url(as: 'search_audit')]
    public string $searchAudit = '';

    #[Url(as: 'event')]
    public string $eventFilter = '';

    #[Url(as: 'module')]
    public string $moduleFilter = '';

    #[Url(as: 'audit_from')]
    public string $auditDateFrom = '';

    #[Url(as: 'audit_to')]
    public string $auditDateTo = '';

    public int $perPageAudit = 20;

    // Submissions Ledger Filters
    #[Url(as: 'search_sub')]
    public string $searchSubmissions = '';

    #[Url(as: 'sem')]
    public string $semesterFilter = '';

    #[Url(as: 'role')]
    public string $evaluatorRoleFilter = '';

    #[Url(as: 'dept')]
    public string $deptFilter = '';

    #[Url(as: 'sub_from')]
    public string $submissionDateFrom = '';

    #[Url(as: 'sub_to')]
    public string $submissionDateTo = '';

    public int $perPageSubmissions = 20;

    public function mount(): void
    {
        if (empty($this->semesterFilter)) {
            $active = Semester::getActive();
            $this->semesterFilter = $active ? (string) $active->id : '';
        }
    }

    public function updatingSearchAudit(): void
    {
        $this->resetPage('auditPage');
    }

    public function updatingEventFilter(): void
    {
        $this->resetPage('auditPage');
    }

    public function updatingModuleFilter(): void
    {
        $this->resetPage('auditPage');
    }

    public function updatingAuditDateFrom(): void
    {
        $this->resetPage('auditPage');
    }

    public function updatingAuditDateTo(): void
    {
        $this->resetPage('auditPage');
    }

    public function updatingSearchSubmissions(): void
    {
        $this->resetPage('subPage');
    }

    public function updatingSemesterFilter(): void
    {
        $this->resetPage('subPage');
    }

    public function updatingEvaluatorRoleFilter(): void
    {
        $this->resetPage('subPage');
    }

    public function updatingDeptFilter(): void
    {
        $this->resetPage('subPage');
    }

    public function updatingSubmissionDateFrom(): void
    {
        $this->resetPage('subPage');
    }

    public function updatingSubmissionDateTo(): void
    {
        $this->resetPage('subPage');
    }

    public function resetAuditFilters(): void
    {
        $this->searchAudit = '';
        $this->eventFilter = '';
        $this->moduleFilter = '';
        $this->auditDateFrom = '';
        $this->auditDateTo = '';
        $this->resetPage('auditPage');
    }

    public function resetSubmissionFilters(): void
    {
        $this->searchSubmissions = '';
        $this->evaluatorRoleFilter = '';
        $this->deptFilter = '';
        $this->submissionDateFrom = '';
        $this->submissionDateTo = '';
        $active = Semester::getActive();
        $this->semesterFilter = $active ? (string) $active->id : '';
        $this->resetPage('subPage');
    }

    public function rendering(): void
    {
        $this->viewData = null;
    }

    public function placeholder()
    {
        return view('livewire.placeholders.manage-activity-skeleton');
    }

    private ?array $viewData = null;

    public function with(): array
    {
        if ($this->viewData !== null) {
            return $this->viewData;
        }

        $semesters = Cache::remember('semesters_all_with_ay', 300, function () {
            return Semester::with('academicYear')->orderByDesc('id')->get();
        });
        $departments = Department::getCachedList();

        $totalAuditCount = Cache::remember('activity_log_total_count', 60, fn () => Activity::count());
        $totalSubmissionsCount = Cache::remember('submissions_total_count', 60, fn () => Evaluation::count());

        if ($this->activeTab === 'audit') {
            // 1. Audit Logs Query with Eager Loading
            $auditQuery = Activity::query()
                ->with(['causer.employee'])
                ->latest('id');

            if (trim($this->searchAudit) !== '') {
                $term = '%'.trim($this->searchAudit).'%';
                $auditQuery->where(function ($q) use ($term) {
                    $q->where('description', 'like', $term)
                        ->orWhere('event', 'like', $term)
                        ->orWhere('properties', 'like', $term)
                        ->orWhereHasMorph('causer', [User::class], function ($sub) use ($term) {
                            $sub->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            }

            if ($this->eventFilter !== '') {
                if ($this->eventFilter === 'bulk') {
                    $auditQuery->where(function ($q) {
                        $q->where('event', 'bulk_updated')
                            ->orWhere('description', 'like', 'Bulk %');
                    });
                } else {
                    $auditQuery->where('event', $this->eventFilter);
                }
            }

            if ($this->moduleFilter !== '') {
                $auditQuery->where('subject_type', 'like', '%'.$this->moduleFilter);
            }

            if ($this->auditDateFrom !== '') {
                try {
                    $auditQuery->where('created_at', '>=', Carbon::parse($this->auditDateFrom, 'Asia/Manila'));
                } catch (\Throwable $e) {}
            }

            if ($this->auditDateTo !== '') {
                try {
                    $auditQuery->where('created_at', '<=', Carbon::parse($this->auditDateTo, 'Asia/Manila'));
                } catch (\Throwable $e) {}
            }

            $auditLogs = $auditQuery->paginate($this->perPageAudit, ['*'], 'auditPage');
            $submissions = new LengthAwarePaginator([], 0, $this->perPageSubmissions, 1, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'subPage',
            ]);
        } else {
            // 2. Submissions Ledger Query with Eager Loading
            $submissionsQuery = Evaluation::query()
                ->with([
                    'evaluator.employee.department',
                    'evaluator.student.program.department',
                    'evaluatee.employee.department',
                    'evaluatee.student.program.department',
                    'class.subject',
                ])
                ->latest('id');

            if ($this->semesterFilter !== '') {
                $submissionsQuery->where('semester_id', (int) $this->semesterFilter);
            }

            if (trim($this->searchSubmissions) !== '') {
                $term = '%'.trim($this->searchSubmissions).'%';
                $submissionsQuery->where(function ($q) use ($term) {
                    $q->whereHas('evaluatee', fn ($sub) => $sub->where('name', 'like', $term))
                        ->orWhereHas('evaluator', fn ($sub) => $sub->where('name', 'like', $term))
                        ->orWhereHas('class.subject', fn ($sub) => $sub->where('name', 'like', $term)->orWhere('code', 'like', $term));
                });
            }

            if ($this->deptFilter !== '') {
                $deptId = (int) $this->deptFilter;
                $submissionsQuery->where(function ($q) use ($deptId) {
                    $q->whereHas('evaluatee.employee', fn ($e) => $e->where('department_id', $deptId))
                        ->orWhereHas('evaluatee.student.program', fn ($p) => $p->where('department_id', $deptId));
                });
            }

            if ($this->evaluatorRoleFilter !== '') {
                $role = $this->evaluatorRoleFilter;
                if ($role === 'student') {
                    $submissionsQuery->whereHas('evaluator.student');
                } elseif ($role === 'faculty') {
                    $submissionsQuery->whereHas('evaluator.employee', fn ($e) => $e->where('role', 'faculty'));
                } elseif ($role === 'dean') {
                    $submissionsQuery->whereHas('evaluator.employee', fn ($e) => $e->where('role', 'dean'));
                } elseif ($role === 'program head') {
                    $submissionsQuery->whereHas('evaluator.employee', fn ($e) => $e->where('role', 'program head'));
                } elseif ($role === 'department head') {
                    $submissionsQuery->whereHas('evaluator.employee', fn ($e) => $e->where('role', 'department head'));
                } elseif ($role === 'staff') {
                    $submissionsQuery->whereHas('evaluator.employee', fn ($e) => $e->where('role', 'staff'));
                }
            }

            if ($this->submissionDateFrom !== '') {
                try {
                    $submissionsQuery->where('created_at', '>=', Carbon::parse($this->submissionDateFrom, 'Asia/Manila'));
                } catch (\Throwable $e) {}
            }

            if ($this->submissionDateTo !== '') {
                try {
                    $submissionsQuery->where('created_at', '<=', Carbon::parse($this->submissionDateTo, 'Asia/Manila'));
                } catch (\Throwable $e) {}
            }

            $submissions = $submissionsQuery->paginate($this->perPageSubmissions, ['*'], 'subPage');
            $auditLogs = new LengthAwarePaginator([], 0, $this->perPageAudit, 1, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'auditPage',
            ]);
        }

        return $this->viewData = [
            'auditLogs' => $auditLogs,
            'submissions' => $submissions,
            'semesters' => $semesters,
            'departments' => $departments,
            'totalAuditCount' => $totalAuditCount,
            'totalSubmissionsCount' => $totalSubmissionsCount,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <flux:heading size="xl" level="1" class="font-extrabold tracking-tight">System Activity & Logs</flux:heading>
                <flux:badge variant="neutral" size="sm" class="font-bold">
                    {{ number_format($totalAuditCount) }} Audit Logs &bull; {{ number_format($totalSubmissionsCount) }} Submissions
                </flux:badge>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Audit trail of administrative actions and historical evaluation submissions ledger.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.dashboard') }}" wire:navigate size="sm" variant="subtle" icon="arrow-left" class="border border-zinc-200 dark:border-zinc-700">
                Dashboard
            </flux:button>
        </div>
    </div>

    <!-- Main Card Container with Tab Switcher -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col overflow-hidden">
        <!-- Tab Navigation Bar -->
        <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-2 p-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs shrink-0">
                <button
                    type="button"
                    wire:click="$set('activeTab', 'audit')"
                    class="px-3.5 py-1.5 rounded-md font-semibold transition-colors cursor-pointer {{ $activeTab === 'audit' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    System Audit Trail
                </button>
                <button
                    type="button"
                    wire:click="$set('activeTab', 'submissions')"
                    class="px-3.5 py-1.5 rounded-md font-semibold transition-colors cursor-pointer {{ $activeTab === 'submissions' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100' }}"
                >
                    Submissions Ledger
                </button>
            </div>

            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                @if($activeTab === 'audit')
                    Showing {{ $auditLogs->firstItem() ?? 0 }} to {{ $auditLogs->lastItem() ?? 0 }} of {{ number_format($auditLogs->total()) }} audit events
                @else
                    Showing {{ $submissions->firstItem() ?? 0 }} to {{ $submissions->lastItem() ?? 0 }} of {{ number_format($submissions->total()) }} submitted evaluations
                @endif
            </span>
        </div>

        @if($activeTab === 'audit')
            <!-- AUDIT LOGS TAB CONTENT -->
            <div class="p-4 sm:p-6 flex flex-col gap-5">
                <!-- Filters Bar -->
                <div class="flex flex-col gap-3">
                    <!-- Top Row: Search and Categorical Filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <!-- Search Input -->
                        <div class="lg:col-span-2">
                            <flux:input
                                type="search"
                                wire:model.live.debounce.300ms="searchAudit"
                                placeholder="Search description, actor, email..."
                                icon="magnifying-glass"
                                clearable
                            />
                        </div>

                        <!-- Event Filter -->
                        <div>
                            <flux:select wire:model.live="eventFilter" placeholder="All Events">
                                <option value="">All Events</option>
                                <option value="created">Created</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                                <option value="bulk">Bulk Actions</option>
                            </flux:select>
                        </div>

                        <!-- Module Filter -->
                        <div>
                            <flux:select wire:model.live="moduleFilter" placeholder="All Modules">
                                <option value="">All Modules</option>
                                <option value="User">Users</option>
                                <option value="Employee">Employees</option>
                                <option value="Student">Students</option>
                                <option value="AcademicClass">Classes</option>
                                <option value="Subject">Subjects</option>
                                <option value="Department">Departments</option>
                                <option value="Program">Programs</option>
                                <option value="EvaluationQuestion">Questions</option>
                                <option value="EvaluationCriterion">Criteria</option>
                                <option value="Semester">Semesters</option>
                            </flux:select>
                        </div>
                    </div>

                    <!-- Date & Time Range Window Filter Bar -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-zinc-50/80 dark:bg-zinc-800/40 rounded-lg border border-zinc-200/80 dark:border-zinc-700/60 text-xs">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
                            <div class="flex items-center gap-1.5 font-semibold text-zinc-600 dark:text-zinc-400 shrink-0">
                                <flux:icon icon="calendar" class="size-4 text-zinc-500" />
                                <span>Date & Time Window:</span>
                            </div>
                            <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap sm:flex-nowrap">
                                <div class="w-full sm:w-56">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.live="auditDateFrom"
                                        placeholder="Start Date & Time"
                                        class="!text-xs"
                                    />
                                </div>
                                <span class="text-zinc-400 dark:text-zinc-500 font-medium shrink-0">to</span>
                                <div class="w-full sm:w-56">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.live="auditDateTo"
                                        placeholder="End Date & Time"
                                        class="!text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        @if($searchAudit || $eventFilter || $moduleFilter || $auditDateFrom || $auditDateTo)
                            <flux:button wire:click="resetAuditFilters" size="sm" variant="subtle" icon="x-mark" class="shrink-0">
                                Clear Filters
                            </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Audit Log Table -->
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[760px] lg:min-w-0 lg:table-fixed">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-4 w-44 lg:w-[18%]">Date & Time</th>
                                <th class="py-3.5 px-4 w-28 lg:w-[12%]">Action</th>
                                <th class="py-3.5 px-4 w-36 lg:w-[16%]">Module</th>
                                <th class="py-3.5 px-4 w-48 lg:w-[18%]">Actor</th>
                                <th class="py-3.5 px-4 min-w-[220px] lg:w-[36%]">Operation Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 bg-white dark:bg-zinc-900">
                            @forelse($auditLogs as $act)
                                @php
                                    $rawEvent = strtolower($act->event ?? '');
                                    $rawDesc = $act->description ?? '';
                                    $isBulk = $rawEvent === 'bulk_updated' || str_starts_with(strtolower($rawDesc), 'bulk');

                                    if ($isBulk) {
                                        $event = 'bulk';
                                        $eventBadgeText = 'BULK ACTION';
                                        $badgeClass = 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800';
                                    } else {
                                        $event = strtolower($act->event ?? $rawDesc ?: 'action');
                                        $eventBadgeText = strtoupper(Str::limit($event, 14, ''));
                                        $badgeClass = match ($event) {
                                            'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                                            'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400 border border-sky-200 dark:border-sky-800',
                                            'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800',
                                            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
                                        };
                                    }

                                    $subjectClass = class_basename($act->subject_type ?? '');
                                    $props = $act->properties ?? [];
                                    $attributes = $props['attributes'] ?? [];
                                    $old = $props['old'] ?? [];

                                    if (!empty($subjectClass)) {
                                        $subjectLabel = match ($subjectClass) {
                                            'User' => 'User Account',
                                            'Employee' => 'Employee Profile',
                                            'Student' => 'Student Profile',
                                            'Department' => 'Department',
                                            'Program' => 'Academic Program',
                                            'AcademicClass' => 'Class Section',
                                            'Subject' => 'Subject',
                                            'EvaluationQuestion' => 'Question',
                                            'EvaluationCriterion' => 'Criterion',
                                            'Evaluation' => 'Evaluation Record',
                                            'Semester' => 'Semester & Window',
                                            'AcademicYear' => 'Academic Year',
                                            default => $subjectClass,
                                        };
                                    } else {
                                        $descLower = strtolower($rawDesc);
                                        if (str_contains($descLower, 'student')) {
                                            $subjectLabel = 'Student Profile';
                                        } elseif (str_contains($descLower, 'employee')) {
                                            $subjectLabel = 'Employee Profile';
                                        } elseif (str_contains($descLower, 'department')) {
                                            $subjectLabel = 'Department';
                                        } else {
                                            $subjectLabel = 'System Record';
                                        }
                                    }

                                    $detailStr = $act->description;
                                    if (in_array($act->description, ['created', 'updated', 'deleted', 'custom', null])) {
                                        if ($subjectClass === 'User') {
                                            $name = $attributes['name'] ?? $old['name'] ?? 'User';
                                            $email = $attributes['email'] ?? $old['email'] ?? '';
                                            $detailStr = ($event === 'created' ? 'Created user: ' : ($event === 'deleted' ? 'Deleted user: ' : 'Updated user: '))."{$name}".($email ? " ({$email})" : '');
                                        } elseif ($subjectClass === 'Employee') {
                                            $fn = $attributes['first_name'] ?? $old['first_name'] ?? '';
                                            $ln = $attributes['last_name'] ?? $old['last_name'] ?? '';
                                            $name = trim("{$fn} {$ln}") ?: 'Employee';
                                            $detailStr = ($event === 'created' ? 'Created employee: ' : ($event === 'deleted' ? 'Deleted employee: ' : 'Updated employee: '))."{$name}";
                                        } elseif ($subjectClass === 'Student') {
                                            $fn = $attributes['first_name'] ?? $old['first_name'] ?? '';
                                            $ln = $attributes['last_name'] ?? $old['last_name'] ?? '';
                                            $name = trim("{$fn} {$ln}") ?: 'Student';
                                            $detailStr = ($event === 'created' ? 'Enrolled student: ' : ($event === 'deleted' ? 'Deleted student: ' : 'Updated student: '))."{$name}";
                                        } elseif ($subjectClass === 'Department') {
                                            $dName = $attributes['name'] ?? $old['name'] ?? 'Department';
                                            $dCode = $attributes['code'] ?? $old['code'] ?? '';
                                            $detailStr = ($event === 'created' ? 'Created department: ' : ($event === 'deleted' ? 'Deleted department: ' : 'Updated department: '))."{$dName}".($dCode ? " ({$dCode})" : '');
                                        } elseif ($subjectClass === 'Program') {
                                            $pName = $attributes['name'] ?? $old['name'] ?? 'Program';
                                            $pCode = $attributes['code'] ?? $old['code'] ?? '';
                                            $detailStr = ($event === 'created' ? 'Created program: ' : ($event === 'deleted' ? 'Deleted program: ' : 'Updated program: '))."{$pName}".($pCode ? " ({$pCode})" : '');
                                        } elseif ($subjectClass === 'Subject') {
                                            $sName = $attributes['name'] ?? $old['name'] ?? 'Subject';
                                            $sCode = $attributes['code'] ?? $old['code'] ?? '';
                                            $detailStr = ($event === 'created' ? 'Created subject: ' : ($event === 'deleted' ? 'Deleted subject: ' : 'Updated subject: '))."{$sName}".($sCode ? " ({$sCode})" : '');
                                        } elseif ($subjectClass === 'AcademicClass') {
                                            $sec = $attributes['section'] ?? $old['section'] ?? 'Class Section';
                                            $detailStr = ($event === 'created' ? 'Created class section: ' : ($event === 'deleted' ? 'Deleted class section: ' : 'Updated class section: '))."{$sec}";
                                        } else {
                                            $detailStr = ucfirst($event)." record in {$subjectLabel}";
                                        }
                                    }

                                    $causerName = $act->causer?->name ?? 'System Administrator';
                                    $causerRole = $act->causer?->employee?->role ? ucfirst($act->causer->employee->role) : ($act->causer ? 'Admin' : 'System');
                                @endphp
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $act->created_at ? $act->created_at->format('M d, Y') : '—' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 tabular-nums">
                                            {{ $act->created_at ? $act->created_at->format('h:i:s A') : '' }} &bull; {{ $act->created_at ? $act->created_at->diffForHumans() : '' }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap overflow-hidden">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                            {{ $eventBadgeText }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap overflow-hidden">
                                        <span class="inline-block max-w-full truncate px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700" title="{{ $subjectLabel }}">
                                            {{ $subjectLabel }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate" title="{{ $causerName }}">{{ $causerName }}</div>
                                        <div class="text-[10px] uppercase font-semibold text-zinc-500 dark:text-zinc-400 tracking-wider truncate">{{ $causerRole }}</div>
                                    </td>
                                    <td class="py-3.5 px-4 text-zinc-700 dark:text-zinc-300">
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100 line-clamp-2" title="{{ $detailStr }}">{{ $detailStr }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-10 text-center text-zinc-500 dark:text-zinc-400">
                                        <flux:icon icon="clock" class="size-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                                        <p class="font-semibold text-sm">No audit logs found matching the selected filters.</p>
                                        <p class="text-xs mt-1">Try changing your search keywords or resetting active filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($auditLogs->hasPages())
                    <div class="pt-2">
                        {{ $auditLogs->links() }}
                    </div>
                @endif
            </div>
        @else
            <!-- SUBMISSIONS LEDGER TAB CONTENT -->
            <div class="p-4 sm:p-6 flex flex-col gap-5">
                <!-- Filters Bar -->
                <div class="flex flex-col gap-3">
                    <!-- Top Row: Search and Categorical Filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <!-- Search Input -->
                        <div class="lg:col-span-2">
                            <flux:input
                                type="search"
                                wire:model.live.debounce.300ms="searchSubmissions"
                                placeholder="Search evaluatee, evaluator, or subject..."
                                icon="magnifying-glass"
                                clearable
                            />
                        </div>

                        <!-- Semester Filter -->
                        <div>
                            <flux:select wire:model.live="semesterFilter" placeholder="Select Term">
                                <option value="">All Semesters</option>
                                @foreach($semesters as $sem)
                                    <option value="{{ $sem->id }}">{{ $sem->academicYear?->name }} &bull; {{ $sem->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <!-- Evaluator Role Filter -->
                        <div>
                            <flux:select wire:model.live="evaluatorRoleFilter" placeholder="Evaluator Role">
                                <option value="">All Evaluator Roles</option>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="dean">Dean</option>
                                <option value="program head">Program Head</option>
                                <option value="department head">Department Head</option>
                                <option value="staff">Staff</option>
                            </flux:select>
                        </div>

                        <!-- Department Filter -->
                        <div>
                            <flux:select wire:model.live="deptFilter" placeholder="Department">
                                <option value="">All Departments</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->code }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <!-- Date & Time Range Window Filter Bar -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-zinc-50/80 dark:bg-zinc-800/40 rounded-lg border border-zinc-200/80 dark:border-zinc-700/60 text-xs">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 sm:gap-3 w-full sm:w-auto">
                            <div class="flex items-center gap-1.5 font-semibold text-zinc-600 dark:text-zinc-400 shrink-0">
                                <flux:icon icon="calendar" class="size-4 text-zinc-500" />
                                <span>Date & Time Window:</span>
                            </div>
                            <div class="flex items-center gap-2 w-full sm:w-auto flex-wrap sm:flex-nowrap">
                                <div class="w-full sm:w-56">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.live="submissionDateFrom"
                                        placeholder="Start Date & Time"
                                        class="!text-xs"
                                    />
                                </div>
                                <span class="text-zinc-400 dark:text-zinc-500 font-medium shrink-0">to</span>
                                <div class="w-full sm:w-56">
                                    <flux:input
                                        type="datetime-local"
                                        wire:model.live="submissionDateTo"
                                        placeholder="End Date & Time"
                                        class="!text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                        @if($searchSubmissions || $evaluatorRoleFilter || $deptFilter || $submissionDateFrom || $submissionDateTo)
                            <flux:button wire:click="resetSubmissionFilters" size="sm" variant="subtle" icon="x-mark" class="shrink-0">
                                Clear Filters
                            </flux:button>
                        @endif
                    </div>
                </div>

                <!-- Submissions Table -->
                <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs min-w-[760px] lg:min-w-0 lg:table-fixed">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/80 border-b border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-400 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="py-3.5 px-4 w-44 lg:w-[18%]">Submitted</th>
                                <th class="py-3.5 px-4 w-40 lg:w-[18%]">Evaluation Type</th>
                                <th class="py-3.5 px-4 w-44 lg:w-[20%]">Subject / Scope</th>
                                <th class="py-3.5 px-4 w-44 lg:w-[18%]">Target Faculty</th>
                                <th class="py-3.5 px-4 min-w-[160px] lg:w-[18%]">Evaluation Flow</th>
                                <th class="py-3.5 px-4 w-28 text-right lg:w-[8%]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 bg-white dark:bg-zinc-900">
                            @forelse($submissions as $sub)
                                @php
                                    $type = $sub->evaluation_type;
                                    $evaluatorRole = 'User';
                                    if ($sub->evaluator?->student) {
                                        $evaluatorRole = 'Student';
                                    } elseif ($sub->evaluator?->employee) {
                                        $roleName = $sub->evaluator->employee->role;
                                        $evaluatorRole = match ($roleName) {
                                            'faculty' => 'Professor',
                                            'program head' => 'Program Head',
                                            'department head' => 'Department Head',
                                            'dean' => 'Dean',
                                            'staff' => 'Staff',
                                            default => ucwords(str_replace('_', ' ', $roleName))
                                        };
                                    }

                                    $evaluateeRole = 'Faculty';
                                    if ($sub->evaluatee?->student) {
                                        $evaluateeRole = 'Student';
                                    } elseif ($sub->evaluatee?->employee) {
                                        $roleName = $sub->evaluatee->employee->role;
                                        $evaluateeRole = match ($roleName) {
                                            'faculty' => 'Professor',
                                            'program head' => 'Program Head',
                                            'department head' => 'Department Head',
                                            'dean' => 'Dean',
                                            'staff' => 'Staff',
                                            default => ucwords(str_replace('_', ' ', $roleName))
                                        };
                                    }

                                    if ($type === 'self') {
                                        $label = 'Self Evaluation';
                                        $flowDescription = "{$evaluatorRole} evaluates Self";
                                    } elseif ($evaluatorRole === 'Student') {
                                        $label = 'Student Evaluation';
                                        $flowDescription = 'Student evaluates Professor';
                                    } elseif ($evaluatorRole === 'Dean') {
                                        $label = 'Dean Evaluation';
                                        $flowDescription = "Dean evaluates {$evaluateeRole}";
                                    } elseif ($evaluatorRole === 'Program Head' && in_array($evaluateeRole, ['Professor', 'Faculty'])) {
                                        $label = 'Program Head Evaluation';
                                        $flowDescription = 'Program Head evaluates Professor';
                                    } elseif ($evaluatorRole === 'Department Head') {
                                        $label = 'Department Head Evaluation';
                                        $flowDescription = "Department Head evaluates {$evaluateeRole}";
                                    } elseif ($evaluatorRole === 'Professor' && $evaluateeRole === 'Professor') {
                                        $label = 'Peer Evaluation';
                                        $flowDescription = 'Professor evaluates Professor';
                                    } elseif ($evaluatorRole === 'Staff' && $evaluateeRole === 'Staff') {
                                        $label = 'Peer Evaluation';
                                        $flowDescription = 'Staff evaluates Staff';
                                    } elseif ($type === 'upward_employee' || ($evaluatorRole === 'Program Head' && $evaluateeRole === 'Dean') || ($evaluatorRole === 'Professor' && $evaluateeRole === 'Program Head') || ($evaluatorRole === 'Staff' && in_array($evaluateeRole, ['Dean', 'Program Head', 'Department Head']))) {
                                        $label = 'Supervisor Evaluation';
                                        $flowDescription = "{$evaluatorRole} evaluates {$evaluateeRole}";
                                    } elseif ($evaluateeRole === 'Staff') {
                                        $label = 'Staff Evaluation';
                                        $flowDescription = "{$evaluatorRole} evaluates Staff";
                                    } else {
                                        $label = match ($type) {
                                            'upward_student' => 'Student Evaluation',
                                            'peer' => 'Peer Evaluation',
                                            'dean' => 'Dean Evaluation',
                                            'downward' => 'Supervisor Evaluation',
                                            'upward_employee' => 'Supervisor Evaluation',
                                            default => 'Evaluation'
                                        };
                                        $flowDescription = "{$evaluatorRole} evaluates {$evaluateeRole}";
                                    }

                                    $subjectCode = $sub->class?->subject?->code;
                                    $sectionName = $sub->class?->section;
                                    $subjectDisplay = $subjectCode ? ($sectionName ? "Course: {$subjectCode} ({$sectionName})" : "Course: {$subjectCode}") : match ($type) {
                                        'self' => 'Self Appraisal',
                                        'peer' => 'Peer Review',
                                        'downward' => 'Supervisor Review',
                                        'upward_employee' => 'Supervisor Review',
                                        'upward_student' => 'Class Evaluation',
                                        default => 'Institutional Review'
                                    };

                                    $evaluateeName = $sub->evaluatee?->name ?? 'Faculty Member';
                                    $evaluateeDept = $sub->evaluatee?->employee?->department?->code ?? $sub->evaluatee?->student?->program?->department?->code ?? '';

                                    $categoryBadge = match ($label) {
                                        'Student Evaluation' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-800',
                                        'Peer Evaluation' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800',
                                        'Self Evaluation' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
                                        'Supervisor Evaluation', 'Dean Evaluation', 'Program Head Evaluation', 'Department Head Evaluation' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                                        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'
                                    };
                                @endphp
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="py-3.5 px-4 whitespace-nowrap" title="{{ $sub->created_at ? $sub->created_at->format('M d, Y h:i A') : '' }}">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $sub->created_at ? $sub->created_at->format('M d, Y') : '—' }}</div>
                                        <div class="text-[11px] text-zinc-500 dark:text-zinc-400 tabular-nums">
                                            {{ $sub->created_at ? $sub->created_at->format('h:i A') : '' }} &bull; {{ $sub->created_at ? $sub->created_at->diffForHumans() : '' }}
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $categoryBadge }} truncate max-w-full">
                                            {{ $label }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap font-medium">
                                        <span class="inline-block max-w-full truncate px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-800 dark:text-zinc-200" title="{{ $subjectDisplay }}">
                                            {{ $subjectDisplay }}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 whitespace-nowrap">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate" title="{{ $evaluateeName }}">{{ $evaluateeName }}</div>
                                        @if($evaluateeDept)
                                            <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-semibold uppercase">{{ $evaluateeDept }}</div>
                                        @endif
                                    </td>
                                    <td class="py-3.5 px-4 text-zinc-700 dark:text-zinc-300">
                                        <span class="truncate block" title="{{ $flowDescription }}">{{ $flowDescription }}</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                            <flux:icon name="check" class="size-3" /> Submitted
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-10 text-center text-zinc-500 dark:text-zinc-400">
                                        <flux:icon icon="clipboard-document-check" class="size-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                                        <p class="font-semibold text-sm">No evaluation submissions found matching the criteria.</p>
                                        <p class="text-xs mt-1">Select a different semester or reset active filters.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($submissions->hasPages())
                    <div class="pt-2">
                        {{ $submissions->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
