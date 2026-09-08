<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\Semester;
use App\Models\Employee;
use App\Models\User;
use App\Models\Evaluation;

new #[Layout('components.layouts.app')] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.evaluator-dashboard-skeleton');
    }
    #[Url]
    public string $tab = 'self';

    public string $statusFilter = 'all'; // 'all', 'pending', 'completed'

    public function mount(): void
    {
        if (!in_array($this->tab, ['self', 'staff', 'dean'], true)) {
            $this->tab = 'self';
        }
    }

    public function updatedTab($value): void
    {
        if (!in_array($value, ['self', 'staff', 'dean'], true)) {
            $this->tab = 'self';
        }
    }

    public ?int $selectedEvaluateeUserId = null;
    public string $selectedEvaluationType = 'department_head'; // 'self', 'department_head' (for staff), 'upward_employee' (for deans)
    public bool $showForm = false;

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getIsEvaluationOpenProperty()
    {
        $sem = $this->activeSemester;
        return $sem ? $sem->isEvaluationWindowActive() : false;
    }

    public function getEmployeeProperty()
    {
        return auth()->user()?->employee;
    }

    public function getDepartmentProperty()
    {
        return $this->employee?->department;
    }

    // Staff members belonging to the same administrative department
    public function getDepartmentStaffProperty()
    {
        $emp = $this->employee;
        if (!$emp || !$emp->department_id) return collect();

        return Employee::where('role', 'staff')
            ->where('department_id', $emp->department_id)
            ->with('user')
            ->get();
    }

    public function getFilteredStaffProperty()
    {
        return $this->departmentStaff->filter(function ($staff) {
            if (!$staff->user) {
                return false;
            }
            if ($this->statusFilter === 'all') {
                return true;
            }
            $status = $this->getEvaluationStatus($staff->user->id, 'department_head');
            $isDone = in_array($status, ['completed', 'processing']);

            return $this->statusFilter === 'completed' ? $isDone : !$isDone;
        });
    }

    // Deans available for upward evaluation
    public function getDeansProperty()
    {
        return Employee::where('role', 'dean')
            ->with('user')
            ->get();
    }

    public function getFilteredDeansProperty()
    {
        return $this->deans->filter(function ($dean) {
            if (!$dean->user) {
                return false;
            }
            if ($this->statusFilter === 'all') {
                return true;
            }
            $status = $this->getEvaluationStatus($dean->user->id, 'upward_employee');
            $isDone = in_array($status, ['completed', 'processing']);

            return $this->statusFilter === 'completed' ? $isDone : !$isDone;
        });
    }

    public function getEvaluationStatus($evaluateeUserId, $type)
    {
        $sem = $this->activeSemester;
        if (!$sem) return 'closed';

        return Evaluation::getStatus(auth()->id(), $evaluateeUserId, $sem->id, null, $type);
    }

    public function getSelfEvaluatedProperty(): bool
    {
        $status = $this->getEvaluationStatus(auth()->id(), 'self');
        return in_array($status, ['completed', 'processing']);
    }

    public function getEvaluatedStaffCountProperty(): int
    {
        return $this->departmentStaff->filter(function ($staff) {
            if (!$staff->user) {
                return false;
            }

            $status = $this->getEvaluationStatus($staff->user->id, 'department_head');
            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function getEvaluatedDeansCountProperty(): int
    {
        return $this->deans->filter(function ($dean) {
            if (!$dean->user) {
                return false;
            }

            $status = $this->getEvaluationStatus($dean->user->id, 'upward_employee');
            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function selectTarget($evaluateeUserId, $type)
    {
        if (!$this->isEvaluationOpen) {
            session()->flash('error', 'Evaluations are currently closed.');
            return;
        }

        $status = $this->getEvaluationStatus($evaluateeUserId, $type);
        if ($status !== 'pending') {
            session()->flash('error', 'This evaluation is already processing or completed.');
            return;
        }

        $this->selectedEvaluateeUserId = $evaluateeUserId;
        $this->selectedEvaluationType = $type;
        $this->showForm = true;
    }

    #[On('evaluation-submitted')]
    public function handleEvaluationSubmitted()
    {
        $this->selectedEvaluateeUserId = null;
        $this->showForm = false;
    }
}; ?>

<div class="flex flex-col gap-6 sm:gap-8 w-full max-w-6xl mx-auto px-2 sm:px-4 md:px-6 py-3 sm:py-6">
    @if(!$showForm)
        <!-- Header -->
        <div class="flex flex-col items-center text-center md:flex-row md:justify-between md:items-center md:text-left gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Department Head Dashboard</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                    Administrative Department: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->department?->name ?? 'Not assigned' }} ({{ $this->department?->code ?? 'N/A' }})</span>
                    @if($this->activeSemester)
                        | Semester: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->activeSemester->academicYear->name }} - {{ $this->activeSemester->name }}</span>
                    @endif
                </p>
            </div>

            <div class="flex justify-center md:justify-end">
                @if($this->isEvaluationOpen)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Evaluations Open
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                        <span class="size-2 rounded-full bg-rose-500"></span>
                        Evaluations Closed
                    </span>
                @endif
            </div>
        </div>
    @endif

    @if(session()->has('error') && !$showForm)
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-3">
            <flux:icon icon="exclamation-circle" class="size-6 text-rose-600" />
            <div class="text-sm font-semibold">{{ session('error') }}</div>
        </div>
    @endif

    @if(!$this->employee?->department_id)
        <div class="p-6 bg-amber-50 border border-amber-200 text-amber-900 rounded-2xl">
            <h3 class="font-bold text-lg">Department Assignment Required</h3>
            <p class="text-sm mt-1">Your employee profile is not assigned to an administrative department. Please ask the administrator to assign your department in the user management page so you can evaluate staff members.</p>
        </div>
    @endif

    <!-- Content Area -->
    @if($showForm && $selectedEvaluateeUserId)
        <div>
            <div class="mb-4">
                <flux:button variant="ghost" icon="arrow-left" wire:click="$set('showForm', false)">
                    Back to Dashboard
                </flux:button>
            </div>
            
            <livewire:evaluation-form 
                :evaluatee="App\Models\User::find($selectedEvaluateeUserId)" 
                :evaluationType="$selectedEvaluationType" 
                :key="'eval-dept-head-'.$selectedEvaluateeUserId.'-'.$selectedEvaluationType" />
        </div>
    @elseif($this->employee?->department_id)
        <!-- In-Page Tab Navigation (Minimal Underline Style) -->
        <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto no-scrollbar">
            <button 
                type="button" 
                wire:key="department-head-tab-btn-self"
                wire:click="$set('tab', 'self')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'self' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Self
            </button>

            <button 
                type="button" 
                wire:key="department-head-tab-btn-staff"
                wire:click="$set('tab', 'staff')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'staff' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Staff
            </button>

            <button 
                type="button" 
                wire:key="department-head-tab-btn-dean"
                wire:click="$set('tab', 'dean')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'dean' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Dean
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- 1. Self Evaluation -->
            @if($tab === 'self')
                <flux:card wire:key="department-head-tab-content-self" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Self Evaluation</flux:heading>
                        </div>
                        <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->selfEvaluated ? '1/1' : '0/1' }}</span> evaluated
                            </span>
                            <div class="inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium shrink-0">
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'all')"
                                    class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                    All
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'pending')"
                                    class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                    Pending
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'completed')"
                                    class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                    Completed
                                </button>
                            </div>
                        </div>
                    </div>

                    @if($statusFilter === 'pending' && $this->selfEvaluated)
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="check-circle" class="size-10 mx-auto text-emerald-500 mb-2" />
                            <p class="font-medium text-sm">You have completed your self-evaluation for this semester.</p>
                        </div>
                    @elseif($statusFilter === 'completed' && !$this->selfEvaluated)
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="clock" class="size-10 mx-auto text-amber-500 mb-2" />
                            <p class="font-medium text-sm">You have not completed your self-evaluation yet.</p>
                        </div>
                    @else
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-4 sm:p-5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-start sm:items-center gap-3">
                                <div class="size-10 rounded-full bg-red-50 dark:bg-red-950/40 text-[#9b0000] dark:text-[#e07a7a] flex items-center justify-center shrink-0">
                                    <flux:icon icon="user" class="size-5" />
                                </div>
                                <div>
                                    <div class="font-bold text-zinc-800 dark:text-zinc-200">Department Head Self Evaluation</div>
                                    <p class="text-xs text-zinc-500 mt-0.5">Required once per semester (Max points: {{ (float)($this->activeSemester?->self_max_points ?? 10) }} pts)</p>
                                </div>
                            </div>
                            <div class="self-end sm:self-auto">
                                @php $status = $this->getEvaluationStatus(auth()->id(), 'self'); @endphp
                                @if($status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                        <flux:icon icon="check-circle" class="size-4" />
                                        Completed
                                    </span>
                                @elseif($status === 'processing')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                        <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                        Your evaluation is being processed. Thank you!
                                    </span>
                                @elseif(!$this->isEvaluationOpen)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                        <flux:icon icon="clock" class="size-4" />
                                        Closed
                                    </span>
                                @else
                                    <flux:button size="sm" variant="primary" wire:click="selectTarget({{ auth()->id() }}, 'self')">
                                        Begin Self Eval
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif

            <!-- 2. Staff Evaluation -->
            @if($tab === 'staff')
                <flux:card wire:key="department-head-tab-content-staff" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Staff Evaluations</flux:heading>
                        </div>
                        @if($this->departmentStaff->isNotEmpty())
                            <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                    <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->evaluatedStaffCount }}/{{ $this->departmentStaff->count() }}</span> evaluated
                                </span>
                                <div class="inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium shrink-0">
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'all')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        All
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'pending')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Pending
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'completed')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Completed
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($this->departmentStaff->isNotEmpty())
                        @php
                            $staffTotal = $this->departmentStaff->count();
                            $staffDone = $this->evaluatedStaffCount;
                            $staffPercent = $staffTotal > 0 ? round(($staffDone / $staffTotal) * 100) : 0;
                        @endphp
                        <div class="mb-5 bg-zinc-50 dark:bg-zinc-800/40 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="flex justify-between items-center text-xs mb-1.5 font-medium">
                                <span class="text-zinc-600 dark:text-zinc-400">Completion Progress</span>
                                <span class="font-bold {{ $staffPercent === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ $staffPercent }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-300 {{ $staffPercent === 100 ? 'bg-emerald-500' : 'bg-[#9b0000]' }}" style="width: {{ $staffPercent }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($this->departmentStaff->isEmpty())
                        <div class="text-center py-10 text-zinc-500">
                            <flux:icon icon="user-group" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No staff members assigned to your administrative department yet.</p>
                        </div>
                    @elseif($this->filteredStaff->isEmpty())
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="funnel" class="size-10 mx-auto text-zinc-300 mb-2" />
                            <p class="font-medium text-sm">
                                {{ $statusFilter === 'pending' ? 'No pending staff evaluations remaining.' : 'No completed staff evaluations found.' }}
                            </p>
                        </div>
                    @else
                        <!-- Mobile Responsive Cards (visible < 640px) -->
                        <div class="grid grid-cols-1 gap-3 sm:hidden max-h-[500px] overflow-y-auto pr-1">
                            @foreach($this->filteredStaff as $staff)
                                @if($staff->user)
                                    @php $status = $this->getEvaluationStatus($staff->user->id, 'department_head'); @endphp
                                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-3 shadow-2xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <div class="size-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-xs text-zinc-700 dark:text-zinc-300 shrink-0">
                                                    {{ substr($staff->first_name ?? 'S', 0, 1) }}{{ substr($staff->last_name ?? '', 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm text-zinc-800 dark:text-zinc-200 leading-tight">
                                                        {{ $staff->full_name }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                        Staff • ID: {{ $staff->employee_number ?? 'N/A' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                @if($status === 'completed')
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                        <flux:icon icon="check-circle" class="size-3.5" />
                                                        Completed
                                                    </span>
                                                @elseif($status === 'processing')
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                                        <flux:icon icon="arrow-path" class="size-3.5 animate-spin" />
                                                        Processing
                                                    </span>
                                                @elseif(!$this->isEvaluationOpen)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                                        Closed
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                                        Pending
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                                            @if($status === 'completed')
                                                <span class="text-xs text-zinc-400 font-semibold py-1">Done</span>
                                            @elseif($status === 'processing')
                                                <span class="text-xs text-zinc-400 font-semibold py-1">Processing...</span>
                                            @elseif(!$this->isEvaluationOpen)
                                                <span class="text-xs text-zinc-400 py-1">Unavailable</span>
                                            @else
                                                <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="selectTarget({{ $staff->user->id }}, 'department_head')">
                                                    Evaluate Staff
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <!-- Desktop Table (visible >= 640px) -->
                        <div class="hidden sm:block overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <table class="w-full text-left text-sm min-w-[500px]">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-10 shadow-2xs">
                                    <tr>
                                        <th class="px-6 py-3.5">Name</th>
                                        <th class="px-6 py-3.5">Employee ID</th>
                                        <th class="px-6 py-3.5">Status</th>
                                        <th class="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                    @foreach($this->filteredStaff as $staff)
                                        @if($staff->user)
                                            @php $status = $this->getEvaluationStatus($staff->user->id, 'department_head'); @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                                <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                                    {{ $staff->full_name }}
                                                </td>
                                                <td class="px-6 py-4 text-zinc-500 font-mono text-xs">
                                                    {{ $staff->employee_number }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    @if($status === 'completed')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                            <flux:icon icon="check-circle" class="size-4" />
                                                            Completed
                                                        </span>
                                                    @elseif($status === 'processing')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                                            Processing...
                                                        </span>
                                                    @elseif(!$this->isEvaluationOpen)
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                                            Closed
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                                            Pending
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    @if($status === 'completed')
                                                        <span class="text-xs text-zinc-400 font-semibold">Done</span>
                                                    @elseif($status === 'processing')
                                                        <span class="text-xs text-zinc-400 font-semibold">Processing</span>
                                                    @elseif(!$this->isEvaluationOpen)
                                                        <span class="text-xs text-zinc-400">Unavailable</span>
                                                    @else
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $staff->user->id }}, 'department_head')">
                                                            Evaluate
                                                        </flux:button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            @endif

            <!-- 3. Dean Evaluation -->
            @if($tab === 'dean')
                <flux:card wire:key="department-head-tab-content-dean" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Dean Evaluations</flux:heading>
                        </div>
                        @if($this->deans->isNotEmpty())
                            <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                    <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->evaluatedDeansCount }}/{{ $this->deans->count() }}</span> evaluated
                                </span>
                                <div class="inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium shrink-0">
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'all')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        All
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'pending')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Pending
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'completed')"
                                        class="px-2.5 py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Completed
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($this->deans->isNotEmpty())
                        @php
                            $deanTotal = $this->deans->count();
                            $deanDone = $this->evaluatedDeansCount;
                            $deanPercent = $deanTotal > 0 ? round(($deanDone / $deanTotal) * 100) : 0;
                        @endphp
                        <div class="mb-5 bg-zinc-50 dark:bg-zinc-800/40 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="flex justify-between items-center text-xs mb-1.5 font-medium">
                                <span class="text-zinc-600 dark:text-zinc-400">Completion Progress</span>
                                <span class="font-bold {{ $deanPercent === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ $deanPercent }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-300 {{ $deanPercent === 100 ? 'bg-emerald-500' : 'bg-[#9b0000]' }}" style="width: {{ $deanPercent }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($this->deans->isEmpty())
                        <div class="text-center py-10 text-zinc-500">
                            <flux:icon icon="building-library" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No Deans registered in the system.</p>
                        </div>
                    @elseif($this->filteredDeans->isEmpty())
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="funnel" class="size-10 mx-auto text-zinc-300 mb-2" />
                            <p class="font-medium text-sm">
                                {{ $statusFilter === 'pending' ? 'No pending Dean evaluations remaining.' : 'No completed Dean evaluations found.' }}
                            </p>
                        </div>
                    @else
                        <!-- Mobile Responsive Cards (visible < 640px) -->
                        <div class="grid grid-cols-1 gap-3 sm:hidden max-h-[500px] overflow-y-auto pr-1">
                            @foreach($this->filteredDeans as $dean)
                                @if($dean->user)
                                    @php $status = $this->getEvaluationStatus($dean->user->id, 'upward_employee'); @endphp
                                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-3 shadow-2xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <div class="size-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-xs text-zinc-700 dark:text-zinc-300 shrink-0">
                                                    {{ substr($dean->first_name ?? 'D', 0, 1) }}{{ substr($dean->last_name ?? '', 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm text-zinc-800 dark:text-zinc-200 leading-tight">
                                                        {{ $dean->full_name }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                        Dean • {{ $dean->department?->name ?? 'Academic' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                @if($status === 'completed')
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                        <flux:icon icon="check-circle" class="size-3.5" />
                                                        Completed
                                                    </span>
                                                @elseif($status === 'processing')
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                                        <flux:icon icon="arrow-path" class="size-3.5 animate-spin" />
                                                        Processing
                                                    </span>
                                                @elseif(!$this->isEvaluationOpen)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                                        Closed
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                                        Pending
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                                            @if($status === 'completed')
                                                <span class="text-xs text-zinc-400 font-semibold py-1">Done</span>
                                            @elseif($status === 'processing')
                                                <span class="text-xs text-zinc-400 font-semibold py-1">Processing...</span>
                                            @elseif(!$this->isEvaluationOpen)
                                                <span class="text-xs text-zinc-400 py-1">Unavailable</span>
                                            @else
                                                <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="selectTarget({{ $dean->user->id }}, 'upward_employee')">
                                                    Evaluate Dean
                                                </flux:button>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <!-- Desktop Table (visible >= 640px) -->
                        <div class="hidden sm:block overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <table class="w-full text-left text-sm min-w-[480px]">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-10 shadow-2xs">
                                    <tr>
                                        <th class="px-6 py-3.5">Name</th>
                                        <th class="px-6 py-3.5">Status</th>
                                        <th class="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                    @foreach($this->filteredDeans as $dean)
                                        @if($dean->user)
                                            @php $status = $this->getEvaluationStatus($dean->user->id, 'upward_employee'); @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                                <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                                    {{ $dean->full_name }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    @if($status === 'completed')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                            <flux:icon icon="check-circle" class="size-4" />
                                                            Completed
                                                        </span>
                                                    @elseif($status === 'processing')
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                                            Processing...
                                                        </span>
                                                    @elseif(!$this->isEvaluationOpen)
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                                            Closed
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                                            Pending
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    @if($status === 'completed')
                                                        <span class="text-xs text-zinc-400 font-semibold">Done</span>
                                                    @elseif($status === 'processing')
                                                        <span class="text-xs text-zinc-400 font-semibold">Processing</span>
                                                    @elseif(!$this->isEvaluationOpen)
                                                        <span class="text-xs text-zinc-400">Unavailable</span>
                                                    @else
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $dean->user->id }}, 'upward_employee')">
                                                            Evaluate Dean
                                                        </flux:button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </flux:card>
            @endif
        </div>
    @endif
</div>
