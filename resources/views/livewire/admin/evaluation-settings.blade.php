<?php

use Livewire\Volt\Component;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\EvaluationCriterion;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Program;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    // Program CRUD
    public bool $showProgModal = false;
    public bool $showDeleteProgModal = false;
    public string $progId = '';
    public string $progCode = '';
    public string $progName = '';
    public string $progDeptId = '';
    public string $progHeadId = '';
    public ?Program $deletingProg = null;

    // Department CRUD
    public bool $showDeptModal = false;
    public bool $showDeleteDeptModal = false;
    public string $deptId = '';
    public string $deptCode = '';
    public string $deptName = '';
    public string $deptDeanId = '';
    public ?Department $deletingDept = null;

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
    public string $newCriterionType = 'student';
    public bool $showCriterionModal = false;

    // Criteria points (array keyed by ID)
    public array $criteriaPoints = [];

    // Max targets config
    public string $studentMaxTarget = '90';
    public string $peerMaxTarget = '50';
    public string $selfMaxTarget = '10';

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
            $this->studentMaxTarget = (string)(float)$activeSem->student_max_points;
            $this->peerMaxTarget = (string)(float)$activeSem->peer_max_points;
            $this->selfMaxTarget = (string)(float)$activeSem->self_max_points;
            $this->startsAt = $activeSem->evaluation_starts_at ? $activeSem->evaluation_starts_at->format('Y-m-d\TH:i') : '';
            $this->endsAt = $activeSem->evaluation_ends_at ? $activeSem->evaluation_ends_at->format('Y-m-d\TH:i') : '';
        }
    }

    public function getAcademicYearsProperty()
    {
        return AcademicYear::with('semesters')->orderBy('name', 'desc')->get();
    }

    public function getDepartmentsProperty()
    {
        return Department::with('dean')->orderBy('name')->get();
    }

    public function getDeansProperty()
    {
        return Employee::where('role', 'dean')->orderBy('last_name')->get();
    }

    public function getProgramsProperty()
    {
        return Program::with(['department', 'programHead'])->orderBy('name')->get();
    }

    public function getProgramHeadsProperty()
    {
        return Employee::where('role', 'program head')->orderBy('last_name')->get();
    }

    // Open department modal
    public function openDeptModal($id = null)
    {
        $this->resetErrorBag();
        if ($id) {
            $dept = Department::findOrFail($id);
            $this->deptId = (string)$dept->id;
            $this->deptCode = $dept->code;
            $this->deptName = $dept->name;
            $this->deptDeanId = (string)$dept->dean_id;
        } else {
            $this->deptId = '';
            $this->deptCode = '';
            $this->deptName = '';
            $this->deptDeanId = '';
        }
        $this->showDeptModal = true;
    }

    // Save department (create or update)
    public function saveDept()
    {
        $rules = [
            'deptCode' => 'required|string|max:50|unique:departments,code,' . $this->deptId,
            'deptName' => 'required|string|max:255',
            'deptDeanId' => 'nullable|exists:employees,id',
        ];

        $this->validate($rules);

        $data = [
            'code' => $this->deptCode,
            'name' => $this->deptName,
            'dean_id' => $this->deptDeanId ?: null,
        ];

        if ($this->deptId) {
            $dept = Department::findOrFail($this->deptId);
            $dept->update($data);
            
            // Sync dean employee department if assigned
            if ($dept->dean_id) {
                Employee::where('id', $dept->dean_id)->update(['department_id' => $dept->id]);
            }
            
            session()->flash('status', "Department '{$dept->name}' updated successfully.");
        } else {
            $dept = Department::create($data);
            
            // Sync dean employee department if assigned
            if ($dept->dean_id) {
                Employee::where('id', $dept->dean_id)->update(['department_id' => $dept->id]);
            }
            
            session()->flash('status', "Department '{$dept->name}' created successfully.");
        }

        $this->showDeptModal = false;
    }

    // Confirm Delete
    public function confirmDeleteDept($id)
    {
        $this->deletingDept = Department::with('dean')->findOrFail($id);
        $this->showDeleteDeptModal = true;
    }

    // Delete Department
    public function deleteDept()
    {
        if ($this->deletingDept) {
            $name = $this->deletingDept->name;
            $this->deletingDept->delete();
            $this->deletingDept = null;
            session()->flash('status', "Department '{$name}' deleted successfully.");
        }
        $this->showDeleteDeptModal = false;
    }

    // Open program modal
    public function openProgModal($id = null)
    {
        $this->resetErrorBag();
        if ($id) {
            $prog = Program::findOrFail($id);
            $this->progId = (string)$prog->id;
            $this->progCode = $prog->code;
            $this->progName = $prog->name;
            $this->progDeptId = (string)$prog->department_id;
            $this->progHeadId = (string)$prog->program_head_id;
        } else {
            $this->progId = '';
            $this->progCode = '';
            $this->progName = '';
            $this->progDeptId = '';
            $this->progHeadId = '';
        }
        $this->showProgModal = true;
    }

    // Save program (create or update)
    public function saveProg()
    {
        $rules = [
            'progCode' => 'required|string|max:50|unique:programs,code,' . $this->progId,
            'progName' => 'required|string|max:255',
            'progDeptId' => 'required|exists:departments,id',
            'progHeadId' => 'nullable|exists:employees,id',
        ];

        $this->validate($rules);

        $data = [
            'code' => $this->progCode,
            'name' => $this->progName,
            'department_id' => $this->progDeptId,
            'program_head_id' => $this->progHeadId ?: null,
        ];

        if ($this->progId) {
            $prog = Program::findOrFail($this->progId);
            $prog->update($data);
            
            // Sync program head employee department if assigned
            if ($prog->program_head_id) {
                Employee::where('id', $prog->program_head_id)->update(['department_id' => $prog->department_id]);
            }
            
            session()->flash('status', "Program '{$prog->name}' updated successfully.");
        } else {
            $prog = Program::create($data);
            
            // Sync program head employee department if assigned
            if ($prog->program_head_id) {
                Employee::where('id', $prog->program_head_id)->update(['department_id' => $prog->department_id]);
            }
            
            session()->flash('status', "Program '{$prog->name}' created successfully.");
        }

        $this->showProgModal = false;
    }

    // Confirm Delete Program
    public function confirmDeleteProg($id)
    {
        $this->deletingProg = Program::with(['department', 'programHead'])->findOrFail($id);
        $this->showDeleteProgModal = true;
    }

    // Delete Program
    public function deleteProg()
    {
        if ($this->deletingProg) {
            $name = $this->deletingProg->name;
            $this->deletingProg->delete();
            $this->deletingProg = null;
            session()->flash('status', "Program '{$name}' deleted successfully.");
        }
        $this->showDeleteProgModal = false;
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
        $totals = ['student' => 0.0, 'peer' => 0.0, 'self' => 0.0];
        $criteria = EvaluationCriterion::all();
        foreach ($criteria as $criterion) {
            $val = $this->criteriaPoints[$criterion->id] ?? 0.0;
            $totals[$criterion->evaluation_type] += is_numeric($val) ? (float)$val : 0.0;
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
        // Set all semesters to inactive
        Semester::query()->update(['is_active' => false]);
        
        $semester = Semester::findOrFail($id);
        $semester->is_active = true;
        $semester->save();

        // Also make sure its parent Academic Year is set to active
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

        // If currently closed, validate before opening
        if (!$activeSem->is_evaluation_open) {
            $this->resetErrorBag();

            // 1. Check if schedule window is set in the database
            if (!$activeSem->evaluation_starts_at || !$activeSem->evaluation_ends_at) {
                $this->addError('evaluation_toggle', "Cannot open evaluations: Please configure and save the evaluation window schedule dates first.");
                return;
            }

            // 2. Check if criteria points are balanced
            $totals = $this->categoryTotals;
            $studentTarget = is_numeric($this->studentMaxTarget) ? (float)$this->studentMaxTarget : 0.0;
            $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
            $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

            $isStudentBalanced = abs($totals['student'] - $studentTarget) < 0.001;
            $isPeerBalanced = abs($totals['peer'] - $peerTarget) < 0.001;
            $isSelfBalanced = abs($totals['self'] - $selfTarget) < 0.001;

            if (!$isStudentBalanced || !$isPeerBalanced || !$isSelfBalanced) {
                $this->addError('evaluation_toggle', "Cannot open evaluations: One or more evaluation categories are not balanced. Check your Evaluation Criteria Points configuration below.");
                return;
            }
        }

        $activeSem->is_evaluation_open = !$activeSem->is_evaluation_open;
        $activeSem->save();

        $status = $activeSem->is_evaluation_open ? 'opened' : 'closed';
        session()->flash('status', "Evaluations have been successfully {$status}.");
    }

    // Save evaluation schedule — intercept if one already exists
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

        // If there is already a saved schedule, ask for confirmation before overwriting
        if ($activeSem->evaluation_starts_at || $activeSem->evaluation_ends_at) {
            $this->showOverwriteModal = true;
            return;
        }

        $this->commitSaveSchedule($activeSem);
    }

    // Called when admin confirms overwrite from the modal
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

    // Internal: persist dates to DB
    private function commitSaveSchedule(Semester $activeSem): void
    {
        $activeSem->update([
            'evaluation_starts_at' => $this->startsAt ? \Illuminate\Support\Carbon::parse($this->startsAt) : null,
            'evaluation_ends_at' => $this->endsAt ? \Illuminate\Support\Carbon::parse($this->endsAt) : null,
        ]);

        session()->flash('status', "Evaluation schedule dates updated successfully.");
    }

    // Open the remove schedule confirmation modal
    public function confirmRemoveSchedule()
    {
        $activeSem = $this->activeSemester;
        if ($activeSem && $activeSem->is_evaluation_open) {
            session()->flash('status', "Cannot remove schedule: Please close the evaluation first.");
            return;
        }
        $this->showRemoveScheduleModal = true;
    }

    // Clear / remove the saved schedule from DB
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
            'newCriterionType' => 'required|in:student,peer,self',
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

    // Confirm Delete Criterion
    public function confirmDeleteCriterion($id)
    {
        $this->deletingCriterion = EvaluationCriterion::findOrFail($id);
        $this->showDeleteCriterionModal = true;
    }

    // Delete Evaluation Criterion
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

    // Save criteria points and targets
    public function savePoints()
    {
        $totals = $this->categoryTotals;

        $studentTarget = is_numeric($this->studentMaxTarget) ? (float)$this->studentMaxTarget : 0.0;
        $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
        $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

        if (abs($totals['student'] - $studentTarget) > 0.001) {
            $this->addError('points_student', "Student evaluation total points must equal exactly {$studentTarget} (Current: {$totals['student']}).");
            return;
        }

        if (abs($totals['peer'] - $peerTarget) > 0.001) {
            $this->addError('points_peer', "Peer evaluation total points must equal exactly {$peerTarget} (Current: {$totals['peer']}).");
            return;
        }

        if (abs($totals['self'] - $selfTarget) > 0.001) {
            $this->addError('points_self', "Self evaluation total points must equal exactly {$selfTarget} (Current: {$totals['self']}).");
            return;
        }

        $activeSem = Semester::where('is_active', true)->first();
        if ($activeSem) {
            $activeSem->update([
                'student_max_points' => $studentTarget,
                'peer_max_points' => $peerTarget,
                'self_max_points' => $selfTarget,
            ]);
        }

        foreach ($this->criteriaPoints as $id => $points) {
            $val = is_numeric($points) ? (float)$points : 0.0;
            $criterion = EvaluationCriterion::findOrFail($id);
            $criterion->update([
                'max_points' => $val,
            ]);
        }

        session()->flash('status', "Evaluation criteria points and max targets updated successfully.");
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl" level="1">Evaluation Settings</flux:heading>
            <flux:subheading>Manage system academic periods, evaluation windows, and point allocations.</flux:subheading>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 rounded-lg border border-emerald-200 dark:border-emerald-800 text-sm font-medium animate-pulse">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- Left Side: Status Toggles & Criteria Points -->
        <div class="lg:col-span-7 flex flex-col gap-6">
            
            <!-- Active Evaluation Status Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon icon="bolt" class="size-5 text-indigo-500" />
                        Active Period Status
                    </h2>
                    
                    @if($this->activeSemester)
                        @php
                            $status = $this->activeSemester->evaluation_status;
                        @endphp
                        
                        @if($status === 'locked')
                            <flux:badge variant="danger" size="md">Closed (Locked)</flux:badge>
                        @elseif($status === 'scheduled')
                            <flux:badge variant="warning" size="md">Scheduled (Not started)</flux:badge>
                        @elseif($status === 'expired')
                            <flux:badge variant="danger" size="md">Expired (Closed)</flux:badge>
                        @elseif($status === 'active')
                            <flux:badge variant="success" size="md">Open & Active</flux:badge>
                        @endif
                    @else
                        <flux:badge variant="neutral" size="md">No Active Period</flux:badge>
                    @endif
                </div>

                @if($this->activeSemester)
                    <div class="space-y-6">
                        <!-- Period Info -->
                        <div class="grid grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-lg">
                            <div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 block font-medium">Academic Year</span>
                                <span class="text-base font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->activeYear->name }}</span>
                            </div>
                            <div>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 block font-medium">Semester</span>
                                <span class="text-base font-semibold text-zinc-800 dark:text-zinc-200">{{ $this->activeSemester->name }}</span>
                            </div>
                        </div>

                        <!-- Schedule Setting Section -->
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
                            <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 mb-3 flex items-center gap-1.5">
                                <flux:icon icon="clock" class="size-4 text-zinc-400" />
                                Configure Evaluation Window
                            </h3>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                                <!-- Left: Input form -->
                                <form wire:submit="saveSchedule" class="space-y-4">
                                    <div class="flex flex-col gap-4">
                                        <flux:input
                                            type="datetime-local"
                                            wire:model="startsAt"
                                            label="Start Time & Date"
                                            class="w-full"
                                            class:input="!rounded-lg"
                                        />
                                        <flux:input
                                            type="datetime-local"
                                            wire:model="endsAt"
                                            label="End Time & Date"
                                            class="w-full"
                                            class:input="!rounded-lg"
                                        />
                                    </div>
                                    @error('endsAt')
                                        <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                                    @enderror

                                    <div class="flex justify-end pt-1">
                                        <flux:button type="submit" variant="outline" size="sm" icon="calendar">
                                            Save Schedule Window
                                        </flux:button>
                                    </div>
                                </form>

                                <!-- Right: Current saved schedule display -->
                                @if($this->activeSemester->evaluation_starts_at || $this->activeSemester->evaluation_ends_at)
                                    <div class="rounded-lg border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/30 p-4 space-y-4">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wide flex items-center gap-1.5">
                                                <flux:icon icon="calendar-days" class="size-4" />
                                                Current Saved Schedule
                                            </span>
                                            @if($this->activeSemester->is_evaluation_open)
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="flex items-center gap-1 text-xs font-semibold text-zinc-400 dark:text-zinc-600 cursor-not-allowed"
                                                    title="Close evaluation first to remove schedule"
                                                >
                                                    <flux:icon icon="lock-closed" class="size-3.5" />
                                                    Locked
                                                </button>
                                            @else
                                                <button
                                                    type="button"
                                                    wire:click="confirmRemoveSchedule"
                                                    class="flex items-center gap-1 text-xs font-semibold text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition-colors"
                                                >
                                                    <flux:icon icon="trash" class="size-3.5" />
                                                    Remove
                                                </button>
                                            @endif
                                        </div>

                                        <div class="space-y-2">
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 flex-shrink-0 w-2 h-2 rounded-full bg-emerald-500 mt-1.5"></span>
                                                <div>
                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Opens</p>
                                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                                        {{ $this->activeSemester->evaluation_starts_at
                                                            ? $this->activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A')
                                                            : '—' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 flex-shrink-0 w-2 h-2 rounded-full bg-rose-500 mt-1.5"></span>
                                                <div>
                                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Closes</p>
                                                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                                                        {{ $this->activeSemester->evaluation_ends_at
                                                            ? $this->activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A')
                                                            : '—' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        @php
                                            $status = $this->activeSemester->evaluation_status;
                                        @endphp
                                        <div class="pt-1">
                                            @if($status === 'active')
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 rounded-full px-2.5 py-0.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Active Now
                                                </span>
                                            @elseif($status === 'scheduled')
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-700 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900/40 rounded-full px-2.5 py-0.5">
                                                    <flux:icon icon="clock" class="size-3" />
                                                    Scheduled
                                                </span>
                                            @elseif($status === 'expired')
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 rounded-full px-2.5 py-0.5">
                                                    <flux:icon icon="x-circle" class="size-3" />
                                                    Expired
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/40 rounded-full px-2.5 py-0.5">
                                                    <flux:icon icon="lock-closed" class="size-3" />
                                                    Locked
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/30 p-4 flex flex-col items-center justify-center text-center gap-2 min-h-[120px]">
                                        <flux:icon icon="calendar" class="size-7 text-zinc-300 dark:text-zinc-600" />
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500 font-medium">No schedule set yet.</p>
                                        <p class="text-xs text-zinc-400 dark:text-zinc-500">Set a start and end date, then click Save.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Manual Lock / Unlock Toggle -->
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                        Manual Evaluation Control
                                    </p>
                                    <p class="text-xs text-zinc-500">
                                        Opening this allows evaluations to be active during the scheduled start and end window.
                                    </p>
                                </div>
                                
                                <flux:button 
                                    variant="{{ $this->activeSemester->is_evaluation_open ? 'danger' : 'primary' }}" 
                                    wire:click="toggleEvaluation"
                                    size="sm"
                                >
                                    {{ $this->activeSemester->is_evaluation_open ? 'Close Evaluation' : 'Open Evaluation' }}
                                </flux:button>
                            </div>
                            @error('evaluation_toggle')
                                <div class="mt-3 p-3 bg-rose-50 dark:bg-rose-950/20 text-rose-800 dark:text-rose-300 rounded-lg border border-rose-100 dark:border-rose-900 text-xs font-semibold">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                @else
                    <div class="text-center py-6 text-zinc-500">
                        <flux:icon icon="exclamation-circle" class="size-8 mx-auto mb-2 text-zinc-400" />
                        <p class="text-sm">There is currently no active academic year/semester set.</p>
                        <p class="text-xs mt-1">Please select an active semester in the Academic Periods panel to the right.</p>
                    </div>
                @endif
            </div>

            <!-- Dynamic Criteria Points Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon icon="adjustments-horizontal" class="size-5 text-indigo-500" />
                        Evaluation Criteria Points
                    </h2>
                </div>

                <p class="text-xs text-zinc-500 mb-6">
                    Configure the target max points and individual part points for each evaluation target. Saving will update the settings for the active semester.
                </p>

                @php
                    $totals = $this->categoryTotals;
                    $studentTarget = is_numeric($this->studentMaxTarget) ? (float)$this->studentMaxTarget : 0.0;
                    $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
                    $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

                    $isStudentBalanced = abs($totals['student'] - $studentTarget) < 0.001;
                    $isPeerBalanced = abs($totals['peer'] - $peerTarget) < 0.001;
                    $isSelfBalanced = abs($totals['self'] - $selfTarget) < 0.001;
                    $allBalanced = $isStudentBalanced && $isPeerBalanced && $isSelfBalanced;
                @endphp

                <!-- Adjust Target Points Panel -->
                <div class="grid grid-cols-3 gap-4 bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-150 dark:border-zinc-800 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1 font-semibold">Student Max Target</label>
                        <div class="flex items-center gap-1">
                            <flux:input type="number" wire:model.live="studentMaxTarget" min="0" class="font-bold text-sm" />
                            <span class="text-xs text-zinc-400 font-semibold">pts</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1 font-semibold">Peer Max Target</label>
                        <div class="flex items-center gap-1">
                            <flux:input type="number" wire:model.live="peerMaxTarget" min="0" class="font-bold text-sm" />
                            <span class="text-xs text-zinc-400 font-semibold">pts</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-500 dark:text-zinc-400 mb-1 font-semibold">Self Max Target</label>
                        <div class="flex items-center gap-1">
                            <flux:input type="number" wire:model.live="selfMaxTarget" min="0" class="font-bold text-sm" />
                            <span class="text-xs text-zinc-400 font-semibold">pts</span>
                        </div>
                    </div>
                </div>

                <form wire:submit="savePoints" class="space-y-8">
                    <!-- Student Evaluation Points Category -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-2">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Student Evaluation</h3>
                                <flux:badge variant="{{ $isStudentBalanced ? 'success' : 'danger' }}" size="sm">
                                    {{ $totals['student'] }} / {{ $studentTarget }} pts
                                </flux:badge>
                            </div>
                            <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('student')">Add Part</flux:button>
                        </div>

                        <div class="space-y-3">
                            @foreach($this->criteria->where('evaluation_type', 'student') as $criterion)
                                <div class="flex items-center justify-between gap-4 p-3 bg-zinc-50 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800 rounded-lg hover:border-zinc-200 transition duration-150">
                                    <div class="flex-1">
                                        <span class="text-xs text-zinc-500">Part #{{ $criterion->order }}</span>
                                        <span class="font-medium text-sm text-zinc-800 dark:text-zinc-200 block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 flex items-center gap-1">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-semibold"
                                            />
                                            <span class="text-xs font-semibold text-zinc-400">pts</span>
                                        </div>
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost" 
                                            icon="trash" 
                                            wire:click="confirmDeleteCriterion({{ $criterion->id }})"
                                            class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('points_student')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Peer/Supervisor Points Category -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-2">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Peer Evaluation (Deans/PHs)</h3>
                                <flux:badge variant="{{ $isPeerBalanced ? 'success' : 'danger' }}" size="sm">
                                    {{ $totals['peer'] }} / {{ $peerTarget }} pts
                                </flux:badge>
                            </div>
                            <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('peer')">Add Part</flux:button>
                        </div>

                        <div class="space-y-3">
                            @foreach($this->criteria->where('evaluation_type', 'peer') as $criterion)
                                <div class="flex items-center justify-between gap-4 p-3 bg-zinc-50 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800 rounded-lg hover:border-zinc-200 transition duration-150">
                                    <div class="flex-1">
                                        <span class="text-xs text-zinc-500">Part #{{ $criterion->order }}</span>
                                        <span class="font-medium text-sm text-zinc-800 dark:text-zinc-200 block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 flex items-center gap-1">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-semibold"
                                            />
                                            <span class="text-xs font-semibold text-zinc-400">pts</span>
                                        </div>
                                        <flux:button 
                                            size="sm" 
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

                    <!-- Self Evaluation Points Category -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-2">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Self Evaluation</h3>
                                <flux:badge variant="{{ $isSelfBalanced ? 'success' : 'danger' }}" size="sm">
                                    {{ $totals['self'] }} / {{ $selfTarget }} pts
                                </flux:badge>
                            </div>
                            <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('self')">Add Part</flux:button>
                        </div>

                        <div class="space-y-3">
                            @foreach($this->criteria->where('evaluation_type', 'self') as $criterion)
                                <div class="flex items-center justify-between gap-4 p-3 bg-zinc-50 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800 rounded-lg hover:border-zinc-200 transition duration-150">
                                    <div class="flex-1">
                                        <span class="text-xs text-zinc-500">Part #{{ $criterion->order }}</span>
                                        <span class="font-medium text-sm text-zinc-800 dark:text-zinc-200 block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 flex items-center gap-1">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-semibold"
                                            />
                                            <span class="text-xs font-semibold text-zinc-400">pts</span>
                                        </div>
                                        <flux:button 
                                            size="sm" 
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

                    <div class="flex items-center justify-between border-t border-zinc-150 dark:border-zinc-800 pt-4 mt-6">
                        <div>
                            <span class="text-xs text-zinc-500 font-medium">Scoring Configuration Status</span>
                            <span class="text-sm font-bold block {{ $allBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $allBalanced ? 'All categories balanced' : 'Please resolve point balancing' }}
                            </span>
                        </div>

                        <flux:button 
                            type="submit" 
                            variant="primary" 
                            :disabled="!$allBalanced"
                        >
                            Save Points
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Side: Academic Periods Listing & Actions -->
        <div class="lg:col-span-5 flex flex-col gap-6">
            
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon icon="calendar" class="size-5 text-indigo-500" />
                        Academic Periods
                    </h2>
                </div>

                <div class="flex gap-2 mb-6">
                    <flux:button size="sm" variant="outline" class="flex-1" icon="plus" wire:click="$set('showYearModal', true)">Add Year</flux:button>
                    <flux:button size="sm" variant="outline" class="flex-1" icon="plus" wire:click="$set('showSemModal', true)">Add Semester</flux:button>
                </div>

                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                    @forelse($this->academicYears as $year)
                        <div class="border border-zinc-100 dark:border-zinc-800 rounded-lg overflow-hidden shadow-xs">
                            <!-- Year Header -->
                            <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 p-3 border-b border-zinc-100 dark:border-zinc-800">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm text-zinc-950 dark:text-zinc-150">A.Y. {{ $year->name }}</span>
                                    @if($year->is_active)
                                        <flux:badge variant="info" size="sm">Active</flux:badge>
                                    @endif
                                </div>
                                
                                @if(!$year->is_active)
                                    <flux:button size="xs" variant="ghost" wire:click="setActiveYear({{ $year->id }})">Activate</flux:button>
                                @endif
                            </div>

                            <!-- Semesters List -->
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse($year->semesters as $sem)
                                    <div class="flex items-center justify-between p-3 pl-6 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">{{ $sem->name }}</span>
                                            @if($sem->is_active)
                                                <flux:badge variant="info" size="sm">Active</flux:badge>
                                            @endif
                                            @php
                                                $semStatus = $sem->evaluation_status;
                                            @endphp
                                            @if($semStatus === 'active')
                                                <flux:badge variant="success" size="sm">Open</flux:badge>
                                            @endif
                                        </div>

                                        @if(!$sem->is_active)
                                            <flux:button size="xs" variant="ghost" wire:click="setActiveSemester({{ $sem->id }})">
                                                Activate
                                            </flux:button>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-3 pl-6 text-xs text-zinc-500 italic">No semesters added yet.</div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-sm">No Academic Years created.</div>
                    @endforelse
                </div>
            </div>

            <!-- Departments Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon icon="academic-cap" class="size-5 text-indigo-500" />
                        Manage Departments
                    </h2>
                    <flux:button size="sm" variant="outline" icon="plus" wire:click="openDeptModal()">Add Dept</flux:button>
                </div>

                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($this->departments as $dept)
                        <div class="p-3 bg-zinc-50 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800 rounded-lg flex items-center justify-between hover:border-zinc-200 transition duration-150">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded">{{ $dept->code }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-250">{{ $dept->name }}</span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    Dean: <span class="font-medium text-zinc-750 dark:text-zinc-300">{{ $dept->dean ? $dept->dean->full_name : 'Not assigned' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="pencil" 
                                    wire:click="openDeptModal({{ $dept->id }})"
                                    class="text-zinc-650 dark:text-zinc-400"
                                />
                                <flux:button 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="trash" 
                                    wire:click="confirmDeleteDept({{ $dept->id }})"
                                    class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                />
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-sm">No departments created.</div>
                    @endforelse
                </div>
            </div>

            <!-- Programs Card -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        <flux:icon icon="book-open" class="size-5 text-indigo-500" />
                        Manage Programs
                    </h2>
                    <flux:button size="sm" variant="outline" icon="plus" wire:click="openProgModal()">Add Prog</flux:button>
                </div>

                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    @forelse($this->programs as $prog)
                        <div class="p-3 bg-zinc-50 dark:bg-zinc-800/20 border border-zinc-100 dark:border-zinc-800 rounded-lg flex items-center justify-between hover:border-zinc-200 transition duration-150">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded">{{ $prog->code }}</span>
                                    <span class="font-semibold text-sm text-zinc-800 dark:text-zinc-250">{{ $prog->name }}</span>
                                </div>
                                <div class="text-xs text-zinc-500 mt-1">
                                    Dept: <span class="font-medium text-zinc-700 dark:text-zinc-300 mr-2">{{ $prog->department?->code ?: 'None' }}</span>
                                    Head: <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $prog->programHead ? $prog->programHead->full_name : 'Not assigned' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="pencil" 
                                    wire:click="openProgModal({{ $prog->id }})"
                                    class="text-zinc-650 dark:text-zinc-400"
                                />
                                <flux:button 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="trash" 
                                    wire:click="confirmDeleteProg({{ $prog->id }})"
                                    class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                />
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-400 text-sm">No programs created.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Create Academic Year Modal -->
    @if($showYearModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">Create Academic Year</flux:heading>
            
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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">Create Semester</flux:heading>
            
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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">Create Criterion</flux:heading>
            
            <form wire:submit="createCriterion" class="space-y-4">
                <flux:input 
                    wire:model="newCriterionName" 
                    label="Part Name" 
                    placeholder="e.g. Part 1: Course Prep" 
                    required 
                />
                @error('newCriterionName')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:input 
                    type="number"
                    wire:model="newCriterionMaxPoints" 
                    label="Max Points" 
                    min="0"
                    required 
                />

                <flux:select wire:model="newCriterionType" label="Evaluation Target Type" required>
                    <flux:select.option value="student">Student Evaluation</flux:select.option>
                    <flux:select.option value="peer">Peer Evaluation (Deans/PHs)</flux:select.option>
                    <flux:select.option value="self">Self Evaluation</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showCriterionModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Create</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Create/Edit Department Modal -->
    @if($showDeptModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">{{ $deptId ? 'Edit Department' : 'Create Department' }}</flux:heading>
            
            <form wire:submit="saveDept" class="space-y-4">
                <flux:input 
                    wire:model="deptCode" 
                    label="Department Code" 
                    placeholder="e.g. CCS" 
                    required 
                />
                @error('deptCode')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:input 
                    wire:model="deptName" 
                    label="Department Name" 
                    placeholder="e.g. College of Computer Studies" 
                    required 
                />
                @error('deptName')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:select wire:model="deptDeanId" label="Dean / Head (Optional)">
                    <flux:select.option value="">Select Dean</flux:select.option>
                    @foreach($this->deans as $dean)
                        <flux:select.option value="{{ $dean->id }}">{{ $dean->full_name }} ({{ $dean->employee_number }})</flux:select.option>
                    @endforeach
                </flux:select>
                @error('deptDeanId')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showDeptModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">{{ $deptId ? 'Save Changes' : 'Create' }}</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Department Confirmation Modal -->
    @if($showDeleteDeptModal && $deletingDept)
    <x-confirmation-modal 
        title="Delete Department" 
        on-confirm="deleteDept" 
        on-cancel="$set('showDeleteDeptModal', false)" 
    >
        Are you sure you want to delete this department? This action cannot be undone and will cascade to remove associated programs and unlink assigned staff/deans.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department Code</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingDept->code }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Dean / Head</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingDept->dean ? $deletingDept->dean->full_name : 'Not assigned' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingDept->name }}</span>
                </div>
            </div>
        </x-slot:details>
    </x-confirmation-modal>
    @endif

    <!-- Create/Edit Program Modal -->
    @if($showProgModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-sm border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">{{ $progId ? 'Edit Program' : 'Create Program' }}</flux:heading>
            
            <form wire:submit="saveProg" class="space-y-4">
                <flux:input 
                    wire:model="progCode" 
                    label="Program Code" 
                    placeholder="e.g. BSCS" 
                    required 
                />
                @error('progCode')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:input 
                    wire:model="progName" 
                    label="Program Name" 
                    placeholder="e.g. BS Computer Science" 
                    required 
                />
                @error('progName')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:select wire:model="progDeptId" label="Department / College" required>
                    <flux:select.option value="">Select Department</flux:select.option>
                    @foreach($this->departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error('progDeptId')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <flux:select wire:model="progHeadId" label="Program Head / Lead (Optional)">
                    <flux:select.option value="">Select Program Head</flux:select.option>
                    @foreach($this->programHeads as $head)
                        <flux:select.option value="{{ $head->id }}">{{ $head->full_name }} ({{ $head->employee_number }})</flux:select.option>
                    @endforeach
                </flux:select>
                @error('progHeadId')
                    <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                @enderror

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showProgModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">{{ $progId ? 'Save Changes' : 'Create' }}</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Program Confirmation Modal -->
    @if($showDeleteProgModal && $deletingProg)
    <x-confirmation-modal 
        title="Delete Program" 
        on-confirm="deleteProg" 
        on-cancel="$set('showDeleteProgModal', false)" 
    >
        Are you sure you want to delete this program? This action cannot be undone and will cascade to affect student program links.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Code</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProg->code }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Head</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProg->programHead ? $deletingProg->programHead->full_name : 'Not assigned' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProg->name }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProg->department?->code }} - {{ $deletingProg->department?->name }}</span>
                </div>
            </div>
        </x-slot:details>
    </x-confirmation-modal>
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
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Evaluation Target Type</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150 capitalize">{{ $deletingCriterion->evaluation_type }} Evaluation</span>
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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 w-full max-w-sm mx-4 p-6 space-y-5">
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
        This will permanently clear the saved evaluation window. The <span class="font-semibold text-rose-600 dark:text-rose-400 font-semibold">Open Evaluation</span> button will also stop working until a new schedule is set.

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
