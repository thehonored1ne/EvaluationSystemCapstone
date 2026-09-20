<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function placeholder()
    {
        return view('livewire.placeholders.evaluator-dashboard-skeleton');
    }

    #[Url]
    public string $tab = 'self';

    public string $statusFilter = 'all'; // 'all', 'pending', 'completed'

    public function mount(): void
    {
        if (! in_array($this->tab, ['self', 'faculty', 'supervisor'], true)) {
            $this->tab = 'self';
        }
    }

    public function updatedTab($value): void
    {
        if (! in_array($value, ['self', 'faculty', 'supervisor'], true)) {
            $this->tab = 'self';
        }
    }

    public ?int $selectedEvaluateeUserId = null;

    public string $selectedEvaluationType = 'program_head'; // 'self', 'upward_employee', 'program_head'

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

    // Subordinate Faculty in same department
    public function getFacultyProperty()
    {
        $emp = $this->employee;
        if (! $emp || ! $emp->department_id) {
            return collect();
        }

        return Employee::where('role', 'faculty')
            ->where('department_id', $emp->department_id)
            ->with('user')
            ->get();
    }

    public function getFilteredFacultyProperty()
    {
        return $this->faculty->filter(function ($member) {
            if (! $member->user) {
                return false;
            }
            if ($this->statusFilter === 'all') {
                return true;
            }
            $status = $this->getEvaluationStatus($member->user->id, 'program_head');
            $isDone = in_array($status, ['completed', 'processing']);

            return $this->statusFilter === 'completed' ? $isDone : ! $isDone;
        });
    }

    // Dean of their department (with fallback to active institutional dean)
    public function getDeanProperty()
    {
        $dept = $this->department;
        if ($dept && $dept->dean_id) {
            $dean = Employee::with('user')->find($dept->dean_id);
            if ($dean) {
                return $dean;
            }
        }

        return Employee::where('role', 'dean')->where('status', 'active')->with('user')->first();
    }

    public function getEvaluationStatus($evaluateeUserId, $type)
    {
        $sem = $this->activeSemester;
        if (! $sem) {
            return 'closed';
        }

        return Evaluation::getStatus(auth()->id(), $evaluateeUserId, $sem->id, null, $type);
    }

    public function getSelfEvaluatedProperty(): bool
    {
        $status = $this->getEvaluationStatus(auth()->id(), 'self');

        return in_array($status, ['completed', 'processing']);
    }

    public function getDeanEvaluatedProperty(): bool
    {
        if (! $this->dean || ! $this->dean->user) {
            return false;
        }

        $status = $this->getEvaluationStatus($this->dean->user->id, 'upward_employee');

        return in_array($status, ['completed', 'processing']);
    }

    public function getEvaluatedFacultyCountProperty(): int
    {
        return $this->faculty->filter(function ($fac) {
            if (! $fac->user) {
                return false;
            }

            $status = $this->getEvaluationStatus($fac->user->id, 'program_head');

            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function selectTarget($evaluateeUserId, $type)
    {
        if (! $this->isEvaluationOpen) {
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

    public function getHasProcessingProperty(): bool
    {
        if ($this->getEvaluationStatus(auth()->id(), 'self') === 'processing') {
            return true;
        }

        if ($this->dean && $this->dean->user && $this->getEvaluationStatus($this->dean->user->id, 'upward_employee') === 'processing') {
            return true;
        }

        return $this->faculty->contains(function ($fac) {
            return $fac->user && $this->getEvaluationStatus($fac->user->id, 'program_head') === 'processing';
        });
    }

    public function checkProcessingStatus(): void
    {
        Evaluation::flushStatusCache();
    }

    #[On('evaluation-submitted')]
    public function handleEvaluationSubmitted()
    {
        Evaluation::flushStatusCache();
        $this->selectedEvaluateeUserId = null;
        $this->showForm = false;
    }
}; ?>

<div class="flex flex-col gap-6 sm:gap-8 w-full max-w-6xl mx-auto px-0 sm:px-3 md:px-4 py-3 sm:py-6"
    @if($this->hasProcessing) wire:poll.2500ms="checkProcessingStatus" @endif>
    @if(!$showForm)
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-sm mb-4 sm:mb-6 text-center sm:text-left">
            <div class="flex flex-col items-center sm:items-start min-w-0 flex-1">
                <h1 class="text-xl sm:text-2xl font-bold font-sans text-zinc-900 dark:text-zinc-50 tracking-tight">
                    Program Head Dashboard
                </h1>
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-x-2 gap-y-1 mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    @if($this->department)
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">
                            {{ $this->department->name }}
                        </span>
                    @else
                        <span class="font-medium text-amber-600 dark:text-amber-400">
                            Department not assigned
                        </span>
                    @endif

                    @if($this->activeSemester)
                        <span class="text-zinc-300 dark:text-zinc-700">•</span>
                        <span class="font-medium text-zinc-600 dark:text-zinc-400">
                            <span class="font-mono">{{ $this->activeSemester->academicYear->name }}</span>
                            <span>–</span>
                            <span>{{ $this->activeSemester->name }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex justify-center sm:justify-end shrink-0">
                @if($this->isEvaluationOpen)
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/60 shadow-2xs whitespace-nowrap font-sans">
                        <span class="relative flex size-2 shrink-0">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full size-2 bg-emerald-500"></span>
                        </span>
                        <span>Evaluations Open</span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200/80 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800/60 shadow-2xs whitespace-nowrap font-sans">
                        <span class="size-2 rounded-full bg-rose-500 shrink-0"></span>
                        <span>Evaluations Closed</span>
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
            <p class="text-sm mt-1">Your employee profile is not assigned to a department. Please ask the administrator to assign your department in the user management page so you can evaluate faculty and supervisor dean.</p>
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
                :key="'eval-ph-'.$selectedEvaluateeUserId.'-'.$selectedEvaluationType" />
        </div>
    @elseif($this->employee?->department_id)
        <!-- In-Page Tab Navigation (Minimal Underline Style) -->
        <div class="flex items-center border-b border-zinc-200 dark:border-zinc-800 w-full overflow-x-auto">
            <button 
                type="button" 
                wire:key="program-head-tab-btn-self"
                wire:click="$set('tab', 'self')"
                class="flex-1 sm:flex-none pb-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold transition-all cursor-pointer text-center border-b-[3px] {{ $tab === 'self' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Self
            </button>

            <button 
                type="button" 
                wire:key="program-head-tab-btn-faculty"
                wire:click="$set('tab', 'faculty')"
                class="flex-1 sm:flex-none pb-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold transition-all cursor-pointer text-center border-b-[3px] {{ $tab === 'faculty' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Faculty
            </button>

            <button 
                type="button" 
                wire:key="program-head-tab-btn-supervisor"
                wire:click="$set('tab', 'supervisor')"
                class="flex-1 sm:flex-none pb-3 px-2 sm:px-4 text-xs sm:text-sm font-semibold transition-all cursor-pointer text-center border-b-[3px] {{ $tab === 'supervisor' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Dean
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- 1. Self Evaluation -->
            @if($tab === 'self')
                <flux:card wire:key="program-head-tab-content-self" class="p-3.5 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Self Evaluation</flux:heading>
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
                            <div class="flex items-center justify-between sm:justify-end">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                    <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->selfEvaluated ? '1/1' : '0/1' }}</span> evaluated
                                </span>
                            </div>
                            <div class="grid grid-cols-3 sm:inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium w-full sm:w-auto text-center shrink-0">
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'all')"
                                    class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                    All
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'pending')"
                                    class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                    Pending
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="$set('statusFilter', 'completed')"
                                    class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
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
                        @php $status = $this->getEvaluationStatus(auth()->id(), 'self'); @endphp
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-4 sm:p-5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div>
                                <div class="font-bold text-zinc-800 dark:text-zinc-200">Self Evaluation Form</div>
                                <p class="text-xs text-zinc-500 mt-0.5">Evaluate your own academic performance, teaching load, and accomplishments.</p>
                            </div>
                            <div class="self-end sm:self-auto">
                                @if($status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                        <flux:icon icon="check-circle" class="size-4" />
                                        Completed
                                    </span>
                                @elseif($status === 'processing')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                        <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                        Processing...
                                    </span>
                                @elseif(!$this->isEvaluationOpen)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                        <flux:icon icon="clock" class="size-4" />
                                        Closed
                                    </span>
                                @else
                                    <flux:button size="sm" variant="primary" wire:click="selectTarget({{ auth()->id() }}, 'self')">
                                        Evaluate Yourself
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif

            <!-- 2. Dean (Supervisor) Evaluation -->
            @if($tab === 'supervisor')
                <flux:card wire:key="program-head-tab-content-supervisor" class="p-3.5 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Dean Evaluation</flux:heading>
                        </div>
                        @if($this->dean && $this->dean->user)
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
                                <div class="flex items-center justify-between sm:justify-end">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                        <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->deanEvaluated ? '1/1' : '0/1' }}</span> evaluated
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 sm:inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium w-full sm:w-auto text-center shrink-0">
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'all')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        All
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'pending')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Pending
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'completed')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Completed
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if(!$this->dean)
                        <div class="text-zinc-500 py-10 text-center">
                            <flux:icon icon="building-library" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No Dean registered or assigned to your department.</p>
                        </div>
                    @elseif(!$this->dean->user)
                        <div class="text-zinc-500 py-10 text-center">
                            <flux:icon icon="exclamation-circle" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No user account found for the Dean.</p>
                        </div>
                    @elseif($statusFilter === 'pending' && $this->deanEvaluated)
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="check-circle" class="size-10 mx-auto text-emerald-500 mb-2" />
                            <p class="font-medium text-sm">You have completed your evaluation of the Dean for this semester.</p>
                        </div>
                    @elseif($statusFilter === 'completed' && !$this->deanEvaluated)
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="clock" class="size-10 mx-auto text-amber-500 mb-2" />
                            <p class="font-medium text-sm">You have not evaluated your Dean yet.</p>
                        </div>
                    @else
                        @php $status = $this->getEvaluationStatus($this->dean->user->id, 'upward_employee'); @endphp
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-4 sm:p-5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="min-w-0 flex-1">
                                <div class="font-bold text-zinc-800 dark:text-zinc-200 truncate">{{ $this->dean->full_name }}</div>
                                <p class="text-xs text-zinc-500 mt-0.5">Dean • {{ $this->department?->name ?? 'College' }}</p>
                            </div>
                            <div class="self-end sm:self-auto">
                                @if($status === 'completed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                        <flux:icon icon="check-circle" class="size-4" />
                                        Completed
                                    </span>
                                @elseif($status === 'processing')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                        <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                        Processing...
                                    </span>
                                @elseif(!$this->isEvaluationOpen)
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
                                        <flux:icon icon="clock" class="size-4" />
                                        Closed
                                    </span>
                                @else
                                    <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $this->dean->user->id }}, 'upward_employee')">
                                        Evaluate Dean
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif

            <!-- 3. Faculty Evaluations -->
            @if($tab === 'faculty')
                <flux:card wire:key="program-head-tab-content-faculty" class="p-3.5 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Faculty Evaluations</flux:heading>
                        </div>
                        @if($this->faculty->isNotEmpty())
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full sm:w-auto">
                                <div class="flex items-center justify-between sm:justify-end">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                        <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->evaluatedFacultyCount }}/{{ $this->faculty->count() }}</span> evaluated
                                    </span>
                                </div>
                                <div class="grid grid-cols-3 sm:inline-flex items-center p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs font-medium w-full sm:w-auto text-center shrink-0">
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'all')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        All
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'pending')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Pending
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('statusFilter', 'completed')"
                                        class="px-2.5 py-1.5 sm:py-1 rounded-md transition-all cursor-pointer {{ $statusFilter === 'completed' ? 'bg-[#9b0000] dark:bg-[#a82e2e] text-white font-semibold shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-200' }}">
                                        Completed
                                    </button>
                                </div>
                            </div>
                        @endif
                </div>

                @if($this->faculty->isNotEmpty())
                    @php
                        $facTotal = $this->faculty->count();
                        $facDone = $this->evaluatedFacultyCount;
                        $facPercent = $facTotal > 0 ? round(($facDone / $facTotal) * 100) : 0;
                    @endphp
                    <div class="mb-5 bg-zinc-50 dark:bg-zinc-800/40 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <div class="flex justify-between items-center text-xs mb-1.5 font-medium">
                            <span class="text-zinc-600 dark:text-zinc-400">Completion Progress</span>
                            <span class="font-bold {{ $facPercent === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ $facPercent }}%</span>
                        </div>
                        <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                            <div class="h-2 rounded-full transition-all duration-300 {{ $facPercent === 100 ? 'bg-emerald-500' : 'bg-[#9b0000]' }}" style="width: {{ $facPercent }}%"></div>
                        </div>
                    </div>
                @endif

                @if($this->faculty->isEmpty())
                    <div class="text-center py-10 text-zinc-500">
                        <flux:icon icon="users" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                        <p class="font-medium text-sm">No faculty professors registered in your department.</p>
                    </div>
                @elseif($this->filteredFaculty->isEmpty())
                    <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                        <flux:icon icon="funnel" class="size-10 mx-auto text-zinc-300 mb-2" />
                        <p class="font-medium text-sm">
                            {{ $statusFilter === 'pending' ? 'No pending faculty evaluations remaining.' : 'No completed faculty evaluations found.' }}
                        </p>
                    </div>
                @else
                    <!-- Mobile Responsive Cards (visible < 640px) -->
                    <div class="grid grid-cols-1 gap-3 sm:hidden max-h-[500px] overflow-y-auto pr-1">
                        @foreach($this->filteredFaculty as $member)
                            @if($member->user)
                                @php $status = $this->getEvaluationStatus($member->user->id, 'program_head'); @endphp
                                <div class="bg-white dark:bg-zinc-900 p-3 sm:p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-3 shadow-2xs">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="font-bold text-sm text-zinc-800 dark:text-zinc-200 leading-tight truncate">
                                                {{ $member->full_name }}
                                            </div>
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                Faculty • {{ $member->employee_number ?? 'N/A' }}
                                            </div>
                                        </div>
                                        <div class="shrink-0">
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
                                                <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="selectTarget({{ $member->user->id }}, 'program_head')">
                                                    Evaluate Professor
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
                                    @foreach($this->filteredFaculty as $member)
                                        @if($member->user)
                                            @php $status = $this->getEvaluationStatus($member->user->id, 'program_head'); @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                                <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                                    {{ $member->full_name }}
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
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $member->user->id }}, 'program_head')">
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
        </div>
    @endif
</div>
