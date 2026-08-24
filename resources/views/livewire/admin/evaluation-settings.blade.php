<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.evaluation-settings-skeleton');
    }

    // Criterion Delete & Edit
    public bool $showDeleteCriterionModal = false;
    public ?EvaluationCriterion $deletingCriterion = null;
    public bool $showEditCriterionModal = false;
    public ?EvaluationCriterion $editingCriterion = null;
    public string $editCriterionName = '';
    public string $editCriterionMaxPoints = '';

    // Unified Academic Period creation
    public bool $showPeriodModal = false;
    public string $periodYearMode = 'existing'; // 'existing', 'new'
    public string $periodYearId = '';
    public string $periodNewYearName = '';
    public string $periodSemesterName = '1st Semester';

    // Academic Year creation (legacy compatibility)
    public string $newYearName = '';
    public bool $showYearModal = false;

    // Semester creation (legacy compatibility)
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

    // Dynamic Max score targets config & weights
    public string $weightsReportTab = 'teaching_effectiveness'; // 'teaching_effectiveness', 'global_targets'
    public string $overallMaxTarget = '200';
    public string $maxWeightPercent = '100'; // Editable total weight percentage sum
    
    public string $studentWeightTarget = '40';
    public string $deanWeightTarget = '20';
    public string $phDhWeightTarget = '20';
    public string $peerWeightTarget = '15';
    public string $selfWeightTarget = '5';
    public string $superiorWeightTarget = '20';

    public string $upwardStudentMaxTarget = '80';
    public string $selfMaxTarget = '10';
    public string $deanMaxTarget = '40';
    public string $departmentHeadMaxTarget = '50';
    public string $programHeadMaxTarget = '40';
    public string $peerMaxTarget = '30';
    public string $downwardMaxTarget = '40';
    public string $staffMaxTarget = '10';
    public string $upwardEmployeeMaxTarget = '30';

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
            $this->overallMaxTarget = (string)(float)($activeSem->overall_max_points ?? 200);
            $this->studentWeightTarget = (string)(float)($activeSem->student_weight ?? 40);
            $this->deanWeightTarget = (string)(float)($activeSem->dean_weight ?? 20);
            $this->phDhWeightTarget = (string)(float)($activeSem->ph_dh_weight ?? 20);
            $this->peerWeightTarget = (string)(float)($activeSem->peer_weight ?? 15);
            $this->selfWeightTarget = (string)(float)($activeSem->self_weight ?? 5);
            $this->superiorWeightTarget = (string)(float)($activeSem->superior_weight ?? 20);

            $this->upwardStudentMaxTarget = (string)(float)($activeSem->upward_student_max_points ?? 80);
            $this->upwardEmployeeMaxTarget = (string)(float)($activeSem->upward_employee_max_points ?? 40);
            $this->deanMaxTarget = (string)(float)($activeSem->dean_max_points ?? 40);
            $this->departmentHeadMaxTarget = (string)(float)($activeSem->department_head_max_points ?? 40);
            $this->programHeadMaxTarget = (string)(float)($activeSem->program_head_max_points ?? 40);
            $this->downwardMaxTarget = (string)(float)($activeSem->downward_max_points ?? 40);
            $this->peerMaxTarget = (string)(float)($activeSem->peer_max_points ?? 30);
            $this->selfMaxTarget = (string)(float)($activeSem->self_max_points ?? 10);
            $this->staffMaxTarget = (string)(float)($activeSem->staff_max_points ?? 10);
            
            $this->startsAt = $activeSem->evaluation_starts_at ? $activeSem->evaluation_starts_at->format('Y-m-d\TH:i') : '';
            $this->endsAt = $activeSem->evaluation_ends_at ? $activeSem->evaluation_ends_at->format('Y-m-d\TH:i') : '';
        }
    }

    public function resetToDefaultWeights(): void
    {
        $this->overallMaxTarget = '200';
        $this->maxWeightPercent = '100';
        $this->studentWeightTarget = '40';
        $this->deanWeightTarget = '20';
        $this->phDhWeightTarget = '20';
        $this->peerWeightTarget = '15';
        $this->selfWeightTarget = '5';
        $this->superiorWeightTarget = '20';

        $this->upwardStudentMaxTarget = '80';
        $this->deanMaxTarget = '40';
        $this->programHeadMaxTarget = '40';
        $this->peerMaxTarget = '30';
        $this->selfMaxTarget = '10';

        session()->flash('status', 'Weights and point targets restored to standard GRC preset (40% / 20% / 20% / 15% / 5%).');
    }

    public function getAcademicYearsProperty()
    {
        return AcademicYear::with('semesters')->orderBy('name', 'desc')->get();
    }

    public function getSemestersPaginatedProperty()
    {
        return Semester::with('academicYear')
            ->join('academic_years', 'semesters.academic_year_id', '=', 'academic_years.id')
            ->select('semesters.*')
            ->orderBy('academic_years.name', 'desc')
            ->orderBy('semesters.name', 'asc')
            ->paginate(10);
    }

    public function openAddPeriodModal()
    {
        $this->reset(['periodYearId', 'periodNewYearName']);
        $this->periodYearMode = AcademicYear::count() > 0 ? 'existing' : 'new';
        $this->periodYearId = (string)(AcademicYear::where('is_active', true)->first()?->id ?? AcademicYear::first()?->id ?? '');
        $this->periodSemesterName = '1st Semester';
        $this->showPeriodModal = true;
    }

    public function saveAcademicPeriod()
    {
        if ($this->periodYearMode === 'new') {
            $this->validate([
                'periodNewYearName' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/', 'unique:academic_years,name'],
                'periodSemesterName' => 'required|string|max:50',
            ], [
                'periodNewYearName.regex' => 'Academic year must be formatted as YYYY-YYYY (e.g. 2026-2027).',
            ]);

            $year = AcademicYear::create([
                'name' => $this->periodNewYearName,
                'is_active' => false,
            ]);
            $yearId = $year->id;
        } else {
            $this->validate([
                'periodYearId' => 'required|exists:academic_years,id',
                'periodSemesterName' => 'required|string|max:50',
            ]);
            $yearId = (int)$this->periodYearId;
        }

        // Check if semester already exists for this year
        $existing = Semester::where('academic_year_id', $yearId)
            ->where('name', $this->periodSemesterName)
            ->first();

        if ($existing) {
            $this->addError('periodSemesterName', 'This semester already exists under the selected academic year.');
            return;
        }

        Semester::create([
            'academic_year_id' => $yearId,
            'name' => $this->periodSemesterName,
            'is_active' => false,
            'is_evaluation_open' => false,
        ]);

        $this->showPeriodModal = false;
        $this->resetPage();

        \Flux::toast(
            heading: 'Academic Period Created',
            text: 'New academic period added successfully.',
            variant: 'success'
        );
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
            'student' => 0.0,
            'upward_student' => 0.0,
            'dean' => 0.0,
            'program_head' => 0.0,
            'department_head' => 0.0,
            'ph_dh' => 0.0,
            'downward' => 0.0,
            'peer' => 0.0,
            'self' => 0.0,
            'superior' => 0.0,
            'upward_employee' => 0.0,
        ];
        $criteria = EvaluationCriterion::all();
        foreach ($criteria as $criterion) {
            $val = $this->criteriaPoints[$criterion->id] ?? 0.0;
            $type = $criterion->evaluation_type;
            if (isset($totals[$type])) {
                $totals[$type] += is_numeric($val) ? (float)$val : 0.0;
            }
        }

        // Combine aliased totals for legacy criteria mapping
        $totals['combined_student'] = $totals['student'] + $totals['upward_student'];
        $totals['combined_program_head'] = $totals['program_head'] + $totals['ph_dh'];
        $totals['combined_department_head'] = $totals['department_head'] + $totals['downward'];
        $totals['combined_ph_dh'] = $totals['combined_program_head'] + $totals['combined_department_head'];
        $totals['combined_superior'] = $totals['superior'] + $totals['upward_employee'];

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
            $peerTarget = is_numeric($this->peerMaxTarget) ? (float)$this->peerMaxTarget : 0.0;
            $selfTarget = is_numeric($this->selfMaxTarget) ? (float)$this->selfMaxTarget : 0.0;

            $isUpwardStudentBalanced = abs($totals['combined_student'] - $upwardStudentTarget) < 0.05 || $totals['combined_student'] > 0;
            $isPeerBalanced = abs($totals['peer'] - $peerTarget) < 0.05 || $totals['peer'] > 0;
            $isSelfBalanced = abs($totals['self'] - $selfTarget) < 0.05 || $totals['self'] > 0;

            if (!$isUpwardStudentBalanced || !$isPeerBalanced || !$isSelfBalanced) {
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
        $this->newCriterionType = match ($type) {
            'upward_student' => 'student',
            'downward' => 'department_head',
            'ph_dh' => 'program_head',
            'upward_employee' => 'superior',
            default => $type,
        };
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
            'newCriterionType' => 'required|in:student,dean,program_head,department_head,ph_dh,peer,self,superior',
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

    public function openEditCriterionModal($id)
    {
        $this->editingCriterion = EvaluationCriterion::findOrFail($id);
        $this->editCriterionName = $this->editingCriterion->name;
        $this->editCriterionMaxPoints = (string)$this->editingCriterion->max_points;
        $this->showEditCriterionModal = true;
    }

    public function updateCriterion()
    {
        $this->validate([
            'editCriterionName' => 'required|string|max:255',
            'editCriterionMaxPoints' => 'required|numeric|min:0|max:1000',
        ]);

        if ($this->editingCriterion) {
            $exists = EvaluationCriterion::where('evaluation_type', $this->editingCriterion->evaluation_type)
                ->where('name', $this->editCriterionName)
                ->where('id', '!=', $this->editingCriterion->id)
                ->exists();

            if ($exists) {
                $this->addError('editCriterionName', 'This criterion name already exists for this evaluation type.');
                return;
            }

            $this->editingCriterion->update([
                'name' => $this->editCriterionName,
                'max_points' => (float)$this->editCriterionMaxPoints,
            ]);

            $this->criteriaPoints[$this->editingCriterion->id] = (float)$this->editCriterionMaxPoints;

            $this->showEditCriterionModal = false;
            $this->editingCriterion = null;
            $this->loadPoints();
            session()->flash('status', "Questionnaire part updated successfully.");
        }
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
        $this->resetErrorBag();
        $totals = $this->categoryTotals;
        $maxWeight = is_numeric($this->maxWeightPercent) && (float)$this->maxWeightPercent > 0 ? (float)$this->maxWeightPercent : 100.0;
        $overall = is_numeric($this->overallMaxTarget) && (float)$this->overallMaxTarget > 0 ? (float)$this->overallMaxTarget : 200.0;

        if ($this->weightsReportTab === 'teaching_effectiveness') {
            // Teaching Effectiveness 5 Categories: Student, Dean, Program Head, Peer, Self
            $wStudent = (float)$this->studentWeightTarget;
            $wDean = (float)$this->deanWeightTarget;
            $wPhDh = (float)$this->phDhWeightTarget;
            $wPeer = (float)$this->peerWeightTarget;
            $wSelf = (float)$this->selfWeightTarget;

            $teWeights = $wStudent + $wDean + $wPhDh + $wPeer + $wSelf;
            if (abs($teWeights - $maxWeight) > 0.05) {
                $this->addError('total_weights', "Teaching Effectiveness percentage weights must equal exactly {$maxWeight}% (Current sum: {$teWeights}%).");
                return;
            }

            $studentTarget = round(($wStudent / $maxWeight) * $overall, 2);
            $deanTarget = round(($wDean / $maxWeight) * $overall, 2);
            $phDhTarget = round(($wPhDh / $maxWeight) * $overall, 2);
            $peerTarget = round(($wPeer / $maxWeight) * $overall, 2);
            $selfTarget = round(($wSelf / $maxWeight) * $overall, 2);

            if (abs(($totals['combined_student'] ?? 0.0) - $studentTarget) > 0.05) {
                $this->addError('points_student', "Student Evaluation total criteria points must equal exactly {$studentTarget} pts (Current: {$totals['combined_student']}).");
                return;
            }

            if (abs(($totals['dean'] ?? 0.0) - $deanTarget) > 0.05 && EvaluationCriterion::where('evaluation_type', 'dean')->count() > 0) {
                $this->addError('points_dean', "Dean Evaluation total criteria points must equal exactly {$deanTarget} pts (Current: {$totals['dean']}).");
                return;
            }

            if (abs(($totals['combined_program_head'] ?? 0.0) - $phDhTarget) > 0.05 && EvaluationCriterion::whereIn('evaluation_type', ['program_head', 'ph_dh'])->count() > 0) {
                $this->addError('points_ph_dh', "Program Head Evaluation total criteria points must equal exactly {$phDhTarget} pts (Current: {$totals['combined_program_head']}).");
                return;
            }

            if (abs(($totals['peer'] ?? 0.0) - $peerTarget) > 0.05 && EvaluationCriterion::where('evaluation_type', 'peer')->count() > 0) {
                $this->addError('points_peer', "Peer Evaluation total criteria points must equal exactly {$peerTarget} pts (Current: {$totals['peer']}).");
                return;
            }

            if (abs(($totals['self'] ?? 0.0) - $selfTarget) > 0.05 && EvaluationCriterion::where('evaluation_type', 'self')->count() > 0) {
                $this->addError('points_self', "Self Evaluation total criteria points must equal exactly {$selfTarget} pts (Current: {$totals['self']}).");
                return;
            }

            $activeSem = Semester::where('is_active', true)->first();
            if ($activeSem) {
                $activeSem->update([
                    'overall_max_points' => $overall,
                    'student_weight' => $wStudent,
                    'dean_weight' => $wDean,
                    'ph_dh_weight' => $wPhDh,
                    'peer_weight' => $wPeer,
                    'self_weight' => $wSelf,
                    'upward_student_max_points' => $studentTarget,
                    'dean_max_points' => $deanTarget,
                    'program_head_max_points' => $phDhTarget,
                    'downward_max_points' => $phDhTarget,
                    'peer_max_points' => $peerTarget,
                    'self_max_points' => $selfTarget,
                ]);
            }
        } else {
            // Global All Categories
            $activeSem = Semester::where('is_active', true)->first();
            if ($activeSem) {
                $activeSem->update([
                    'overall_max_points' => $overall,
                    'student_weight' => (float)$this->studentWeightTarget,
                    'dean_weight' => (float)$this->deanWeightTarget,
                    'ph_dh_weight' => (float)$this->phDhWeightTarget,
                    'peer_weight' => (float)$this->peerWeightTarget,
                    'self_weight' => (float)$this->selfWeightTarget,
                    'superior_weight' => (float)$this->superiorWeightTarget,
                    'upward_student_max_points' => (float)$this->upwardStudentMaxTarget,
                    'upward_employee_max_points' => (float)$this->upwardEmployeeMaxTarget,
                    'dean_max_points' => (float)$this->deanMaxTarget,
                    'department_head_max_points' => (float)$this->departmentHeadMaxTarget,
                    'program_head_max_points' => (float)$this->programHeadMaxTarget,
                    'downward_max_points' => (float)$this->downwardMaxTarget,
                    'peer_max_points' => (float)$this->peerMaxTarget,
                    'self_max_points' => (float)$this->selfMaxTarget,
                    'staff_max_points' => (float)$this->staffMaxTarget,
                ]);
            }
        }

        foreach ($this->criteriaPoints as $id => $points) {
            $val = is_numeric($points) ? (float)$points : 0.0;
            $criterion = EvaluationCriterion::find($id);
            if ($criterion) {
                $criterion->update([
                    'max_points' => $val,
                ]);
            }
        }

        $this->loadPoints();

        session()->flash('status', "Evaluation criteria score points and dynamic max targets updated successfully.");
        \Flux::toast(
            heading: 'Settings Saved',
            text: 'Evaluation criteria score points and dynamic max targets updated successfully.',
            variant: 'success'
        );
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex justify-between items-center w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Settings</flux:heading>
        </div>
    </div>

    <!-- Quick Navigation Anchor Bar (Static) -->
    <div class="bg-white dark:bg-zinc-900 p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-wrap items-center gap-2 sm:gap-3">
        <span class="text-xs font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 w-full sm:w-auto">Quick Navigation:</span>
        <a href="#schedule-section" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#9b0000] hover:text-white dark:hover:bg-[#9b0000] transition-colors flex items-center gap-1.5">
            
            Schedule Window
        </a>
        <a href="#weights-section" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#9b0000] hover:text-white dark:hover:bg-[#9b0000] transition-colors flex items-center gap-1.5">
            
            Evaluation Weights & Criteria
        </a>
        <a href="#academic-periods-section" class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-[#9b0000] hover:text-white dark:hover:bg-[#9b0000] transition-colors flex items-center gap-1.5">
            
            Academic Years & Semesters
        </a>
    </div>

    @if (session()->has('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 rounded-xl border border-emerald-200 dark:border-emerald-800 text-sm font-medium">
            {{ session('status') }}
        </div>
    @endif

    <!-- SECTION 1: Active Evaluation Status & Toggle Control Banner -->
    <div id="status-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4 sm:gap-6 w-full">
        <div class="flex items-start sm:items-center gap-3 sm:gap-4">

            <div>
                <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                    <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">System Access Status</h2>
                    @if($this->activeSemester)
                        @php $status = $this->activeSemester->evaluation_status; @endphp
                        @if($status === 'active')
                            <flux:badge variant="success" size="sm" class="font-bold shrink-0">Open & Active</flux:badge>
                        @elseif($status === 'scheduled')
                            <flux:badge variant="warning" size="sm" class="font-bold shrink-0">Scheduled</flux:badge>
                        @elseif($status === 'expired')
                            <flux:badge variant="danger" size="sm" class="font-bold shrink-0">Expired</flux:badge>
                        @else
                            <flux:badge variant="danger" size="sm" class="font-bold shrink-0">Closed / Locked</flux:badge>
                        @endif
                    @else
                        <flux:badge variant="neutral" size="sm" class="shrink-0">No Active Semester</flux:badge>
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
            <div class="flex flex-col items-stretch sm:items-end gap-2 w-full md:w-auto shrink-0">
                <flux:button 
                    variant="{{ $this->activeSemester->is_evaluation_open ? 'danger' : 'primary' }}" 
                    wire:click="toggleEvaluation"
                    class="w-full sm:w-auto font-bold justify-center"
                >
                    {{ $this->activeSemester->is_evaluation_open ? 'Close Evaluation System' : 'Open Evaluation System' }}
                </flux:button>
                @error('evaluation_toggle')
                    <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        @endif
    </div>

    <!-- SECTION 2: Evaluation Window Schedule -->
    <div id="schedule-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-4 sm:gap-6 w-full">
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    
                    Set Evaluation Schedule Dates
                </h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Automate system opening and closing date/time windows for active evaluations.</p>
            </div>
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
                    <flux:button type="submit" variant="outline" size="sm" icon="calendar" class="w-full sm:w-auto justify-center">
                        Save Schedule Window
                    </flux:button>
                </div>
            </form>

            <!-- Current Saved Schedule Banner -->
            @if($this->activeSemester->evaluation_starts_at || $this->activeSemester->evaluation_ends_at)
                <div class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50/50 dark:bg-indigo-950/30 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase tracking-wide flex items-center gap-1.5">
                            <flux:icon icon="calendar-days" class="size-4 shrink-0" />
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
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

    @php
        $totals = $this->categoryTotals;
        $overall = is_numeric($this->overallMaxTarget) && (float)$this->overallMaxTarget > 0 ? (float)$this->overallMaxTarget : 200.0;
        $maxWeight = is_numeric($this->maxWeightPercent) && (float)$this->maxWeightPercent > 0 ? (float)$this->maxWeightPercent : 100.0;
        
        $wStudent = is_numeric($this->studentWeightTarget) ? (float)$this->studentWeightTarget : 40.0;
        $wDean = is_numeric($this->deanWeightTarget) ? (float)$this->deanWeightTarget : 20.0;
        $wPhDh = is_numeric($this->phDhWeightTarget) ? (float)$this->phDhWeightTarget : 20.0;
        $wPeer = is_numeric($this->peerWeightTarget) ? (float)$this->peerWeightTarget : 15.0;
        $wSelf = is_numeric($this->selfWeightTarget) ? (float)$this->selfWeightTarget : 5.0;
        $wSuperior = is_numeric($this->superiorWeightTarget) ? (float)$this->superiorWeightTarget : 20.0;

        // Teaching effectiveness weights sum
        $teWeights = $wStudent + $wDean + $wPhDh + $wPeer + $wSelf;
        $isTeBalanced = abs($teWeights - $maxWeight) < 0.05;

        // Dynamic targets computed against maxWeight & overall scale
        $tStudent = round(($wStudent / $maxWeight) * $overall, 2);
        $tDean = round(($wDean / $maxWeight) * $overall, 2);
        $tPhDh = round(($wPhDh / $maxWeight) * $overall, 2);
        $tPeer = round(($wPeer / $maxWeight) * $overall, 2);
        $tSelf = round(($wSelf / $maxWeight) * $overall, 2);
        $tSuperior = round(($wSuperior / $maxWeight) * $overall, 2);

        $isStudentBalanced = abs(($totals['combined_student'] ?? 0.0) - $tStudent) < 0.05;
        $isDeanBalanced = abs(($totals['dean'] ?? 0.0) - $tDean) < 0.05 || EvaluationCriterion::where('evaluation_type', 'dean')->count() === 0;
        $isPhBalanced = abs(($totals['combined_program_head'] ?? 0.0) - $tPhDh) < 0.05 || EvaluationCriterion::whereIn('evaluation_type', ['program_head', 'ph_dh'])->count() === 0;
        $isDhBalanced = abs(($totals['combined_department_head'] ?? 0.0) - $tPhDh) < 0.05 || EvaluationCriterion::whereIn('evaluation_type', ['department_head', 'downward'])->count() === 0;
        $isPeerBalanced = abs(($totals['peer'] ?? 0.0) - $tPeer) < 0.05;
        $isSelfBalanced = abs(($totals['self'] ?? 0.0) - $tSelf) < 0.05;
        $isSuperiorBalanced = abs(($totals['combined_superior'] ?? 0.0) - $tSuperior) < 0.05 || EvaluationCriterion::whereIn('evaluation_type', ['superior', 'upward_employee'])->count() === 0;

        $allBalanced = $isTeBalanced && $isStudentBalanced && $isDeanBalanced && $isPhBalanced && $isPeerBalanced && $isSelfBalanced;
    @endphp

    <!-- SECTION 3: UNIFIED Evaluation Weights & Questionnaire Parts Setup -->
    <div id="weights-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-6 w-full border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
        
        <!-- Section Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    
                    Evaluation Weights & Questionnaire Parts Allocation
                </h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 leading-relaxed">
                    Configure overall scale, target percentage weights, and specific questionnaire criteria parts directly for each category.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:gap-3 self-start sm:self-auto">
                <flux:badge variant="{{ $isTeBalanced ? 'info' : 'danger' }}" size="sm" class="font-bold shrink-0">
                    Weights: {{ $teWeights }}% / {{ $maxWeight }}%
                </flux:badge>
                <flux:badge variant="{{ $allBalanced ? 'success' : 'warning' }}" size="sm" class="font-bold shrink-0">
                    {{ $allBalanced ? 'Report Formula Balanced' : 'Action Required' }}
                </flux:badge>
            </div>
        </div>

        <!-- Tab Navigation Switcher -->
        <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-2 md:gap-4 overflow-x-auto pb-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <button 
                type="button"
                wire:click="$set('weightsReportTab', 'teaching_effectiveness')" 
                class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $weightsReportTab === 'teaching_effectiveness' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon icon="academic-cap" class="size-4 shrink-0" />
                Individual Teaching Effectiveness
            </button>
            <button 
                type="button"
                wire:click="$set('weightsReportTab', 'global_targets')" 
                class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $weightsReportTab === 'global_targets' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <flux:icon icon="adjustments-vertical" class="size-4 shrink-0" />
                All Categories & Extended Roles
            </button>
        </div>

        <form wire:submit="savePoints" class="space-y-6">

            <!-- TAB 1: Individual Teaching Effectiveness (Faculty 360°) -->
            @if($weightsReportTab === 'teaching_effectiveness')
                
                <!-- Top Controls: Overall Scale, Target Sum %, & Reset Preset -->
                <div class="bg-zinc-50 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700/80 rounded-xl p-4 sm:p-5 shadow-2xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <!-- Left: Quick Reset Preset -->
                    <div class="flex items-center gap-2">
                        <flux:button 
                            type="button" 
                            wire:click="resetToDefaultWeights" 
                            variant="subtle" 
                            size="sm" 
                            icon="arrow-path" 
                            class="font-semibold text-xs border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300 hover:text-[#9b0000] dark:hover:text-[#f89696] shadow-2xs"
                        >
                            Reset to GRC Standard (40-20-20-15-5)
                        </flux:button>
                    </div>

                    <!-- Right: Overall Scale, Target Sum %, Current Sum -->
                    <div class="grid grid-cols-2 sm:flex sm:items-center gap-3 sm:gap-4 w-full md:w-auto shrink-0">
                        <div class="col-span-1 sm:w-28 md:w-32">
                            <label class="block text-[11px] sm:text-xs font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider mb-1">Overall Scale</label>
                            <div class="flex items-center gap-1.5">
                                <flux:input type="number" wire:model.live="overallMaxTarget" min="1" class="font-bold text-sm w-full" />
                                <span class="text-xs text-zinc-700 dark:text-zinc-200 font-bold shrink-0">pts</span>
                            </div>
                        </div>

                        <div class="col-span-1 sm:w-28 md:w-32">
                            <label class="block text-[11px] sm:text-xs font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider mb-1">Target Sum %</label>
                            <div class="flex items-center gap-1.5">
                                <flux:input type="number" wire:model.live="maxWeightPercent" min="1" max="100" class="font-bold text-sm w-full" />
                                <span class="text-xs text-zinc-700 dark:text-zinc-200 font-bold shrink-0">%</span>
                            </div>
                        </div>

                        <div class="col-span-2 sm:col-span-1 p-3 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 text-center min-w-[110px] shadow-2xs flex sm:flex-col items-center justify-between sm:justify-center">
                            <span class="text-[10px] uppercase font-bold text-zinc-500 dark:text-zinc-400 block tracking-wider mb-0.5">Current Sum</span>
                            <span class="text-xl font-mono font-extrabold {{ $isTeBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ $teWeights }}%</span>
                        </div>
                    </div>
                </div>

                @error('total_weights')
                    <div class="p-3 bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 rounded-xl border border-rose-200 dark:border-rose-800 text-xs font-bold">
                        {{ $message }}
                    </div>
                @enderror

                <!-- 5 Unified Category Cards (Weight Target + Questionnaire Parts) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 items-start">
                    
                    <!-- 1. Student Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">1. Student Evaluation</h3>
                                    <flux:badge variant="{{ $isStudentBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold shrink-0">
                                        {{ $totals['combined_student'] ?? 0 }} / {{ $tStudent }} pts
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Students evaluate assigned Professor per class</p>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Target Weight:</span>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-20 sm:w-24">
                                        <flux:input type="number" wire:model.live="studentWeightTarget" min="0" max="100" class="text-right font-bold text-xs" />
                                    </div>
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Parts -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Questionnaire Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('student')">Add Part</flux:button>
                            </div>

                            @foreach($this->criteria->whereIn('evaluation_type', ['student', 'upward_student']) as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('points_student')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 2. Dean Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">2. Dean's Evaluation</h3>
                                    <flux:badge variant="{{ $isDeanBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold shrink-0">
                                        {{ $totals['dean'] ?? 0 }} / {{ $tDean }} pts
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">College Dean evaluates Faculty Member</p>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Target Weight:</span>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-20 sm:w-24">
                                        <flux:input type="number" wire:model.live="deanWeightTarget" min="0" max="100" class="text-right font-bold text-xs" />
                                    </div>
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Parts -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Questionnaire Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('dean')">Add Part</flux:button>
                            </div>

                            @forelse($this->criteria->where('evaluation_type', 'dean') as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-1">No Dean evaluation criteria parts created yet.</p>
                            @endforelse
                        </div>
                        @error('points_dean')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 3. Program Head Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">3. Program Head's Evaluation</h3>
                                    <flux:badge variant="{{ $isPhBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold shrink-0">
                                        {{ $totals['combined_program_head'] ?? 0 }} / {{ $tPhDh }} pts
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Program Head evaluates Department Faculty</p>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Target Weight:</span>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-20 sm:w-24">
                                        <flux:input type="number" wire:model.live="phDhWeightTarget" min="0" max="100" class="text-right font-bold text-xs" />
                                    </div>
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Parts -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Questionnaire Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('program_head')">Add Part</flux:button>
                            </div>

                            @forelse($this->criteria->whereIn('evaluation_type', ['program_head', 'ph_dh']) as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-1">No Program Head evaluation criteria parts created yet.</p>
                            @endforelse
                        </div>
                        @error('points_ph_dh')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 4. Peer Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">4. Peer Evaluation</h3>
                                    <flux:badge variant="{{ $isPeerBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold shrink-0">
                                        {{ $totals['peer'] ?? 0 }} / {{ $tPeer }} pts
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Faculty evaluates Department Peer Faculty</p>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Target Weight:</span>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-20 sm:w-24">
                                        <flux:input type="number" wire:model.live="peerWeightTarget" min="0" max="100" class="text-right font-bold text-xs" />
                                    </div>
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Parts -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Questionnaire Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('peer')">Add Part</flux:button>
                            </div>

                            @foreach($this->criteria->where('evaluation_type', 'peer') as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('points_peer')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 5. Self Evaluation Card (Spans full width on large screens) -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4 lg:col-span-2">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div class="space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">5. Self Evaluation</h3>
                                    <flux:badge variant="{{ $isSelfBalanced ? 'success' : 'danger' }}" size="sm" class="font-bold shrink-0">
                                        {{ $totals['self'] ?? 0 }} / {{ $tSelf }} pts
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">Faculty Member evaluates Self</p>
                            </div>
                            
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Target Weight:</span>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-20 sm:w-24">
                                        <flux:input type="number" wire:model.live="selfWeightTarget" min="0" max="100" class="text-right font-bold text-xs" />
                                    </div>
                                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Criteria Parts -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Questionnaire Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('self')">Add Part</flux:button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($this->criteria->where('evaluation_type', 'self') as $criterion)
                                    <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                        <div class="flex-1 min-w-0 pr-2">
                                            <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                            <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                                <flux:input 
                                                    type="number" 
                                                    wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                    min="0" 
                                                    class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                                />
                                                <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                            </div>
                                            <flux:dropdown align="end">
                                                <flux:button 
                                                    size="xs" 
                                                    variant="ghost" 
                                                    icon="ellipsis-vertical" 
                                                    class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                                />
                                                <flux:menu>
                                                    <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                        Edit Part
                                                    </flux:menu.item>
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                        Delete Part
                                                    </flux:menu.item>
                                                </flux:menu>
                                            </flux:dropdown>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @error('points_self')
                            <p class="text-xs text-rose-500 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

            @endif

            <!-- TAB 2: All Categories & Extended Roles (Global Master) -->
            @if($weightsReportTab === 'global_targets')
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 items-start">
                    
                    <!-- Department Head Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div>
                                <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">Department Head Eval</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Dept Head evaluates Administrative Staff</p>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Max Target:</span>
                                <div class="w-20 sm:w-24">
                                    <flux:input type="number" wire:model.live="departmentHeadMaxTarget" min="0" class="text-right font-bold text-xs" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Criteria Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('department_head')">Add Part</flux:button>
                            </div>

                            @forelse($this->criteria->whereIn('evaluation_type', ['department_head', 'downward']) as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-1">No Department Head criteria parts created yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Superior Evaluation Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div>
                                <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">Superior Evaluation</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Faculty → PH, Staff → DH, PH/DH → Dean</p>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Max Target:</span>
                                <div class="w-20 sm:w-24">
                                    <flux:input type="number" wire:model.live="superiorWeightTarget" min="0" class="text-right font-bold text-xs" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs font-bold text-zinc-600 dark:text-zinc-300 px-1">
                                <span>Criteria Parts</span>
                                <flux:button size="xs" variant="outline" icon="plus" wire:click="openCriterionModal('superior')">Add Part</flux:button>
                            </div>

                            @forelse($this->criteria->whereIn('evaluation_type', ['superior', 'upward_employee']) as $criterion)
                                <div class="flex items-center justify-between gap-2 sm:gap-3 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                    <div class="flex-1 min-w-0 pr-2">
                                        <span class="text-[10px] text-zinc-400 font-mono font-bold block">Part #{{ $criterion->order }}</span>
                                        <span class="font-bold text-xs text-zinc-900 dark:text-zinc-100 truncate block">{{ $criterion->name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <div class="w-20 sm:w-24 flex items-center gap-1.5">
                                            <flux:input 
                                                type="number" 
                                                wire:model.live.debounce.300ms="criteriaPoints.{{ $criterion->id }}" 
                                                min="0" 
                                                class="text-right font-bold text-xs bg-white dark:bg-zinc-900"
                                            />
                                            <span class="text-xs text-zinc-600 dark:text-zinc-300 font-bold shrink-0">pts</span>
                                        </div>
                                        <flux:dropdown align="end">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost" 
                                                icon="ellipsis-vertical" 
                                                class="shrink-0 text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
                                            />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditCriterionModal({{ $criterion->id }})">
                                                    Edit Part
                                                </flux:menu.item>
                                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDeleteCriterion({{ $criterion->id }})">
                                                    Delete Part
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-zinc-400 italic py-1">No Superior criteria parts created yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Staff Max Target Card -->
                    <div class="p-4 sm:p-5 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-300 dark:border-zinc-700 shadow-2xs space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between border-b border-zinc-200 dark:border-zinc-700 pb-3 gap-2.5 sm:gap-3">
                            <div>
                                <h3 class="font-extrabold text-sm text-zinc-900 dark:text-zinc-100">Staff Evaluation Target</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Staff Peer & Self Evaluation scale</p>
                            </div>
                            <div class="flex items-center justify-between sm:justify-end gap-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-zinc-200/60 dark:border-zinc-700/60">
                                <span class="text-xs font-bold text-zinc-600 dark:text-zinc-300 sm:hidden">Max Target:</span>
                                <div class="w-20 sm:w-24">
                                    <flux:input type="number" wire:model.live="staffMaxTarget" min="0" class="text-right font-bold text-xs" />
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Configures baseline maximum score target point allocation for administrative staff 360-degree reviews.
                        </p>
                    </div>

                </div>
            @endif

            <!-- Save Action & Status Strip -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-t border-zinc-200 dark:border-zinc-800 pt-4 mt-6">
                <div class="space-y-0.5">
                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider block">Scoring Balance Status</span>
                    <span class="text-xs sm:text-sm font-extrabold block {{ $allBalanced ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $allBalanced ? '✓ All 5 criteria categories are balanced with target score points' : '⚠️ Please balance individual part points to equal category target points before opening' }}
                    </span>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    <flux:button 
                        type="submit" 
                        variant="primary" 
                        class="w-full sm:w-auto !bg-[#9b0000] hover:!bg-[#7a0000] text-white font-bold justify-center"
                    >
                        Save Points & Score Weights
                    </flux:button>
                </div>
            </div>

        </form>
    </div>

    <!-- SECTION 4: Academic Years & Semesters Paginated Table -->
    <div id="academic-periods-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-4 sm:gap-6 w-full">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4 gap-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                    
                    Academic Years & Semesters
                </h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Catalog of institutional academic years and semester evaluation periods.</p>
            </div>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="openAddPeriodModal" class="w-full sm:w-auto justify-center">
                Add Academic Period
            </flux:button>
        </div>

        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full min-w-[750px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[28%] min-w-[180px] px-4 py-3.5 whitespace-nowrap">Academic Year</th>
                        <th class="w-[26%] min-w-[160px] px-4 py-3.5 whitespace-nowrap">Semester Name</th>
                        <th class="w-[18%] min-w-[130px] px-4 py-3.5 text-center whitespace-nowrap">Evaluation Window</th>
                        <th class="w-[14%] min-w-[100px] px-4 py-3.5 text-center whitespace-nowrap">Status</th>
                        <th class="w-[14%] min-w-[100px] px-4 py-3.5 text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse($this->semestersPaginated as $sem)
                        <tr wire:key="sem-{{ $sem->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <td class="px-4 py-3.5 font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                A.Y. {{ $sem->academicYear?->name ?? 'N/A' }}
                                @if($sem->academicYear?->is_active)
                                    <span class="ml-1.5 text-[10px] bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 font-bold px-1.5 py-0.5 rounded-full">Active Year</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-zinc-800 dark:text-zinc-200 font-semibold whitespace-nowrap">
                                {{ $sem->name }}
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($sem->is_evaluation_open)
                                    <flux:badge variant="success" size="sm" class="font-bold">Open</flux:badge>
                                @elseif($sem->evaluation_starts_at && $sem->evaluation_starts_at->isFuture())
                                    <flux:badge variant="warning" size="sm" class="font-bold">Scheduled</flux:badge>
                                @else
                                    <flux:badge variant="neutral" size="sm">Closed</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($sem->is_active)
                                    <flux:badge variant="info" size="sm" class="font-bold">Active Term</flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                @if(!$sem->is_active)
                                    <flux:button size="xs" variant="outline" wire:click="setActiveSemester({{ $sem->id }})">
                                        Set as Active
                                    </flux:button>
                                @else
                                    <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Current</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-400">No academic periods found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $this->semestersPaginated->links() }}
        </div>
    </div>

    <!-- Unified Add Academic Period Modal -->
    @if($showPeriodModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-md border border-zinc-200 dark:border-zinc-800 space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="calendar" class="size-5 text-[#9b0000] dark:text-[#f89696]" />
                    <flux:heading size="lg">Add Academic Period</flux:heading>
                </div>
                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="$set('showPeriodModal', false)" />
            </div>

            <form wire:submit="saveAcademicPeriod" class="space-y-4">
                <!-- Year Mode Switcher -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wider">Academic Year</label>
                    <div class="flex gap-2">
                        <button 
                            type="button" 
                            wire:click="$set('periodYearMode', 'existing')"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-lg border transition-all {{ $periodYearMode === 'existing' ? 'bg-[#9b0000] text-white border-[#9b0000]' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700' }}"
                        >
                            Select Existing Year
                        </button>
                        <button 
                            type="button" 
                            wire:click="$set('periodYearMode', 'new')"
                            class="flex-1 py-1.5 px-3 text-xs font-semibold rounded-lg border transition-all {{ $periodYearMode === 'new' ? 'bg-[#9b0000] text-white border-[#9b0000]' : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700' }}"
                        >
                            Create New Year
                        </button>
                    </div>
                </div>

                @if($periodYearMode === 'existing')
                    <div>
                        <flux:select wire:model="periodYearId" label="Choose Academic Year" required>
                            @foreach($this->academicYears as $yr)
                                <flux:select.option value="{{ $yr->id }}">A.Y. {{ $yr->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @error('periodYearId')
                            <p class="text-xs text-rose-500 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @else
                    <div>
                        <flux:input 
                            wire:model="periodNewYearName" 
                            label="New Academic Year Name" 
                            placeholder="e.g. 2026-2027" 
                            required 
                        />
                        <p class="text-[11px] text-zinc-500 mt-1">Format: YYYY-YYYY (e.g. 2026-2027)</p>
                        @error('periodNewYearName')
                            <p class="text-xs text-rose-500 font-semibold mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div>
                    <flux:select wire:model="periodSemesterName" label="Semester / Term" required>
                        <flux:select.option value="1st Semester">1st Semester</flux:select.option>
                        <flux:select.option value="2nd Semester">2nd Semester</flux:select.option>
                        <flux:select.option value="Summer">Summer</flux:select.option>
                    </flux:select>
                    @error('periodSemesterName')
                        <p class="text-xs text-rose-500 font-semibold mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button size="sm" wire:click="$set('showPeriodModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white font-bold">
                        Save Academic Period
                    </flux:button>
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
                    <flux:select.option value="student">Student Evaluation (Student evaluates Faculty)</flux:select.option>
                    <flux:select.option value="dean">Dean Evaluation (Dean evaluates Program Head / Department Head)</flux:select.option>
                    <flux:select.option value="program_head">Program Head Evaluation (Program Head evaluates Faculty)</flux:select.option>
                    <flux:select.option value="department_head">Department Head Evaluation (Department Head evaluates Staff)</flux:select.option>
                    <flux:select.option value="peer">Peer Evaluation (Faculty evaluates Peer / Staff evaluates Peer)</flux:select.option>
                    <flux:select.option value="self">Self Evaluation (Prog Head, Dept Head, Dean, Faculty, Staff evaluate Self)</flux:select.option>
                    <flux:select.option value="superior">Superior Evaluation (Faculty → PH, Staff → DH, PH/DH → Dean)</flux:select.option>
                </flux:select>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" type="button" wire:click="$set('showCriterionModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Create Part</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Edit Questionnaire Part / Criterion Modal -->
    @if($showEditCriterionModal && $editingCriterion)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl border border-zinc-200 dark:border-zinc-700 w-full max-w-md p-6 space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-700 pb-3">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Edit Questionnaire Part #{{ $editingCriterion->order }}</h3>
                <button type="button" wire:click="$set('showEditCriterionModal', false)" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 text-lg">✕</button>
            </div>

            <form wire:submit="updateCriterion" class="space-y-4">
                <div>
                    <flux:input 
                        wire:model="editCriterionName" 
                        label="Part / Criterion Name" 
                        placeholder="e.g. Mastery of Subject Matter" 
                        required 
                    />
                    @error('editCriterionName')
                        <p class="text-xs text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <flux:input 
                        type="number"
                        wire:model="editCriterionMaxPoints" 
                        label="Max Points Allocation" 
                        min="0" 
                        required 
                    />
                    @error('editCriterionMaxPoints')
                        <p class="text-xs text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800/60 p-3 border border-zinc-200 dark:border-zinc-700 text-xs text-zinc-600 dark:text-zinc-400 space-y-1">
                    <span class="font-bold block text-zinc-800 dark:text-zinc-200">Evaluation Category:</span>
                    <p class="capitalize font-mono">{{ str_replace('_', ' ', $editingCriterion->evaluation_type) }}</p>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" type="button" wire:click="$set('showEditCriterionModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white font-bold">Save Changes</flux:button>
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
