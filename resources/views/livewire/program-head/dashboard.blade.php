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
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Program Head Dashboard</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                    Department: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->department?->name ?? 'Not assigned' }} ({{ $this->department?->code ?? 'N/A' }})</span>
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

            <!-- 2. Dean (Supervisor) Evaluation -->
            @if($tab === 'supervisor')
                <flux:card class="p-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <flux:heading size="lg">Supervisor Evaluation (College Dean)</flux:heading>
                        @if($this->dean && $this->dean->user)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                                <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->deanEvaluated ? '1/1' : '0/1' }}</span> evaluated
                            </span>
                        @endif
                    </div>
                    @if(!$this->dean)
                        <div class="text-zinc-500 py-4 text-center">No Dean registered or assigned to your department.</div>
                    @elseif(!$this->dean->user)
                        <div class="text-zinc-500 py-4 text-center">No user account found for the Dean.</div>
                    @else
                        @php $status = $this->getEvaluationStatus($this->dean->user->id, 'upward_employee'); @endphp
                        <div class="flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-150 dark:border-zinc-800">
                            <div>
                                <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $this->dean->full_name }}</div>
                                <p class="text-xs text-zinc-500 mt-0.5">Dean of {{ $this->department->name }}</p>
                            </div>
                            <div>
                                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                    <flux:icon icon="check-circle" class="size-4" />
                                    Completed
                                </span>
                            </div>
                        </div>
                    @endif
                </flux:card>
            @endif

            <!-- 3. Faculty (Subordinate) Evaluations -->
            @if($tab === 'faculty')
                <flux:card class="p-6">
                    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                        <flux:heading size="lg">Faculty Evaluations (Subordinate Professors)</flux:heading>
                        @if($this->faculty->isNotEmpty())
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                                <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->evaluatedFacultyCount }}/{{ $this->faculty->count() }}</span> evaluated
                            </span>
                        @endif
                    </div>
                    @if($this->faculty->isEmpty())
                        <div class="text-center py-6 text-zinc-500">No faculty professors registered in your department.</div>
                    @else
                        <div class="overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
                            <table class="w-full text-left text-sm min-w-[480px]">
                                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-10 shadow-2xs">
                                    <tr>
                                        <th class="px-6 py-3.5">Name</th>
                                        <th class="px-6 py-3.5">Status</th>
                                        <th class="px-6 py-3.5 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-250 dark:divide-zinc-850 bg-white dark:bg-zinc-900">
                                    @foreach($this->faculty as $member)
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
