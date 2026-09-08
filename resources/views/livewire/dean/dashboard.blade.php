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
        if (!in_array($this->tab, ['self', 'faculty', 'program-heads'], true)) {
            $this->tab = 'self';
        }
    }

    public function updatedTab($value): void
    {
        if (!in_array($value, ['self', 'faculty', 'program-heads'], true)) {
            $this->tab = 'self';
        }
    }

    public ?int $selectedEvaluateeUserId = null;
    public string $selectedEvaluationType = 'dean'; // 'self', 'dean'
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

    // All Faculty Members in Academic Departments
    public function getFacultyMembersProperty()
    {
        $emp = $this->employee;
        $query = Employee::where('role', 'faculty')
            ->where('status', 'active')
            ->with(['user', 'department']);

        if ($emp && $emp->department_id) {
            $query->where(function ($q) use ($emp) {
                $q->where('department_id', $emp->department_id)
                  ->orWhereHas('department', fn($d) => $d->where('type', 'academic'));
            });
        }

        return $query->orderBy('first_name')->get();
    }

    public function getFilteredFacultyMembersProperty()
    {
        return $this->facultyMembers->filter(function ($fac) {
            if (!$fac->user) {
                return false;
            }
            if ($this->statusFilter === 'all') {
                return true;
            }
            $status = $this->getEvaluationStatus($fac->user->id, 'dean');
            $isDone = in_array($status, ['completed', 'processing']);

            return $this->statusFilter === 'completed' ? $isDone : !$isDone;
        });
    }

    // All Program Heads across all departments
    public function getProgramHeadsProperty()
    {
        return Employee::where('role', 'program head')
            ->where('status', 'active')
            ->with(['user', 'department'])
            ->orderBy('first_name')
            ->get();
    }

    public function getFilteredProgramHeadsProperty()
    {
        return $this->programHeads->filter(function ($head) {
            if (!$head->user) {
                return false;
            }
            if ($this->statusFilter === 'all') {
                return true;
            }
            $status = $this->getEvaluationStatus($head->user->id, 'dean');
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

    public function getEvaluatedFacultyCountProperty(): int
    {
        return $this->facultyMembers->filter(function ($fac) {
            if (!$fac->user) return false;
            $status = $this->getEvaluationStatus($fac->user->id, 'dean');
            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function getEvaluatedProgramHeadsCountProperty(): int
    {
        return $this->programHeads->filter(function ($head) {
            if (!$head->user) return false;
            $status = $this->getEvaluationStatus($head->user->id, 'dean');
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
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">College Dean Dashboard</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                    Role: <span class="font-semibold text-zinc-700 dark:text-zinc-300">Dean / Academic Head</span>
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
                :key="'eval-dean-'.$selectedEvaluateeUserId.'-'.$selectedEvaluationType" />
        </div>
    @else
        <!-- In-Page Tab Navigation (Minimal Underline Style) -->
        <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto no-scrollbar">
            <button 
                type="button" 
                wire:key="dean-tab-btn-self"
                wire:click="$set('tab', 'self')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'self' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Self
            </button>

            <button 
                type="button" 
                wire:key="dean-tab-btn-faculty"
                wire:click="$set('tab', 'faculty')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'faculty' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Faculty
            </button>

            <button 
                type="button" 
                wire:key="dean-tab-btn-program-heads"
                wire:click="$set('tab', 'program-heads')"
                class="shrink-0 pb-3 px-1 text-sm font-semibold transition-all cursor-pointer border-b-[3px] {{ $tab === 'program-heads' ? 'border-[#9b0000] dark:border-[#a82e2e] text-[#9b0000] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-700' }}">
                Program Heads
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- 1. Self Evaluation -->
            @if($tab === 'self')
                <flux:card wire:key="dean-tab-content-self" class="p-4 sm:p-6">
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
                                    <div class="font-bold text-zinc-800 dark:text-zinc-200">College Dean Self Evaluation</div>
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

            <!-- 2. Faculty Evaluation -->
            @if($tab === 'faculty')
                <flux:card wire:key="dean-tab-content-faculty" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Faculty Member Evaluations</flux:heading>
                        </div>
                        @if($this->facultyMembers->isNotEmpty())
                            <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                    <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->evaluatedFacultyCount }}/{{ $this->facultyMembers->count() }}</span> evaluated
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

                    @if($this->facultyMembers->isNotEmpty())
                        @php
                            $facTotal = $this->facultyMembers->count();
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

                    @if($this->facultyMembers->isEmpty())
                        <div class="text-center py-10 text-zinc-500">
                            <flux:icon icon="users" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No active faculty members found in this academic unit.</p>
                        </div>
                    @elseif($this->filteredFacultyMembers->isEmpty())
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="funnel" class="size-10 mx-auto text-zinc-300 mb-2" />
                            <p class="font-medium text-sm">
                                {{ $statusFilter === 'pending' ? 'No pending faculty evaluations remaining.' : 'No completed faculty evaluations found.' }}
                            </p>
                        </div>
                    @else
                        <!-- Mobile Responsive Cards (visible < 640px) -->
                        <div class="grid grid-cols-1 gap-3 sm:hidden max-h-[500px] overflow-y-auto pr-1">
                            @foreach($this->filteredFacultyMembers as $faculty)
                                @if($faculty->user)
                                    @php $status = $this->getEvaluationStatus($faculty->user->id, 'dean'); @endphp
                                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-3 shadow-2xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <div class="size-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-xs text-zinc-700 dark:text-zinc-300 shrink-0">
                                                    {{ substr($faculty->first_name ?? 'F', 0, 1) }}{{ substr($faculty->last_name ?? '', 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm text-zinc-800 dark:text-zinc-200 leading-tight">
                                                        {{ $faculty->full_name }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                        {{ $faculty->department?->code ?: 'N/A' }} • {{ $faculty->employee_number }}
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
                                                <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="selectTarget({{ $faculty->user->id }}, 'dean')">
                                                    Evaluate Faculty
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
                                        <th class="px-6 py-3.5">Faculty Member</th>
                                        <th class="px-6 py-3.5">Department</th>
                                        <th class="px-6 py-3.5">Status</th>
                                        <th class="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                    @foreach($this->filteredFacultyMembers as $faculty)
                                        @if($faculty->user)
                                            @php $status = $this->getEvaluationStatus($faculty->user->id, 'dean'); @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $faculty->full_name }}</div>
                                                    <div class="text-xs text-zinc-500 font-mono">{{ $faculty->employee_number }}</div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                                        {{ $faculty->department?->code ?: 'N/A' }}
                                                    </span>
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
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $faculty->user->id }}, 'dean')">
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

            <!-- 3. Program Heads Evaluation -->
            @if($tab === 'program-heads')
                <flux:card wire:key="dean-tab-content-program-heads" class="p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <div class="text-center sm:text-left">
                            <flux:heading size="lg">Program Head Evaluations</flux:heading>
                        </div>
                        @if($this->programHeads->isNotEmpty())
                            <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs shrink-0">
                                    <span class="text-[#9b0000] dark:text-[#e07a7a] font-extrabold mr-1">{{ $this->evaluatedProgramHeadsCount }}/{{ $this->programHeads->count() }}</span> evaluated
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

                    @if($this->programHeads->isNotEmpty())
                        @php
                            $headTotal = $this->programHeads->count();
                            $headDone = $this->evaluatedProgramHeadsCount;
                            $headPercent = $headTotal > 0 ? round(($headDone / $headTotal) * 100) : 0;
                        @endphp
                        <div class="mb-5 bg-zinc-50 dark:bg-zinc-800/40 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="flex justify-between items-center text-xs mb-1.5 font-medium">
                                <span class="text-zinc-600 dark:text-zinc-400">Completion Progress</span>
                                <span class="font-bold {{ $headPercent === 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-800 dark:text-zinc-200' }}">{{ $headPercent }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                <div class="h-2 rounded-full transition-all duration-300 {{ $headPercent === 100 ? 'bg-emerald-500' : 'bg-[#9b0000]' }}" style="width: {{ $headPercent }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($this->programHeads->isEmpty())
                        <div class="text-center py-10 text-zinc-500">
                            <flux:icon icon="academic-cap" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                            <p class="font-medium text-sm">No program heads registered in the system.</p>
                        </div>
                    @elseif($this->filteredProgramHeads->isEmpty())
                        <div class="text-center py-10 text-zinc-500 dark:text-zinc-400">
                            <flux:icon icon="funnel" class="size-10 mx-auto text-zinc-300 mb-2" />
                            <p class="font-medium text-sm">
                                {{ $statusFilter === 'pending' ? 'No pending program head evaluations remaining.' : 'No completed program head evaluations found.' }}
                            </p>
                        </div>
                    @else
                        <!-- Mobile Responsive Cards (visible < 640px) -->
                        <div class="grid grid-cols-1 gap-3 sm:hidden max-h-[500px] overflow-y-auto pr-1">
                            @foreach($this->filteredProgramHeads as $head)
                                @if($head->user)
                                    @php $status = $this->getEvaluationStatus($head->user->id, 'dean'); @endphp
                                    <div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-3 shadow-2xs">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex items-center gap-3">
                                                <div class="size-9 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center font-bold text-xs text-zinc-700 dark:text-zinc-300 shrink-0">
                                                    {{ substr($head->first_name ?? 'H', 0, 1) }}{{ substr($head->last_name ?? '', 0, 1) }}
                                                </div>
                                                <div>
                                                    <div class="font-bold text-sm text-zinc-800 dark:text-zinc-200 leading-tight">
                                                        {{ $head->full_name }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                                        {{ $head->department?->code ?: 'N/A' }} • {{ $head->employee_number }}
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
                                                <flux:button size="sm" variant="primary" class="w-full sm:w-auto" wire:click="selectTarget({{ $head->user->id }}, 'dean')">
                                                    Evaluate Program Head
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
                                        <th class="px-6 py-3.5">Program Head</th>
                                        <th class="px-6 py-3.5">Department</th>
                                        <th class="px-6 py-3.5">Status</th>
                                        <th class="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                    @foreach($this->filteredProgramHeads as $head)
                                        @if($head->user)
                                            @php $status = $this->getEvaluationStatus($head->user->id, 'dean'); @endphp
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                                <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                                    {{ $head->full_name }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                                        {{ $head->department?->code ?: 'N/A' }}
                                                    </span>
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
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $head->user->id }}, 'dean')">
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
