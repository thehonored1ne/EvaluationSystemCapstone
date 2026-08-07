<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Lazy;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\EvaluationCriterion;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.evaluation-settings-skeleton');
    }

    // Criterion Delete
    public bool $showDeleteCriterionModal = false;
    public ?EvaluationCriterion $deletingCriterion = null;

    // Academic Year creation
    public string $newYearName = '';
    public bool $showYearModal = false;

    // Semester creation
    public string $newSemesterName = '';
    public string $selectedYearId = '';
    public bool $showSemModal = false;

    // Criteria creation
    public string $newCriterionName = '';
    public string $newCriterionMaxPoints = '0';
    public string $newCriterionType = 'upward_student';
    public bool $showCriterionModal = false;

    // Criteria points (array keyed by ID)
    public array $criteriaPoints = [];

    // Dynamic Max score targets config for all 7 evaluation terms
    public string $upwardStudentMaxTarget = '90';
    public string $selfMaxTarget = '10';
    public string $deanMaxTarget = '50';
    public string $programHeadMaxTarget = '50';
    public string $peerMaxTarget = '50';
    public string $downwardMaxTarget = '50';
    public string $staffMaxTarget = '10';
    public string $upwardEmployeeMaxTarget = '50';

    // Evaluation window schedule
    public string $startsAt = '';
    public string $endsAt = '';

    // Schedule modals
    public bool $showOverwriteModal = false;
    public bool $showRemoveScheduleModal = false;

    public function mount()
    {
        $this->loadPoints();
    }

    public function loadPoints()
    {
        $this->criteriaPoints = EvaluationCriterion::all()
            ->pluck('max_points', 'id')
            ->map(fn($p) => (float)$p)
            ->toArray();

        $activeSem = Semester::where('is_active', true)->first();
        if ($activeSem) {
            $this->upwardStudentMaxTarget = (string)(float)($activeSem->upward_student_max_points ?: 90);
            $this->upwardEmployeeMaxTarget = (string)(float)($activeSem->upward_employee_max_points ?: 50);
            $this->deanMaxTarget = (string)(float)($activeSem->dean_max_points ?: 50);
            $this->programHeadMaxTarget = (string)(float)($activeSem->program_head_max_points ?: 50);
            $this->downwardMaxTarget = (string)(float)($activeSem->downward_max_points ?: 50);
            $this->peerMaxTarget = (string)(float)($activeSem->peer_max_points ?: 50);
            $this->selfMaxTarget = (string)(float)($activeSem->self_max_points ?: 10);
            $this->staffMaxTarget = (string)(float)($activeSem->staff_max_points ?: 10);
            
            $this->startsAt = $activeSem->evaluation_starts_at ? $activeSem->evaluation_starts_at->format('Y-m-d\TH:i') : '';
            $this->endsAt = $activeSem->evaluation_ends_at ? $activeSem->evaluation_ends_at->format('Y-m-d\TH:i') : '';
        }
    }

    public function getAcademicYearsProperty()
    {
        return AcademicYear::with('semesters')->orderBy('name', 'desc')->get();
    }

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getActiveYearProperty()
    {
        return AcademicYear::where('is_active', true)->first();
    }

    public function getCriteriaProperty()
    {
        return EvaluationCriterion::orderBy('order')->get();
    }

    public function getCategoryTotalsProperty()
    {
        $totals = [
            'upward_student' => 0.0,
            'upward_employee' => 0.0,
            'downward' => 0.0,
            'peer' => 0.0,
            'self' => 0.0
        ];
        $criteria = EvaluationCriterion::all();
        foreach ($criteria as $criterion) {
            $val = $this->criteriaPoints[$criterion->id] ?? 0.0;
            if (isset($totals[$criterion->evaluation_type])) {
                $totals[$criterion->evaluation_type] += is_numeric($val) ? (float)$val : 0.0;
            }
        }
        return $totals;
    }

    // Toggle active academic year
    public function setActiveYear($id)
    {
        AcademicYear::query()->update(['is_active' => false]);
        $year = AcademicYear::findOrFail($id);
        $year->is_active = true;
        $year->save();

        session()->flash('status', "Academic Year '{$year->name}' is now active.");
    }

    // Toggle active semester
    public function setActiveSemester($id)
    {
        Semester::query()->update(['is_active' => false]);
        
        $semester = Semester::findOrFail($id);
        $semester->is_active = true;
        $semester->save();

        AcademicYear::query()->update(['is_active' => false]);
        $year = $semester->academicYear;
        $year->is_active = true;
        $year->save();

        $this->loadPoints();

        session()->flash('status', "Semester '{$semester->name}' ({$year->name}) is now active.");
    }

    // Toggle evaluation status manually
    public function toggleEvaluation()
    {
        $activeSem = $this->activeSemester;
        if (!$activeSem) {
            session()->flash('status', "No active semester configured.");
            return;
        }

        if (!$activeSem->is_evaluation_open) {
            $this->resetErrorBag();

            if (!$activeSem->evaluation_starts_at || !$activeSem->evaluation_ends_at) {
                $this->addError('evaluation_toggle', "Cannot open evaluations: Please configure and save the evaluation window schedule dates first.");
                return;
            }

            $totals = $this->categoryTotals;
            $upwardStudentTarget = is_numeric($this->upwardStudentMaxTarget) ? (float)$this->upwardStudentMaxTarget : 0.0;
            $upwardEmployeeTarget = is_numeric($this->upwardEmployeeMaxTarget) ? (float)$this->upwardEmployeeMaxTarget : 0.0;
            $downwardTarget = is_numeric($this->downwardMaxTarget) ? (float)$this->downwardMaxTarget : 0.0;
            $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
            $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

            $isUpwardStudentBalanced = abs($totals['upward_student'] - $upwardStudentTarget) < 0.001;
            $isUpwardEmployeeBalanced = abs($totals['upward_employee'] - $upwardEmployeeTarget) < 0.001;
            $isDownwardBalanced = abs($totals['downward'] - $downwardTarget) < 0.001;
            $isPeerBalanced = abs($totals['peer'] - $peerTarget) < 0.001;
            $isSelfBalanced = abs($totals['self'] - $selfTarget) < 0.001;

            if (!$isUpwardStudentBalanced || !$isUpwardEmployeeBalanced || !$isDownwardBalanced || !$isPeerBalanced || !$isSelfBalanced) {
                $this->addError('evaluation_toggle', "Cannot open evaluations: One or more evaluation categories are not balanced. Check your Evaluation Criteria Points configuration below.");
                return;
            }
        }

        $activeSem->is_evaluation_open = !$activeSem->is_evaluation_open;
        $activeSem->save();

        $status = $activeSem->is_evaluation_open ? 'opened' : 'closed';
        session()->flash('status', "Evaluations have been successfully {$status}.");
    }

    // Save evaluation schedule
    public function saveSchedule()
    {
        $this->validate([
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
        ]);

        $activeSem = $this->activeSemester;
        if (!$activeSem) {
            return;
        }

        if ($activeSem->evaluation_starts_at || $activeSem->evaluation_ends_at) {
            $this->showOverwriteModal = true;
            return;
        }

        $this->commitSaveSchedule($activeSem);
    }

    public function confirmSaveSchedule()
    {
        $this->showOverwriteModal = false;

        $this->validate([
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after_or_equal:startsAt',
        ]);

        $activeSem = $this->activeSemester;
        if (!$activeSem) {
            return;
        }

        $this->commitSaveSchedule($activeSem);
    }

    private function commitSaveSchedule(Semester $activeSem): void
    {
        $activeSem->update([
            'evaluation_starts_at' => $this->startsAt ? \Illuminate\Support\Carbon::parse($this->startsAt) : null,
            'evaluation_ends_at' => $this->endsAt ? \Illuminate\Support\Carbon::parse($this->endsAt) : null,
        ]);

        session()->flash('status', "Evaluation schedule dates updated successfully.");
    }

    public function confirmRemoveSchedule()
    {
        $activeSem = $this->activeSemester;
        if ($activeSem && $activeSem->is_evaluation_open) {
            session()->flash('status', "Cannot remove schedule: Please close the evaluation first.");
            return;
        }
        $this->showRemoveScheduleModal = true;
    }

    public function clearSchedule()
    {
        $this->showRemoveScheduleModal = false;

        $activeSem = $this->activeSemester;
        if (!$activeSem) {
            return;
        }

        if ($activeSem->is_evaluation_open) {
            session()->flash('status', "Cannot remove schedule: Please close the evaluation first.");
            return;
        }

        $activeSem->update([
            'evaluation_starts_at' => null,
            'evaluation_ends_at'   => null,
        ]);

        $this->startsAt = '';
        $this->endsAt   = '';

        session()->flash('status', "Evaluation schedule has been cleared.");
    }

    // Create Academic Year
    public function createAcademicYear()
    {
        $this->validate([
            'newYearName' => 'required|string|unique:academic_years,name|regex:/^\d{4}-\d{4}$/',
        ], [
            'newYearName.regex' => 'The format must be YYYY-YYYY (e.g. 2025-2026).'
        ]);

        $year = AcademicYear::create([
            'name' => $this->newYearName,
            'is_active' => false,
        ]);

        if (AcademicYear::where('is_active', true)->count() === 0) {
            $year->is_active = true;
            $year->save();
        }

        $this->newYearName = '';
        $this->showYearModal = false;
        session()->flash('status', "Academic Year successfully created.");
    }

    // Create Semester
    public function createSemester()
    {
        $this->validate([
            'selectedYearId' => 'required|exists:academic_years,id',
            'newSemesterName' => 'required|string',
        ]);

        $semester = Semester::create([
            'academic_year_id' => $this->selectedYearId,
            'name' => $this->newSemesterName,
            'is_active' => false,
        ]);

        if (Semester::where('is_active', true)->count() === 0) {
            $this->setActiveSemester($semester->id);
        }

        $this->newSemesterName = '';
        $this->selectedYearId = '';
        $this->showSemModal = false;
        session()->flash('status', "Semester successfully created.");
    }

    // Open Criterion Modal with specific Type
    public function openCriterionModal($type)
    {
        $this->newCriterionType = $type;
        $this->newCriterionName = '';
        $this->newCriterionMaxPoints = '0';
        $this->showCriterionModal = true;
    }

    // Create Evaluation Criterion
    public function createCriterion()
    {
        $this->validate([
            'newCriterionName' => 'required|string|max:255',
            'newCriterionMaxPoints' => 'required|numeric|min:0',
            'newCriterionType' => 'required|in:upward_student,upward_employee,downward,peer,self',
        ]);

        $exists = EvaluationCriterion::where('evaluation_type', $this->newCriterionType)
            ->where('name', $this->newCriterionName)
            ->exists();

        if ($exists) {
            $this->addError('newCriterionName', 'This criterion name already exists for this evaluation type.');
            return;
        }

        $maxOrder = EvaluationCriterion::where('evaluation_type', $this->newCriterionType)->max('order') ?? 0;

        EvaluationCriterion::create([
            'evaluation_type' => $this->newCriterionType,
            'name' => $this->newCriterionName,
            'max_points' => (float)$this->newCriterionMaxPoints,
            'order' => $maxOrder + 1,
        ]);

        $this->newCriterionName = '';
        $this->newCriterionMaxPoints = '0';
        $this->showCriterionModal = false;

        $this->loadPoints();
        session()->flash('status', "Evaluation criterion created successfully.");
    }

    public function confirmDeleteCriterion($id)
    {
        $this->deletingCriterion = EvaluationCriterion::findOrFail($id);
        $this->showDeleteCriterionModal = true;
    }

    public function deleteCriterion()
    {
        if ($this->deletingCriterion) {
            $name = $this->deletingCriterion->name;
            $this->deletingCriterion->delete();
            $this->deletingCriterion = null;
            $this->showDeleteCriterionModal = false;
            $this->loadPoints();
            session()->flash('status', "Evaluation criterion '{$name}' deleted successfully.");
        }
    }

    // Save criteria points and dynamic max score targets
    public function savePoints()
    {
        $totals = $this->categoryTotals;

        $upwardStudentTarget = is_numeric($this->upwardStudentMaxTarget) ? (float)$this->upwardStudentMaxTarget : 0.0;
        $upwardEmployeeTarget = is_numeric($this->upwardEmployeeMaxTarget) ? (float)$this->upwardEmployeeMaxTarget : 0.0;
        $downwardTarget = is_numeric($this->downwardMaxTarget) ? (float)$this->downwardMaxTarget : 0.0;
        $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
        $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

        if (abs($totals['upward_student'] - $upwardStudentTarget) > 0.001) {
            $this->addError('points_upward_student', "Student Evaluation total points must equal exactly {$upwardStudentTarget} (Current: {$totals['upward_student']}).");
            return;
        }

        if (abs($totals['upward_employee'] - $upwardEmployeeTarget) > 0.001) {
            $this->addError('points_upward_employee', "Employee Upward evaluation total points must equal exactly {$upwardEmployeeTarget} (Current: {$totals['upward_employee']}).");
            return;
        }

        if (abs($totals['downward'] - $downwardTarget) > 0.001) {
            $this->addError('points_downward', "Supervisor Evaluation total points must equal exactly {$downwardTarget} (Current: {$totals['downward']}).");
            return;
        }

        if (abs($totals['peer'] - $peerTarget) > 0.001) {
            $this->addError('points_peer', "Peer Evaluation total points must equal exactly {$peerTarget} (Current: {$totals['peer']}).");
            return;
        }

        if (abs($totals['self'] - $selfTarget) > 0.001) {
            $this->addError('points_self', "Self Evaluation total points must equal exactly {$selfTarget} (Current: {$totals['self']}).");
            return;
        }

        $activeSem = Semester::where('is_active', true)->first();
        if ($activeSem) {
            $activeSem->update([
                'upward_student_max_points' => $upwardStudentTarget,
                'upward_employee_max_points' => $upwardEmployeeTarget,
                'dean_max_points' => is_numeric($this->deanMaxTarget) ? (float)$this->deanMaxTarget : 50.0,
                'program_head_max_points' => is_numeric($this->programHeadMaxTarget) ? (float)$this->programHeadMaxTarget : 50.0,
                'downward_max_points' => $downwardTarget,
                'peer_max_points' => $peerTarget,
                'self_max_points' => $selfTarget,
                'staff_max_points' => is_numeric($this->staffMaxTarget) ? (float)$this->staffMaxTarget : 10.0,
            ]);
        }

        foreach ($this->criteriaPoints as $id => $points) {
            $val = is_numeric($points) ? (float)$points : 0.0;
            $criterion = EvaluationCriterion::findOrFail($id);
            $criterion->update([
                'max_points' => $val,
            ]);
        }

        session()->flash('status', "Evaluation criteria score points and dynamic max targets updated successfully.");
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex justify-between items-center w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Settings</flux:heading>
            <flux:subheading>Manage system academic periods, evaluation windows, and point allocations.</flux:subheading>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 rounded-xl border border-emerald-200 dark:border-emerald-800 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <!-- SECTION 1: Active Evaluation Status & Toggle Control Banner (Full Width) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6 w-full">
        <div class="flex items-center gap-4">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-xl border border-indigo-100 dark:border-indigo-900">
                <flux:icon icon="bolt" class="size-7" />
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">System Access Status</h2>
                    @if($this->activeSemester)
                        @php $status = $this->activeSemester->evaluation_status; @endphp
                        @if($status === 'active')
                            <flux:badge variant="success" size="sm" class="font-bold">Open & Active</flux:badge>
                        @elseif($status === 'scheduled')
                            <flux:badge variant="warning" size="sm" class="font-bold">Scheduled</flux:badge>
                        @elseif($status === 'expired')
                            <flux:badge variant="danger" size="sm" class="font-bold">Expired</flux:badge>
                        @else
                            <flux:badge variant="danger" size="sm" class="font-bold">Closed / Locked</flux:badge>
                        @endif
                    @else
                        <flux:badge variant="neutral" size="sm">No Active Semester</flux:badge>
                    @endif
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    @if($this->activeSemester)
                        Active Period: <span class="font-bold text-zinc-800 dark:text-zinc-200">A.Y. {{ $this->activeYear->name }} — {{ $this->activeSemester->name }}</span>
                    @else
                        No active academic year or semester selected.
                    @endif
                </p>
            </div>
        </div>

        @if($this->activeSemester)
            <div class="flex flex-col items-end gap-2 w-full md:w-auto">
                <flux:button 
                    variant="{{ $this->activeSemester->is_evaluation_open ? 'danger' : 'primary' }}" 
                    wire:click="toggleEvaluation"
                    class="w-full md:w-auto font-bold"
                >
                    {{ $this->activeSemester->is_evaluation_open ? 'Close Evaluation System' : 'Open Evaluation System' }}
                </flux:button>
                @error('evaluation_toggle')
                    <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>

    <!-- SECTION 2: Evaluation Window Schedule & Academic Periods (2 Balanced Grid Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start w-full">
        
        <!-- Card 1: Set Evaluation Dates Schedule Window -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-4 h-full">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon icon="clock" class="size-5 text-indigo-500" />
                    Set Evaluation Schedule Dates
                </h2>
            </div>

            @if($this->activeSemester)
                <form wire:submit="saveSchedule" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <flux:input
                                type="datetime-local"
                                wire:model="startsAt"
                                label="Start Date & Time"
                            />
                        </div>
                        <div>
                            <flux:input
                                type="datetime-local"
                                wire:model="endsAt"
                                label="End Date & Time"
                            />
                        </div>
                    </div>
                    @error('endsAt')
                        <span class="text-xs text-rose-500 font-semibold block">{{ $message }}</span>
                    @enderror

                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="outline" size="sm" icon="calendar">
                            Save Schedule Window
                        </flux:button>
                    </div>
                </form>

                <!-- Current Saved Schedule Banner -->
                @if($this->activeSemester->evaluation_starts_at || $this->activeSemester->evaluation_ends_at)
                    <div class="mt-2 rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/30 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wide flex items-center gap-1.5">
                                <flux:icon icon="calendar-days" class="size-4" />
                                Current Saved Schedule
                            </span>
                            @if($this->activeSemester->is_evaluation_open)
                                <span class="text-xs text-zinc-400 dark:text-zinc-500 italic">Locked while open</span>
                            @else
                                <button
                                    type="button"
                                    wire:click="confirmRemoveSchedule"
                                    class="text-xs font-semibold text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors"
                                >
                                    Clear Schedule
                                </button>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <p class="text-zinc-500 font-medium">Opens On</p>
                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->activeSemester->evaluation_starts_at ? $this->activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A') : '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-zinc-500 font-medium">Closes On</p>
                                <p class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->activeSemester->evaluation_ends_at ? $this->activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A') : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <p class="text-xs text-zinc-400">Select an active semester to configure evaluation schedule dates.</p>
            @endif
        </div>

        <!-- Card 2: Academic Years & Semesters Management -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-4 h-full">
            <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon icon="calendar" class="size-5 text-indigo-500" />
                    Academic Years & Semesters
                </h2>
                <div class="flex gap-2">
                    <flux:button size="xs" variant="outline" icon="plus" wire:click="$set('showYearModal', true)">Add Year</flux:button>
                    <flux:button size="xs" variant="outline" icon="plus" wire:click="$set('showSemModal', true)">Add Semester</flux:button>
                </div>
            </div>

            <div class="space-y-4 max-h-[350px] overflow-y-auto pr-1">
                @forelse($this->academicYears as $year)
                    <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden">
                        <!-- Year Header -->
                        <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 p-3 border-b border-zinc-200 dark:border-zinc-800">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">A.Y. {{ $year->name }}</span>
                                @if($year->is_active)
                                    <flux:badge variant="info" size="sm" class="font-bold">Active Year</flux:badge>
                                @endif
                            </div>
                            
                            @if(!$year->is_active)
                                <flux:button size="xs" variant="ghost" wire:click="setActiveYear({{ $year->id }})">Set Active</flux:button>
                            @endif
                        </div>

                        <!-- Semesters List -->
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse($year->semesters as $sem)
                                <div class="flex items-center justify-between p-3 pl-6 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition-colors">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $sem->name }}</span>
                                        @if($sem->is_active)
                                            <flux:badge variant="info" size="sm" class="font-bold">Active Semester</flux:badge>
                                        @endif
                                    </div>

                                    @if(!$sem->is_active)
                                        <flux:button size="xs" variant="ghost" wire:click="setActiveSemester({{ $sem->id }})">
                                            Activate
                                        </flux:button>
                                    @endif
                                </div>
                            @empty
                                <div class="p-3 pl-6 text-xs text-zinc-400 italic">No semesters added under this year.</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-zinc-400 text-sm">No Academic Years created.</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- SECTION 3: Evaluation Weight Score Card & Dynamic Target Inputs (Full Width Card with 5px #800000 Left Border) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6 w-full" style="border-left: 5px solid #800000 !important;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    <flux:icon icon="chart-bar" class="size-6 text-[#800000] dark:text-red-400" />
                    Evaluation Weight Score Card & Dynamic Max Points Target
                </h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                    Configure max score points for all 7 evaluation types. Relative percentage weights update automatically.
                </p>
            </div>

            @php
                $totals = $this->categoryTotals;
                $tStudent = is_numeric($this->upwardStudentMaxTarget) ? (float)$this->upwardStudentMaxTarget : 0.0;
                $tSelf = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;
                $tDean = is_numeric($this->deanMaxTarget) ? (float)$this->deanMaxTarget : 0.0;
                $tHead = is_numeric($this->programHeadMaxTarget) ? (float)$this->programHeadMaxTarget : 0.0;
                $tPeer = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
                $tSuper = is_numeric($this->downwardMaxTarget) ? (float)$this->downwardMaxTarget : 0.0;
                $tStaff = is_numeric($this->staffMaxTarget) ? (float)$this->staffMaxTarget : 0.0;

                $isUpwardStudentBalanced = abs(($totals['upward_student'] ?? 0.0) - $tStudent) < 0.001;
                $isUpwardEmployeeBalanced = abs(($totals['upward_employee'] ?? 0.0) - $tDean) < 0.001;
                $isDownwardBalanced = abs(($totals['downward'] ?? 0.0) - $tSuper) < 0.001;
                $isPeerBalanced = abs(($totals['peer'] ?? 0.0) - $tPeer) < 0.001;
                $isSelfBalanced = abs(($totals['self'] ?? 0.0) - $tSelf) < 0.001;
                $allBalanced = $isUpwardStudentBalanced && $isUpwardEmployeeBalanced && $isDownwardBalanced && $isPeerBalanced && $isSelfBalanced;

                $grandTotal = max(1, $tStudent + $tSelf + $tDean + $tHead + $tPeer + $tSuper + $tStaff);

                $studentPct = round(($tStudent / $grandTotal) * 100);
                $selfPct = round(($tSelf / $grandTotal) * 100);
                $deanPct = round(($tDean / $grandTotal) * 100);
                $headPct = round(($tHead / $grandTotal) * 100);
                $peerPct = round(($tPeer / $grandTotal) * 100);
                $superPct = round(($tSuper / $grandTotal) * 100);
                $staffPct = round(($tStaff / $grandTotal) * 100);
            @endphp

            <flux:badge variant="{{ $allBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold self-start sm:self-auto">
                {{ $allBalanced ? 'Points Balanced' : 'Unbalanced Points' }}
            </flux:badge>
        </div>

        <!-- 7 Evaluation Terms Grid (Spacious Responsive Layout) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- 1. Student Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-indigo-500"></span>
                            Student Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded">{{ $studentPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Students evaluating Professors</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="upwardStudentMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 2. Self Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-rose-500"></span>
                            Self Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 px-2 py-0.5 rounded">{{ $selfPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Faculty/Staff self evaluation</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="selfMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 3. Dean Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-emerald-500"></span>
                            Dean Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-2 py-0.5 rounded">{{ $deanPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Executive Dean evaluation</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="deanMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 4. Program Head Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-sky-500"></span>
                            Program Head Eval
                        </span>
                        <span class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2 py-0.5 rounded">{{ $headPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Program Head evaluation</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="programHeadMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 5. Peer Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-purple-500"></span>
                            Peer Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-purple-600 dark:text-purple-400 bg-purple-50 dark:bg-purple-950/60 px-2 py-0.5 rounded">{{ $peerPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Faculty peer evaluation</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="peerMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 6. Supervisor Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-amber-500"></span>
                            Supervisor Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-2 py-0.5 rounded">{{ $superPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Deans/Program Heads rating Faculty</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="downwardMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- 7. Staff Evaluation -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-3 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-teal-500"></span>
                            Staff Evaluation
                        </span>
                        <span class="text-xs font-mono font-bold text-teal-600 dark:text-teal-400 bg-teal-50 dark:bg-teal-950/60 px-2 py-0.5 rounded">{{ $staffPct }}% weight</span>
                    </div>
                    <p class="text-[11px] text-zinc-400">Staff performance evaluation</p>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-[11px] font-semibold text-zinc-500 uppercase tracking-wider">Target Points</label>
                    <div class="flex items-center gap-1">
                        <flux:input type="number" wire:model.live="staffMaxTarget" min="0" class="font-bold text-sm" />
                        <span class="text-xs text-zinc-400 font-bold">pts</span>
                    </div>
                </div>
            </div>

            <!-- Total Cumulative Target Card -->
            <div class="p-4 bg-[#800000]/10 dark:bg-red-950/30 rounded-xl border border-[#800000]/20 dark:border-red-900/50 flex flex-col justify-between space-y-2">
                <span class="text-xs font-bold text-[#800000] dark:text-red-400 uppercase tracking-wider">Total Max Evaluation Target</span>
                <span class="font-mono font-bold text-2xl text-[#800000] dark:text-red-400">{{ $grandTotal }} <span class="text-sm">pts</span></span>
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400">Sum of active target score points</span>
            </div>
        </div>
    </div>

    <!-- SECTION 4: Evaluation Questionnaire Parts Breakdown Setup (Full Width Card) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6 w-full">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                <flux:icon icon="adjustments-horizontal" class="size-6 text-indigo-500" />
                Questionnaire Parts & Point Allocations Setup
            </h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Configure individual criteria parts under each evaluation category. The sum of parts must equal the category target points.
            </p>
        </div>

        <form wire:submit="savePoints" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                
                <!-- Student Evaluation Parts Category -->
                <div class="p-5 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Student Evaluation Parts</h3>
                            <flux:badge variant="{{ $isUpwardStudentBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold">
                                {{ $totals['upward_student'] ?? 0 }} / {{ $tStudent }} pts
                            </flux:badge>
                        </div>
                        <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('upward_student')">Add Part</flux:button>
                    </div>

                    <div class="space-y-2">
                        @foreach($this->criteria->where('evaluation_type', 'upward_student') as $criterion)
                            <div class="flex items-center justify-between gap-4 p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xs">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[11px] text-zinc-400 font-mono block">Part #{{ $criterion->order }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate block">{{ $criterion->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 flex items-center gap-1">
                                        <flux:input 
                                            type="number" 
                                            wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                            min="0" 
                                            class="text-right font-bold text-sm"
                                        />
                                        <span class="text-xs text-zinc-400 font-semibold">pts</span>
                                    </div>
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="confirmDeleteCriterion({{ $criterion->id }})"
                                        class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('points_upward_student')
                        <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Supervisor Evaluation Parts Category -->
                <div class="p-5 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Supervisor Evaluation Parts</h3>
                            <flux:badge variant="{{ $isDownwardBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold">
                                {{ $totals['downward'] ?? 0 }} / {{ $tSuper }} pts
                            </flux:badge>
                        </div>
                        <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('downward')">Add Part</flux:button>
                    </div>

                    <div class="space-y-2">
                        @foreach($this->criteria->where('evaluation_type', 'downward') as $criterion)
                            <div class="flex items-center justify-between gap-4 p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xs">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[11px] text-zinc-400 font-mono block">Part #{{ $criterion->order }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate block">{{ $criterion->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 flex items-center gap-1">
                                        <flux:input 
                                            type="number" 
                                            wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                            min="0" 
                                            class="text-right font-bold text-sm"
                                        />
                                        <span class="text-xs text-zinc-400 font-semibold">pts</span>
                                    </div>
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="confirmDeleteCriterion({{ $criterion->id }})"
                                        class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('points_downward')
                        <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Peer Evaluation Parts Category -->
                <div class="p-5 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Peer Evaluation Parts</h3>
                            <flux:badge variant="{{ $isPeerBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold">
                                {{ $totals['peer'] ?? 0 }} / {{ $tPeer }} pts
                            </flux:badge>
                        </div>
                        <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('peer')">Add Part</flux:button>
                    </div>

                    <div class="space-y-2">
                        @foreach($this->criteria->where('evaluation_type', 'peer') as $criterion)
                            <div class="flex items-center justify-between gap-4 p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xs">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[11px] text-zinc-400 font-mono block">Part #{{ $criterion->order }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate block">{{ $criterion->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 flex items-center gap-1">
                                        <flux:input 
                                            type="number" 
                                            wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                            min="0" 
                                            class="text-right font-bold text-sm"
                                        />
                                        <span class="text-xs text-zinc-400 font-semibold">pts</span>
                                    </div>
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="confirmDeleteCriterion({{ $criterion->id }})"
                                        class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('points_peer')
                        <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Self Evaluation Parts Category -->
                <div class="p-5 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200/80 dark:border-zinc-700/80 space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3">
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Self Evaluation Parts</h3>
                            <flux:badge variant="{{ $isSelfBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold">
                                {{ $totals['self'] ?? 0 }} / {{ $tSelf }} pts
                            </flux:badge>
                        </div>
                        <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('self')">Add Part</flux:button>
                    </div>

                    <div class="space-y-2">
                        @foreach($this->criteria->where('evaluation_type', 'self') as $criterion)
                            <div class="flex items-center justify-between gap-4 p-3 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xs">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[11px] text-zinc-400 font-mono block">Part #{{ $criterion->order }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-200 truncate block">{{ $criterion->name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-20 flex items-center gap-1">
                                        <flux:input 
                                            type="number" 
                                            wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                            min="0" 
                                            class="text-right font-bold text-sm"
                                        />
                                        <span class="text-xs text-zinc-400 font-semibold">pts</span>
                                    </div>
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="confirmDeleteCriterion({{ $criterion->id }})"
                                        class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('points_self')
                        <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-between border-t border-zinc-200 dark:border-zinc-800 pt-4 mt-4">
                <div>
                    <span class="text-xs text-zinc-500 font-medium">Scoring Balance Status</span>
                    <span class="text-sm font-bold block {{ $allBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $allBalanced ? 'All criteria categories balanced' : 'Please balance part points with target points' }}
                    </span>
                </div>

                <flux:button 
                    type="submit" 
                    variant="primary" 
                    :disabled="!$allBalanced"
                >
                    Save Points & Score Weights
                </flux:button>
            </div>
        </form>
    </div>

    <!-- Create Academic Year Modal -->
    @if($showYearModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800 space-y-4">
            <flux:heading size="lg">Create Academic Year</flux:heading>
            
            <form wire:submit="createAcademicYear" class="space-y-4">
                <flux:input 
                    wire:model="newYearName" 
                    label="Academic Year Name" 
                    placeholder="e.g. 2026-2027" 
                    required 
                />
                
                <p class="text-[11px] text-zinc-500">Format must be YYYY-YYYY (e.g. 2025-2026).</p>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showYearModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Create</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Create Semester Modal -->
    @if($showSemModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800 space-y-4">
            <flux:heading size="lg">Create Semester</flux:heading>
            
            <form wire:submit="createSemester" class="space-y-4">
                <flux:select wire:model="selectedYearId" label="Academic Year" required>
                    <flux:select.option value="">Select Academic Year</flux:select.option>
                    @foreach($this->academicYears as $year)
                        <flux:select.option value="{{ $year->id }}">{{ $year->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newSemesterName" label="Semester Name" required>
                    <flux:select.option value="">Select Semester</flux:select.option>
                    <flux:select.option value="1st Semester">1st Semester</flux:select.option>
                    <flux:select.option value="2nd Semester">2nd Semester</flux:select.option>
                    <flux:select.option value="Summer">Summer</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showSemModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Create</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Create Criterion Modal -->
    @if($showCriterionModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800 space-y-4">
            <flux:heading size="lg">Create Part / Criterion</flux:heading>
            
            <form wire:submit="createCriterion" class="space-y-4">
                <flux:input 
                    wire:model="newCriterionName" 
                    label="Part Name" 
                    placeholder="e.g. Part 1: Course Preparation" 
                    required 
                />
                @error('newCriterionName')
                    <span class="text-xs text-rose-500 font-semibold block">{{ $message }}</span>
                @enderror

                <flux:input 
                    type="number"
                    wire:model="newCriterionMaxPoints" 
                    label="Max Points" 
                    min="0"
                    required 
                />

                <flux:select wire:model="newCriterionType" label="Evaluation Target Type" required>
                    <flux:select.option value="upward_student">Student Evaluation</flux:select.option>
                    <flux:select.option value="upward_employee">Employee Upward Evaluation (PHs/Deans)</flux:select.option>
                    <flux:select.option value="downward">Supervisor Evaluation (Faculty/PHs)</flux:select.option>
                    <flux:select.option value="peer">Peer Evaluation (Faculty)</flux:select.option>
                    <flux:select.option value="self">Self Evaluation</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showCriterionModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Create Part</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Criterion Confirmation Modal -->
    @if($showDeleteCriterionModal && $deletingCriterion)
    <x-confirmation-modal 
        title="Delete Evaluation Part/Criterion" 
        on-confirm="deleteCriterion" 
        on-cancel="$set('showDeleteCriterionModal', false)" 
    >
        Are you sure you want to delete this evaluation criterion? This action cannot be undone and will remove all questions under this category.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Evaluation Type</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150 capitalize">{{ $deletingCriterion->evaluation_type }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Max Points Allocation</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingCriterion->max_points }} pts</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Part Category Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">Part {{ $deletingCriterion->order }}: {{ $deletingCriterion->name }}</span>
                </div>
            </div>
        </x-slot:details>
    </x-confirmation-modal>
    @endif

    <!-- Overwrite Schedule Confirmation Modal -->
    @if($showOverwriteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 w-full max-w-sm p-6 space-y-5">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Overwrite Existing Schedule?</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        There is already a saved evaluation schedule. Saving now will
                        <span class="font-semibold text-amber-600 dark:text-amber-400">overwrite</span>
                        the current schedule with your new dates.
                    </p>
                </div>
            </div>

            @if($this->activeSemester->evaluation_starts_at || $this->activeSemester->evaluation_ends_at)
                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-4 py-3 text-xs space-y-1.5">
                    <p class="text-zinc-500 font-semibold mb-1">Being replaced:</p>
                    <p class="text-zinc-700 dark:text-zinc-300">
                        <span class="font-semibold">Start:</span>
                        {{ $this->activeSemester->evaluation_starts_at?->format('M d, Y h:i A') ?? '—' }}
                    </p>
                    <p class="text-zinc-700 dark:text-zinc-300">
                        <span class="font-semibold">End:</span>
                        {{ $this->activeSemester->evaluation_ends_at?->format('M d, Y h:i A') ?? '—' }}
                    </p>
                </div>
            @endif

            <div class="flex justify-end gap-2 pt-1">
                <flux:button size="sm" wire:click="$set('showOverwriteModal', false)">
                    Cancel
                </flux:button>
                <flux:button size="sm" variant="danger" wire:click="confirmSaveSchedule">
                    Yes, Overwrite
                </flux:button>
            </div>
        </div>
    </div>
    @endif

    <!-- Remove Schedule Confirmation Modal -->
    @if($showRemoveScheduleModal && $this->activeSemester)
    <x-confirmation-modal 
        title="Remove Schedule" 
        on-confirm="clearSchedule" 
        on-cancel="$set('showRemoveScheduleModal', false)" 
        confirm-text="Yes, Remove"
    >
        This will permanently clear the saved evaluation window schedule. The <span class="font-semibold text-rose-600 dark:text-rose-400">Open Evaluation</span> button will also stop working until a new schedule is set.

        @if($this->activeSemester->evaluation_starts_at || $this->activeSemester->evaluation_ends_at)
            <x-slot:details>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Start Date & Time</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-150">
                            {{ $this->activeSemester->evaluation_starts_at ? $this->activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A') : '—' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">End Date & Time</span>
                        <span class="font-bold text-zinc-900 dark:text-zinc-150">
                            {{ $this->activeSemester->evaluation_ends_at ? $this->activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A') : '—' }}
                        </span>
                    </div>
                </div>
            </x-slot:details>
        @endif
    </x-confirmation-modal>
    @endif
</div>
