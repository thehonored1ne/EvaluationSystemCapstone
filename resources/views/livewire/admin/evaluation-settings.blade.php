<?php

use Livewire\Volt\Component;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\EvaluationCriterion;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
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
            return;
        }

        $activeSem->is_evaluation_open = !$activeSem->is_evaluation_open;
        $activeSem->save();

        $status = $activeSem->is_evaluation_open ? 'unlocked' : 'locked';
        session()->flash('status', "Manual override: Evaluations have been {$status}.");
    }

    // Save evaluation starts_at and ends_at schedule
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

        $activeSem->update([
            'evaluation_starts_at' => $this->startsAt ? \Illuminate\Support\Carbon::parse($this->startsAt) : null,
            'evaluation_ends_at' => $this->endsAt ? \Illuminate\Support\Carbon::parse($this->endsAt) : null,
        ]);

        session()->flash('status', "Evaluation schedule dates updated successfully.");
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

    // Delete Evaluation Criterion
    public function deleteCriterion($id)
    {
        $criterion = EvaluationCriterion::findOrFail($id);
        $criterion->delete();

        $this->loadPoints();
        session()->flash('status', "Evaluation criterion deleted successfully.");
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
                            <flux:badge variant="danger" size="md">Manual locked</flux:badge>
                        @elseif($status === 'scheduled')
                            <flux:badge variant="warning" size="md">Scheduled (Not started)</flux:badge>
                        @elseif($status === 'expired')
                            <flux:badge variant="danger" size="md">Time expired</flux:badge>
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

                            <form wire:submit="saveSchedule" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <flux:input 
                                            type="datetime-local" 
                                            wire:model="startsAt" 
                                            label="Start Time & Date" 
                                        />
                                    </div>
                                    <div>
                                        <flux:input 
                                            type="datetime-local" 
                                            wire:model="endsAt" 
                                            label="End Time & Date" 
                                        />
                                    </div>
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
                        </div>

                        <!-- Manual Lock / Unlock Toggle -->
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">
                                    Manual System Lock override
                                </p>
                                <p class="text-xs text-zinc-500">
                                    Disabling this locks the evaluation system immediately, ignoring any scheduled start/end windows.
                                </p>
                            </div>
                            
                            <flux:button 
                                variant="{{ $this->activeSemester->is_evaluation_open ? 'danger' : 'primary' }}" 
                                wire:click="toggleEvaluation"
                                size="sm"
                            >
                                {{ $this->activeSemester->is_evaluation_open ? 'Disable System' : 'Enable System' }}
                            </flux:button>
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
                                            wire:click="deleteCriterion({{ $criterion->id }})"
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
                                            wire:click="deleteCriterion({{ $criterion->id }})"
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
                                            wire:click="deleteCriterion({{ $criterion->id }})"
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
</div>
