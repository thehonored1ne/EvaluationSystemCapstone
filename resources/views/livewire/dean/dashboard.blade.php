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

    public ?int $selectedEvaluateeUserId = null;
    public string $selectedEvaluationType = 'downward'; // 'self', 'downward', 'dean'
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

    // All Program Heads across all departments
    public function getProgramHeadsProperty()
    {
        return Employee::where('role', 'program head')
            ->where('status', 'active')
            ->with(['user', 'department'])
            ->orderBy('first_name')
            ->get();
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
            $status = $this->getEvaluationStatus($fac->user->id, 'downward');
            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function getEvaluatedProgramHeadsCountProperty(): int
    {
        return $this->programHeads->filter(function ($head) {
            if (!$head->user) return false;
            $status = $this->getEvaluationStatus($head->user->id, 'downward');
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
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">College Dean Dashboard</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                    Role: <span class="font-semibold text-zinc-700 dark:text-zinc-300">Dean / Academic Head</span>
                    @if($this->activeSemester)
                        | Semester: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->activeSemester->academicYear->name }} - {{ $this->activeSemester->name }}</span>
                    @endif
                </p>
            </div>

            <div>
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
        <div class="grid grid-cols-1 gap-8">
            <!-- 1. Self Evaluation -->
            @if($tab === 'self')
                <flux:card class="p-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <flux:heading size="lg">Self Evaluation</flux:heading>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                            <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->selfEvaluated ? '1/1' : '0/1' }}</span> evaluated
                        </span>
                    </div>
                    <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-150 dark:border-zinc-800">
                        <div>
                            <div class="font-bold text-zinc-800 dark:text-zinc-200">My Self Evaluation</div>
                            <p class="text-xs text-zinc-500 mt-0.5">Required once per semester (Max points: {{ (float)($this->activeSemester?->self_max_points ?? 10) }} pts)</p>
                        </div>
                        <div>
                            @php $status = $this->getEvaluationStatus(auth()->id(), 'self'); @endphp
                            @if($status === 'completed')
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                    <flux:icon icon="check-circle" class="size-4" />
                                    Completed
                                </span>
                            @elseif($status === 'processing')
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                    Your evaluation is being processed. Thank you!
                                </span>
                            @elseif(!$this->isEvaluationOpen)
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-500">
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
                </flux:card>
            @endif

            <!-- 2. Faculty Members Evaluations (Academic Professors) -->
            @if($tab === 'faculty')
                <flux:card class="p-6">
                    <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
                        <div>
                            <flux:heading size="lg">Academic Faculty Evaluations</flux:heading>
                            <p class="text-xs text-zinc-500 mt-0.5">Evaluate professors on teaching effectiveness and academic performance.</p>
                        </div>
                        @if($this->facultyMembers->isNotEmpty())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                                <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->evaluatedFacultyCount }}/{{ $this->facultyMembers->count() }}</span> evaluated
                            </span>
                        @endif
                    </div>

                    @if($this->facultyMembers->isEmpty())
                        <div class="text-center py-8 text-zinc-500">No active faculty members found in this academic unit.</div>
                    @else
                        <div class="overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
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
                                    @foreach($this->facultyMembers as $faculty)
                                        @if($faculty->user)
                                            @php $status = $this->getEvaluationStatus($faculty->user->id, 'downward'); @endphp
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
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $faculty->user->id }}, 'downward')">
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

            <!-- 3. Program Heads Subordinates Evaluations -->
            @if($tab === 'program-heads')
                <flux:card class="p-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <flux:heading size="lg">Program Head Evaluations</flux:heading>
                        @if($this->programHeads->isNotEmpty())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                                <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->evaluatedProgramHeadsCount }}/{{ $this->programHeads->count() }}</span> evaluated
                            </span>
                        @endif
                    </div>
                    @if($this->programHeads->isEmpty())
                        <div class="text-center py-6 text-zinc-500">No program heads registered in the system.</div>
                    @else
                        <div class="overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
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
                                    @foreach($this->programHeads as $head)
                                        @if($head->user)
                                            @php $status = $this->getEvaluationStatus($head->user->id, 'downward'); @endphp
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
                                                        <flux:button size="sm" variant="primary" wire:click="selectTarget({{ $head->user->id }}, 'downward')">
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
