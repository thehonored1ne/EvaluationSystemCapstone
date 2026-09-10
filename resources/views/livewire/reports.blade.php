<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Semester;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationSummary;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.reports-skeleton');
    }
    public ?int $selectedTeacherId = null;
    public ?int $selectedSemesterId = null;
    public string $searchTeacher = '';
    public string $selectedDepartment = '';
    public string $activeTab = 'individual';
    public bool $isPrintingAll = false;
    public int $batchPreviewIndex = 0;
    public bool $batchShowAllOnScreen = false;

    public function startPrintAll()
    {
        ini_set('memory_limit', '512M');
        $this->isPrintingAll = true;
        $this->batchPreviewIndex = 0;
        $this->batchShowAllOnScreen = false;
    }

    public function exitPrintAll()
    {
        $this->isPrintingAll = false;
        $this->batchPreviewIndex = 0;
        $this->batchShowAllOnScreen = false;
    }

    public function nextBatchPreview()
    {
        $count = $this->allReportsData->count();
        if ($this->batchPreviewIndex < $count - 1) {
            $this->batchPreviewIndex++;
        }
    }

    public function previousBatchPreview()
    {
        if ($this->batchPreviewIndex > 0) {
            $this->batchPreviewIndex--;
        }
    }

    public function setBatchPreviewIndex($index)
    {
        $idx = (int)$index;
        $count = $this->allReportsData->count();
        if ($idx >= 0 && $idx < $count) {
            $this->batchPreviewIndex = $idx;
        }
    }

    public function toggleBatchShowAll()
    {
        $this->batchShowAllOnScreen = !$this->batchShowAllOnScreen;
    }

    public function updatedSelectedTeacherId()
    {
        $this->isPrintingAll = false;
        $this->batchPreviewIndex = 0;
    }

    public function updatedSearchTeacher()
    {
        $this->cachedTeachers = null;
        $this->cachedAllReports = null;
        $this->batchPreviewIndex = 0;
    }

    public function updatedSelectedDepartment()
    {
        $this->cachedTeachers = null;
        $this->cachedAllReports = null;
        $this->batchPreviewIndex = 0;
    }

    public function updatedSelectedSemesterId()
    {
        $this->cachedAllReports = null;
        $this->batchPreviewIndex = 0;
    }

    public function updatedActiveTab()
    {
        $this->isPrintingAll = false;
        $this->batchPreviewIndex = 0;
    }

    public function mount()
    {
        $activeSem = Semester::getActive();
        if ($activeSem) {
            $this->selectedSemesterId = $activeSem->id;
        }
    }

    private ?Collection $cachedSemesters = null;

    public function getSemestersProperty()
    {
        if ($this->cachedSemesters !== null) {
            return $this->cachedSemesters;
        }

        return $this->cachedSemesters = Semester::with('academicYear')->orderBy('id', 'desc')->get();
    }

    private ?Collection $cachedDepartments = null;

    public function getDepartmentsProperty()
    {
        if ($this->cachedDepartments !== null) {
            return $this->cachedDepartments;
        }

        return $this->cachedDepartments = Department::getCachedList()
            ->filter(fn ($d) => is_null($d->type) || $d->type === 'academic')
            ->values();
    }

    private ?Collection $cachedTeachers = null;

    public function getTeachersProperty()
    {
        if ($this->cachedTeachers !== null) {
            return $this->cachedTeachers;
        }

        $user = auth()->user();
        $query = Employee::whereIn('role', ['faculty', 'program head', 'dean'])
            ->whereHas('department', fn($dq) => $dq->whereNull('type')->orWhere('type', 'academic'))
            ->with(['department', 'user'])
            ->orderBy('first_name');

        if ($user->hasRole('program head')) {
            if ($user->employee?->department_id) {
                $query->where('department_id', $user->employee->department_id);
            }
        } elseif ($user->hasRole('dean')) {
            if ($user->employee?->department_id) {
                $query->where('department_id', $user->employee->department_id);
            }
        }

        if ($this->selectedDepartment) {
            $query->where('department_id', $this->selectedDepartment);
        }

        if ($this->searchTeacher) {
            $search = '%' . trim($this->searchTeacher) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('employee_number', 'like', $search);
            });
        }

        return $this->cachedTeachers = $query->get();
    }

    private ?Collection $cachedCriteria = null;

    public function getAllCriteria(): Collection
    {
        if ($this->cachedCriteria !== null) {
            return $this->cachedCriteria;
        }

        return $this->cachedCriteria = EvaluationCriterion::orderBy('order')->get();
    }

    private bool $deanLoaded = false;
    private ?Employee $cachedDean = null;

    public function getDean(): ?Employee
    {
        if (!$this->deanLoaded) {
            $this->cachedDean = Employee::where('role', 'dean')->where('status', 'active')->first();
            $this->deanLoaded = true;
        }

        return $this->cachedDean;
    }

    private ?Collection $cachedProgramHeads = null;

    public function getProgramHeadsByDept(): Collection
    {
        if ($this->cachedProgramHeads !== null) {
            return $this->cachedProgramHeads;
        }

        return $this->cachedProgramHeads = Employee::where('role', 'program head')
            ->where('status', 'active')
            ->whereNotNull('department_id')
            ->get()
            ->keyBy('department_id');
    }

    private array $prevSemesterMap = [];

    public function getPreviousSemester(int $semesterId): ?Semester
    {
        if (!array_key_exists($semesterId, $this->prevSemesterMap)) {
            $this->prevSemesterMap[$semesterId] = Semester::with('academicYear')
                ->where('id', '<', $semesterId)
                ->orderBy('id', 'desc')
                ->first();
        }

        return $this->prevSemesterMap[$semesterId];
    }

    public function getIndividualReportDataProperty()
    {
        if (!$this->selectedTeacherId || !$this->selectedSemesterId) return null;

        $teacher = $this->teachers->firstWhere('id', $this->selectedTeacherId)
            ?? Employee::with(['user', 'department'])->find($this->selectedTeacherId);
        if (!$teacher) return null;

        $semester = $this->semesters->firstWhere('id', $this->selectedSemesterId)
            ?? Semester::with('academicYear')->find($this->selectedSemesterId);
        if (!$semester) return null;

        return $this->getReportDataForTeacher($teacher, $semester);
    }

    private ?Collection $cachedAllReports = null;

    public function getAllReportsDataProperty()
    {
        if (!$this->isPrintingAll || !$this->selectedSemesterId) return collect();
        if ($this->cachedAllReports !== null) return $this->cachedAllReports;

        ini_set('memory_limit', '512M');

        $semester = $this->semesters->firstWhere('id', $this->selectedSemesterId)
            ?? Semester::with('academicYear')->find($this->selectedSemesterId);
        if (!$semester) return collect();

        $teachers = $this->teachers;
        if ($teachers->isEmpty()) return collect();

        $allCriteria = $this->getAllCriteria();
        $deanEmp = $this->getDean();
        $programHeadsByDept = $this->getProgramHeadsByDept();

        $teacherUserIds = $teachers->pluck('user.id')->filter()->all();

        // 1. Criteria averages across all teachers in 1 lightweight SQL aggregation
        $critAverages = DB::table('evaluation_answers')
            ->join('evaluation_questions', 'evaluation_questions.id', '=', 'evaluation_answers.question_id')
            ->join('evaluations', 'evaluations.id', '=', 'evaluation_answers.evaluation_id')
            ->where('evaluations.semester_id', $semester->id)
            ->whereIn('evaluations.evaluatee_id', $teacherUserIds)
            ->selectRaw('evaluations.evaluatee_id, evaluation_questions.criterion_id, avg(evaluation_answers.rating) as avg_rating')
            ->groupBy('evaluations.evaluatee_id', 'evaluation_questions.criterion_id')
            ->get();

        $critAveragesMap = [];
        foreach ($critAverages as $row) {
            $critAveragesMap[$row->evaluatee_id][$row->criterion_id] = (float) $row->avg_rating;
        }

        // 2. Section stats & counts across all teachers in 1 lightweight SQL aggregation
        $sectionStats = DB::table('evaluations')
            ->leftJoin('users', 'users.id', '=', 'evaluations.evaluator_id')
            ->leftJoin('employees', 'employees.id', '=', 'users.employee_id')
            ->leftJoin('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('evaluations.semester_id', $semester->id)
            ->whereIn('evaluations.evaluatee_id', $teacherUserIds)
            ->selectRaw("
                evaluations.evaluatee_id,
                evaluations.evaluation_type,
                coalesce(employees.role, roles.name, '') as evaluator_role,
                count(*) as eval_count,
                avg(evaluations.rating_average) as avg_rating
            ")
            ->groupBy('evaluations.evaluatee_id', 'evaluations.evaluation_type', 'evaluator_role')
            ->get();

        $sectionStatsMap = [];
        $totalSubmissionsMap = [];
        foreach ($sectionStats as $row) {
            $sectionStatsMap[$row->evaluatee_id][] = $row;
            $totalSubmissionsMap[$row->evaluatee_id] = ($totalSubmissionsMap[$row->evaluatee_id] ?? 0) + (int) $row->eval_count;
        }

        // 3. Sentiment & Comments aggregated per teacher in 1 SQL aggregation
        $sentimentAgg = DB::table('evaluations')
            ->leftJoin('evaluation_sentiments', 'evaluation_sentiments.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.semester_id', $semester->id)
            ->whereIn('evaluations.evaluatee_id', $teacherUserIds)
            ->whereIn('evaluations.evaluation_type', ['student', 'upward_student'])
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '')
            ->selectRaw("
                evaluations.evaluatee_id,
                count(*) as total_comments,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'positive' or (coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) is null and evaluation_sentiments.vader_score > 0.05) then 1 else 0 end) as pos_count,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' or (coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) is null and evaluation_sentiments.vader_score < -0.05) then 1 else 0 end) as neg_count,
                group_concat(evaluations.comments, ' ') as all_comments
            ")
            ->groupBy('evaluations.evaluatee_id')
            ->get()
            ->keyBy('evaluatee_id');

        // 4. Previous semester stats across all teachers in 1 SQL aggregation
        $prevSemester = $this->getPreviousSemester($semester->id);

        $prevStatsMap = collect();
        if ($prevSemester) {
            $prevStatsMap = DB::table('evaluations')
                ->where('semester_id', $prevSemester->id)
                ->whereIn('evaluatee_id', $teacherUserIds)
                ->selectRaw('evaluatee_id, avg(rating_average) as prev_avg, count(*) as prev_count')
                ->groupBy('evaluatee_id')
                ->get()
                ->keyBy('evaluatee_id');
        }

        $reports = [];
        foreach ($teachers as $teacher) {
            $data = $this->getReportDataForTeacher(
                $teacher,
                $semester,
                $allCriteria,
                $deanEmp,
                $programHeadsByDept,
                $critAveragesMap,
                $sectionStatsMap,
                $totalSubmissionsMap,
                $prevSemester,
                $prevStatsMap,
                $sentimentAgg
            );
            if ($data) {
                $reports[] = $data;
            }
        }

        return $this->cachedAllReports = collect($reports);
    }

    public function getReportDataForTeacher(
        Employee $teacher,
        Semester $semester,
        ?Collection $allCriteria = null,
        ?Employee $deanEmp = null,
        ?Collection $programHeadsByDept = null,
        ?array $preloadedCritAveragesMap = null,
        ?array $preloadedSectionStatsMap = null,
        ?array $preloadedTotalSubmissionsMap = null,
        ?Semester $preloadedPrevSemester = null,
        ?Collection $preloadedPrevStatsMap = null,
        ?Collection $preloadedSentimentAggMap = null
    ) {
        $userId = $teacher->user?->id;
        if (!$userId) return null;

        // 360 Degree Weights Allocation (Default out of 200 Max Points: Student 80/40%, Dean 40/20%, PH 40/20%, Peer 30/15%, Self 10/5%)
        $studentMax = (float) ($semester->upward_student_max_points ?? 80.0);
        $deanMax = (float) ($semester->dean_max_points ?? 40.0);
        $phMax = (float) ($semester->program_head_max_points ?? $semester->downward_max_points ?? 40.0);
        $peerMax = (float) ($semester->peer_max_points ?? 30.0);
        $selfMax = (float) ($semester->self_max_points ?? 10.0);
        $totalScale = $studentMax + $deanMax + $phMax + $peerMax + $selfMax;
        if ($totalScale <= 0) $totalScale = 200.0;

        $studentPct = round(($studentMax / $totalScale) * 100);
        $deanPct = round(($deanMax / $totalScale) * 100);
        $phPct = round(($phMax / $totalScale) * 100);
        $peerPct = round(($peerMax / $totalScale) * 100);
        $selfPct = round(($selfMax / $totalScale) * 100);

        $allCriteria = $allCriteria ?? $this->getAllCriteria();

        // Fetch criteria averages for this teacher
        if ($preloadedCritAveragesMap !== null) {
            $userCritMap = $preloadedCritAveragesMap[$userId] ?? [];
        } else {
            $userCritMap = DB::table('evaluation_answers')
                ->join('evaluation_questions', 'evaluation_questions.id', '=', 'evaluation_answers.question_id')
                ->join('evaluations', 'evaluations.id', '=', 'evaluation_answers.evaluation_id')
                ->where('evaluations.semester_id', $semester->id)
                ->where('evaluations.evaluatee_id', $userId)
                ->selectRaw('evaluation_questions.criterion_id, avg(evaluation_answers.rating) as avg_rating')
                ->groupBy('evaluation_questions.criterion_id')
                ->pluck('avg_rating', 'criterion_id')
                ->map(fn ($v) => (float) $v)
                ->all();
        }

        // Fetch section evaluation statistics for this teacher
        if ($preloadedSectionStatsMap !== null) {
            $userSectionRows = $preloadedSectionStatsMap[$userId] ?? [];
        } else {
            $userSectionRows = DB::table('evaluations')
                ->leftJoin('users', 'users.id', '=', 'evaluations.evaluator_id')
                ->leftJoin('employees', 'employees.id', '=', 'users.employee_id')
                ->leftJoin('model_has_roles', function ($join) {
                    $join->on('model_has_roles.model_id', '=', 'users.id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('evaluations.semester_id', $semester->id)
                ->where('evaluations.evaluatee_id', $userId)
                ->selectRaw("
                    evaluations.evaluation_type,
                    coalesce(employees.role, roles.name, '') as evaluator_role,
                    count(*) as eval_count,
                    avg(evaluations.rating_average) as avg_rating
                ")
                ->groupBy('evaluations.evaluation_type', 'evaluator_role')
                ->get()
                ->all();
        }

        // Total Submissions count
        if ($preloadedTotalSubmissionsMap !== null) {
            $totalSubmissions = $preloadedTotalSubmissionsMap[$userId] ?? 0;
        } else {
            $totalSubmissions = array_sum(array_map(fn ($r) => (int) $r->eval_count, $userSectionRows));
        }

        // Helper to calculate criteria breakdown & subtotal for a specific evaluation type
        $calculateSection = function (array $evalTypes, array $evaluatorRoles = [], float $sectionMaxPoints = 50.0) use ($userSectionRows, $userCritMap, $allCriteria) {
            $matchedRows = array_filter($userSectionRows, function ($r) use ($evalTypes, $evaluatorRoles) {
                if (!in_array($r->evaluation_type, $evalTypes)) return false;
                if (!empty($evaluatorRoles) && !in_array($r->evaluator_role, $evaluatorRoles)) return false;
                return true;
            });

            $evalCount = 0;
            $totalRatingSum = 0.0;
            foreach ($matchedRows as $mr) {
                $evalCount += (int) $mr->eval_count;
                $totalRatingSum += ((float) $mr->avg_rating * (int) $mr->eval_count);
            }
            $avg5Scale = $evalCount > 0 ? round($totalRatingSum / $evalCount, 2) : 0.00;

            // Fetch criteria associated with these types
            $criteria = $allCriteria->filter(fn ($c) => in_array($c->evaluation_type, $evalTypes))->values();

            $parts = [];
            $sectionEarnedPoints = 0.0;

            if ($criteria->isNotEmpty()) {
                foreach ($criteria as $idx => $crit) {
                    $rawAvg = isset($userCritMap[$crit->id]) ? (float) $userCritMap[$crit->id] : null;
                    if ($rawAvg === null && $evalCount > 0 && $avg5Scale > 0) {
                        $rawAvg = $avg5Scale;
                    }

                    // If evaluated, scale rating (1-5) to criterion max_points
                    $score = $rawAvg ? round(((float) $rawAvg / 5.0) * (float) $crit->max_points, 2) : 0.00;
                    $parts[] = (object) [
                        'roman' => $this->toRoman($idx + 1),
                        'name' => preg_replace('/^Part\\s*\\d+\\s*:\\s*/i', '', $crit->name),
                        'score' => $score,
                        'max_points' => (float) $crit->max_points,
                        'raw_avg' => $rawAvg ? round($rawAvg, 2) : null,
                    ];
                    $sectionEarnedPoints += $score;
                }
            } else {
                // Default fallback parts if no specific criteria in db
                $sectionEarnedPoints = $avg5Scale > 0 ? round(($avg5Scale / 5.0) * $sectionMaxPoints, 2) : 0.0;
                $parts[] = (object) [
                    'roman' => 'I',
                    'name' => 'General Competence & Effectiveness',
                    'score' => $sectionEarnedPoints,
                    'max_points' => $sectionMaxPoints,
                    'raw_avg' => $avg5Scale > 0 ? $avg5Scale : null,
                ];
            }

            return (object) [
                'count' => $evalCount,
                'max_points' => $sectionMaxPoints,
                'subtotal' => round($sectionEarnedPoints, 2),
                'parts' => $parts,
                'average_5_scale' => $avg5Scale,
            ];
        };

        // 1. Students Evaluation (Student -> Faculty)
        $studentSection = $calculateSection(['student', 'upward_student'], [], $studentMax);

        // 2. Dean's Evaluation (Dean -> Faculty)
        $deanSection = $calculateSection(['dean', 'downward'], ['dean'], $deanMax);

        // 3. Program Head's Evaluation (Program Head -> Faculty)
        $phSection = $calculateSection(['program_head', 'downward', 'ph_dh'], ['program head'], $phMax);

        // 4. Peer Evaluation (Faculty -> Faculty)
        $peerSection = $calculateSection(['peer'], ['faculty'], $peerMax);

        // 5. Self Evaluation (Self -> Self)
        $selfSection = $calculateSection(['self'], [], $selfMax);

        // Composite Overall Rating on 200-point scale
        $totalAchievedPoints = round(
            $studentSection->subtotal +
            $deanSection->subtotal +
            $phSection->subtotal +
            $peerSection->subtotal +
            $selfSection->subtotal,
            2
        );

        // Performance Legend Bracket matching GRC
        if ($totalAchievedPoints >= 194.95) {
            $descriptiveRating = 'Excellent (E)';
            $ratingCode = 'E';
        } elseif ($totalAchievedPoints >= 181.05) {
            $descriptiveRating = 'Very Satisfactory (VS)';
            $ratingCode = 'VS';
        } elseif ($totalAchievedPoints >= 153.26) {
            $descriptiveRating = 'Satisfactory (S)';
            $ratingCode = 'S';
        } elseif ($totalAchievedPoints >= 139.35) {
            $descriptiveRating = 'Need Improvement (NI)';
            $ratingCode = 'NI';
        } else {
            $descriptiveRating = 'Poor (P)';
            $ratingCode = 'P';
        }

        // Calculate Semester-over-Semester Growth
        $prevSemester = $preloadedPrevSemester ?? $this->getPreviousSemester($semester->id);

        $prevOverallAvg = null;
        $scoreGrowth = null;
        $scoreGrowthPercent = null;

        if ($prevSemester) {
            if ($preloadedPrevStatsMap !== null) {
                $prevStat = $preloadedPrevStatsMap->get($userId);
            } else {
                $prevStat = DB::table('evaluations')
                    ->where('semester_id', $prevSemester->id)
                    ->where('evaluatee_id', $userId)
                    ->selectRaw('avg(rating_average) as prev_avg, count(*) as prev_count')
                    ->first();
            }

            if ($prevStat && (int) $prevStat->prev_count > 0) {
                $prevRawAvg = (float) $prevStat->prev_avg;
                $prevOverallAvg = round(($prevRawAvg / 5.0) * $totalScale, 2);
                if ($prevOverallAvg > 0) {
                    $scoreGrowth = round($totalAchievedPoints - $prevOverallAvg, 2);
                    $scoreGrowthPercent = round(($scoreGrowth / $prevOverallAvg) * 100, 1);
                }
            }
        }

        // AI Sentiment Analysis & Bilingual Theme Extraction for Page 2
        if ($preloadedSentimentAggMap !== null) {
            $sentimentRow = $preloadedSentimentAggMap->get($userId);
        } else {
            $sentimentRow = DB::table('evaluations')
                ->leftJoin('evaluation_sentiments', 'evaluation_sentiments.evaluation_id', '=', 'evaluations.id')
                ->where('evaluations.semester_id', $semester->id)
                ->where('evaluations.evaluatee_id', $userId)
                ->whereIn('evaluations.evaluation_type', ['student', 'upward_student'])
                ->whereNotNull('evaluations.comments')
                ->where('evaluations.comments', '!=', '')
                ->selectRaw("
                    count(*) as total_comments,
                    sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'positive' or (coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) is null and evaluation_sentiments.vader_score > 0.05) then 1 else 0 end) as pos_count,
                    sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' or (coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) is null and evaluation_sentiments.vader_score < -0.05) then 1 else 0 end) as neg_count,
                    group_concat(evaluations.comments, ' ') as all_comments
                ")
                ->first();
        }

        $totalComments = (int) ($sentimentRow?->total_comments ?? 0);
        $posCount = (int) ($sentimentRow?->pos_count ?? 0);
        $negCount = (int) ($sentimentRow?->neg_count ?? 0);
        $neuCount = max(0, $totalComments - $posCount - $negCount);

        $posPercent = $totalComments > 0 ? round(($posCount / $totalComments) * 100) : 0;
        $neuPercent = $totalComments > 0 ? round(($neuCount / $totalComments) * 100) : 0;
        $negPercent = $totalComments > 0 ? round(($negCount / $totalComments) * 100) : 0;

        if ($totalComments === 0) {
            $dominantSentiment = 'Neutral / No Student Comments Recorded';
        } elseif ($posPercent >= 65) {
            $dominantSentiment = 'Strongly Positive & Favorable';
        } elseif ($posPercent > $negPercent) {
            $dominantSentiment = 'Mostly Positive';
        } elseif ($negPercent >= 35) {
            $dominantSentiment = 'Notable Constructive Suggestions';
        } else {
            $dominantSentiment = 'Balanced Feedback';
        }

        // Extract Top Positive & Constructive Themes from Comments
        $joinedComments = strtolower($sentimentRow?->all_comments ?? '');

        $positiveDrivers = [];
        if (str_contains($joinedComments, 'clear') || str_contains($joinedComments, 'linaw') || str_contains($joinedComments, 'explain')) $positiveDrivers[] = 'Clear & Thorough Subject Explanations';
        if (str_contains($joinedComments, 'approach') || str_contains($joinedComments, 'mabait') || str_contains($joinedComments, 'patient') || str_contains($joinedComments, 'caring')) $positiveDrivers[] = 'Approachable, Patient & Supportive Demeanor';
        if (str_contains($joinedComments, 'engage') || str_contains($joinedComments, 'active') || str_contains($joinedComments, 'interactive') || str_contains($joinedComments, 'masaya')) $positiveDrivers[] = 'Interactive & Engaging Classroom Activities';
        if (str_contains($joinedComments, 'master') || str_contains($joinedComments, 'magaling') || str_contains($joinedComments, 'expert') || str_contains($joinedComments, 'galing')) $positiveDrivers[] = 'Command of Subject Matter & Expertise';
        if (str_contains($joinedComments, 'time') || str_contains($joinedComments, 'punctual') || str_contains($joinedComments, 'maaga') || str_contains($joinedComments, 'on time')) $positiveDrivers[] = 'Punctual & Effective Class Time Management';
        if (empty($positiveDrivers)) $positiveDrivers = ['Consistent instructional delivery', 'Professional teacher-student engagement'];

        $constructiveThemes = [];
        if (str_contains($joinedComments, 'pace') || str_contains($joinedComments, 'mabilis') || str_contains($joinedComments, 'fast') || str_contains($joinedComments, 'rush')) $constructiveThemes[] = 'Lecture pacing: students request slowing down during complex technical topics';
        if (str_contains($joinedComments, 'grade') || str_contains($joinedComments, 'late') || str_contains($joinedComments, 'tagal') || str_contains($joinedComments, 'feedback')) $constructiveThemes[] = 'Grading turnaround: student requests for earlier return of quizzes and project feedback';
        if (str_contains($joinedComments, 'rubric') || str_contains($joinedComments, 'criteria') || str_contains($joinedComments, 'unclear') || str_contains($joinedComments, 'instructions')) $constructiveThemes[] = 'Assessment transparency: provide detailed rubrics prior to major submissions';
        if (str_contains($joinedComments, 'consult') || str_contains($joinedComments, 'reply') || str_contains($joinedComments, 'message') || str_contains($joinedComments, 'chat')) $constructiveThemes[] = 'Consultation reachability: expand availability during official consultation hours';
        if (str_contains($joinedComments, 'absent') || str_contains($joinedComments, 'late') || str_contains($joinedComments, 'pasok')) $constructiveThemes[] = 'Attendance & Punctuality: maintain consistent physical/virtual class attendance';
        if (empty($constructiveThemes)) $constructiveThemes = ['Maintain continuous pedagogical refinement and student consultation channels.'];

        // Retrieve Dean & Program Head names for signatories
        $deptId = $teacher->department_id;
        $phMap = $programHeadsByDept ?? $this->getProgramHeadsByDept();
        $phEmp = $deptId ? $phMap->get($deptId) : null;
        $deanEmp = $deanEmp ?? $this->getDean();

        return (object) [
            'teacher' => $teacher,
            'semester' => $semester,
            'total_submissions' => $totalSubmissions,
            'total_scale' => $totalScale,
            'student_max' => $studentMax,
            'dean_max' => $deanMax,
            'ph_max' => $phMax,
            'peer_max' => $peerMax,
            'self_max' => $selfMax,
            'student_pct' => $studentPct,
            'dean_pct' => $deanPct,
            'ph_pct' => $phPct,
            'peer_pct' => $peerPct,
            'self_pct' => $selfPct,
            'student_section' => $studentSection,
            'dean_section' => $deanSection,
            'ph_section' => $phSection,
            'peer_section' => $peerSection,
            'self_section' => $selfSection,
            'total_achieved_points' => $totalAchievedPoints,
            'descriptive_rating' => $descriptiveRating,
            'rating_code' => $ratingCode,
            'prev_semester' => $prevSemester,
            'score_growth' => $scoreGrowth,
            'score_growth_percent' => $scoreGrowthPercent,
            'overall_average' => round(($totalAchievedPoints / $totalScale) * 5.0, 2),
            'performance_badge' => $descriptiveRating,
            'ai_sentiment' => (object) [
                'total_comments' => $totalComments,
                'pos_percent' => $posPercent,
                'neu_percent' => $neuPercent,
                'neg_percent' => $negPercent,
                'dominant_label' => $dominantSentiment,
                'positive_drivers' => $positiveDrivers,
                'constructive_themes' => $constructiveThemes,
            ],
            'program_head_name' => $phEmp ? $phEmp->full_name : 'Program Head',
            'dean_name' => $deanEmp ? $deanEmp->full_name : 'College Dean',
        ];
    }

    private function toRoman(int $number): string
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
            6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'
        ];
        return $map[$number] ?? (string)$number;
    }

    public function getReportDataProperty()
    {
        return $this->individualReportData;
    }

    public function getSummaryReportDataProperty()
    {
        if ($this->activeTab !== 'summary' || !$this->selectedSemesterId) return null;

        $semester = $this->semesters->firstWhere('id', $this->selectedSemesterId)
            ?? Semester::with('academicYear')->findOrFail($this->selectedSemesterId);
        $user = auth()->user();

        // 1. Pre-aggregate Department Evaluation Stats via direct SQL
        $deptEvalStats = DB::table('evaluations')
            ->join('users', 'users.id', '=', 'evaluations.evaluatee_id')
            ->join('employees', 'employees.id', '=', 'users.employee_id')
            ->leftJoin('evaluation_sentiments', 'evaluation_sentiments.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.semester_id', $semester->id)
            ->whereNotNull('employees.department_id')
            ->selectRaw("
                employees.department_id,
                count(*) as total_count,
                avg(evaluations.rating_average) as avg_rating,
                min(evaluations.rating_average) as min_rating,
                max(evaluations.rating_average) as max_rating,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'positive' then 1 else 0 end) as pos_count,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' then 1 else 0 end) as neg_count
            ")
            ->groupBy('employees.department_id')
            ->get()
            ->keyBy('department_id');

        $deptFacultyCounts = Employee::whereIn('role', ['faculty', 'program head'])
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        $deptClassCounts = DB::table('classes')
            ->join('employees', 'employees.id', '=', 'classes.teacher_id')
            ->where('classes.semester_id', $semester->id)
            ->selectRaw('employees.department_id, count(*) as count')
            ->groupBy('employees.department_id')
            ->pluck('count', 'department_id');

        $deptStudentCounts = DB::table('class_student')
            ->join('classes', 'classes.id', '=', 'class_student.class_id')
            ->join('employees', 'employees.id', '=', 'classes.teacher_id')
            ->where('classes.semester_id', $semester->id)
            ->selectRaw('employees.department_id, count(*) as count')
            ->groupBy('employees.department_id')
            ->pluck('count', 'department_id');

        // Period-over-period delta vs previous semester
        $prevSemester = $this->getPreviousSemester($semester->id);
        $prevDeptAvgMap = collect();
        if ($prevSemester) {
            $prevDeptAvgMap = DB::table('evaluations')
                ->join('users', 'users.id', '=', 'evaluations.evaluatee_id')
                ->join('employees', 'employees.id', '=', 'users.employee_id')
                ->where('evaluations.semester_id', $prevSemester->id)
                ->whereNotNull('employees.department_id')
                ->selectRaw('employees.department_id, avg(evaluations.rating_average) as avg_rating')
                ->groupBy('employees.department_id')
                ->pluck('avg_rating', 'department_id');
        }

        $deptQuery = Department::where(fn($q) => $q->whereNull('type')->orWhere('type', 'academic'))->orderBy('name');
        if ($user->hasRole('program head')) {
            $deptQuery->where('id', $user->employee->department_id);
        }

        $departments = $deptQuery->get()->map(function ($dept) use ($deptEvalStats, $deptFacultyCounts, $deptClassCounts, $deptStudentCounts, $prevDeptAvgMap) {
            $stat = $deptEvalStats->get($dept->id);
            $evalCount = (int) ($stat?->total_count ?? 0);
            $avgScore = $evalCount > 0 ? round((float) $stat->avg_rating, 2) : 0.00;
            $minScore = $evalCount > 0 ? round((float) $stat->min_rating, 2) : 0.00;
            $maxScore = $evalCount > 0 ? round((float) $stat->max_rating, 2) : 0.00;

            $posCount = (int) ($stat?->pos_count ?? 0);
            $negCount = (int) ($stat?->neg_count ?? 0);
            $neuCount = max(0, $evalCount - $posCount - $negCount);

            $posPct = $evalCount > 0 ? round(($posCount / $evalCount) * 100) : 0;
            $negPct = $evalCount > 0 ? round(($negCount / $evalCount) * 100) : 0;
            $neuPct = max(0, 100 - $posPct - $negPct);

            $prevAvg = isset($prevDeptAvgMap[$dept->id]) ? round((float) $prevDeptAvgMap[$dept->id], 2) : null;
            $delta = $prevAvg !== null ? round($avgScore - $prevAvg, 2) : null;

            $facultyCount = (int) ($deptFacultyCounts[$dept->id] ?? 0);
            $classesCount = (int) ($deptClassCounts[$dept->id] ?? 0);
            $enrolledEst = (int) ($deptStudentCounts[$dept->id] ?? 0);

            $expectedSubmissions = max($facultyCount * 3, $enrolledEst);
            $completionRate = $expectedSubmissions > 0 ? min(100, round(($evalCount / $expectedSubmissions) * 100)) : ($evalCount > 0 ? 100 : 0);

            $performanceLevel = match(true) {
                $avgScore >= 4.50 => 'Outstanding',
                $avgScore >= 4.00 => 'Very Satisfactory',
                $avgScore >= 3.00 => 'Satisfactory',
                $avgScore > 0.00  => 'Needs Improvement',
                default           => 'No Evaluations Yet'
            };

            return (object) [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
                'faculty_count' => $facultyCount,
                'classes_count' => $classesCount,
                'evaluations_count' => $evalCount,
                'average_rating' => $avgScore,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'std_dev' => 0.00,
                'pos_pct' => $posPct,
                'neu_pct' => $neuPct,
                'neg_pct' => $negPct,
                'prev_avg' => $prevAvg,
                'delta' => $delta,
                'expected_submissions' => $expectedSubmissions,
                'completion_rate' => $completionRate,
                'low_confidence' => $evalCount > 0 && $completionRate < 60,
                'performance_level' => $performanceLevel,
            ];
        })->sortByDesc('average_rating')->values();

        // 2. Institutional Totals via Direct SQL
        $instStats = DB::table('evaluations')
            ->leftJoin('evaluation_sentiments', 'evaluation_sentiments.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.semester_id', $semester->id)
            ->selectRaw("
                count(*) as total,
                avg(evaluations.rating_average) as avg_rating,
                avg(case when evaluations.evaluation_type = 'upward_student' then evaluations.rating_average else null end) as student_avg,
                count(distinct evaluations.evaluatee_id) as faculty_evaluated_count,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'positive' then 1 else 0 end) as pos_count,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' then 1 else 0 end) as neg_count
            ")
            ->first();

        $totalSubmissions = (int) ($instStats?->total ?? 0);
        $instAverage = $totalSubmissions > 0 ? round((float) $instStats->avg_rating, 2) : 0.00;
        $studentAvg = $totalSubmissions > 0 ? round((float) $instStats->student_avg, 2) : 0.00;
        $facultyEvaluatedCount = (int) ($instStats?->faculty_evaluated_count ?? 0);

        // 3. Faculty Requiring Attention via Direct SQL
        $facultyAttentionRows = DB::table('evaluations')
            ->join('users', 'users.id', '=', 'evaluations.evaluatee_id')
            ->join('employees', 'employees.id', '=', 'users.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('evaluation_sentiments', 'evaluation_sentiments.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.semester_id', $semester->id)
            ->whereIn('employees.role', ['faculty', 'program head'])
            ->selectRaw("
                employees.id as employee_id,
                employees.first_name,
                employees.last_name,
                departments.name as department_name,
                departments.code as department_code,
                count(*) as total_count,
                avg(evaluations.rating_average) as avg_rating,
                sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' then 1 else 0 end) as neg_count,
                group_concat(evaluations.comments, ' ') as all_comments
            ")
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'departments.name', 'departments.code')
            ->havingRaw("avg(evaluations.rating_average) < 3.50 OR (count(*) >= 3 AND (sum(case when coalesce(evaluation_sentiments.manual_label, evaluation_sentiments.vader_label) = 'negative' then 1 else 0 end) * 1.0 / count(*)) >= 0.30)")
            ->orderBy('avg_rating', 'asc')
            ->get();

        $facultyAttentionList = [];
        foreach ($facultyAttentionRows as $row) {
            $fAvg = round((float) $row->avg_rating, 2);
            $fNegPct = $row->total_count > 0 ? round(((int)$row->neg_count / (int)$row->total_count) * 100) : 0;
            $comments = !empty($row->all_comments) ? [$row->all_comments] : [];
            $reason = $this->generateFacultyAttentionReason($fAvg, $fNegPct, $comments);

            $facultyAttentionList[] = (object) [
                'id' => $row->employee_id,
                'name' => trim($row->first_name . ' ' . $row->last_name),
                'department' => $row->department_name ?? 'N/A',
                'department_code' => $row->department_code ?? 'N/A',
                'submissions' => (int) $row->total_count,
                'average' => $fAvg,
                'negative_pct' => $fNegPct,
                'severity' => $fAvg < 3.00 ? 'Critical' : 'Moderate',
                'reason' => $reason,
            ];
        }

        // Institutional Target Benchmark Comparison
        $targetBenchmark = 4.00;
        $benchmarkDelta = round($instAverage - $targetBenchmark, 2);
        $benchmarkStatus = $benchmarkDelta >= 0 ? 'Above Target' : 'Below Target';

        // Sentiment Breakdown
        $posTotal = (int) ($instStats?->pos_count ?? 0);
        $negTotal = (int) ($instStats?->neg_count ?? 0);
        $neuTotal = max(0, $totalSubmissions - $posTotal - $negTotal);

        $posPercent = $totalSubmissions > 0 ? round(($posTotal / $totalSubmissions) * 100) : 0;
        $neuPercent = $totalSubmissions > 0 ? round(($neuTotal / $totalSubmissions) * 100) : 0;
        $negPercent = $totalSubmissions > 0 ? round(($negTotal / $totalSubmissions) * 100) : 0;

        // Executive Action Recommendations
        $recommendations = [];
        if ($instAverage >= 4.20) {
            $recommendations[] = (object) [
                'type' => 'success',
                'title' => 'High Institutional Excellence Maintained',
                'description' => "Overall institution average of {$instAverage} surpasses target benchmark ({$targetBenchmark}). Replicate teaching methodologies across departments.",
            ];
        } else {
            $recommendations[] = (object) [
                'type' => 'warning',
                'title' => 'Priority Instructional Remediation Needed',
                'description' => "Institution average ({$instAverage}) sits below or near target ({$targetBenchmark}). Focus on active faculty coaching and lesson pacing seminars.",
            ];
        }

        if (count($facultyAttentionList) > 0) {
            $recommendations[] = (object) [
                'type' => 'danger',
                'title' => count($facultyAttentionList) . ' Faculty Flagged for 1-on-1 Dean Coaching',
                'description' => 'Coordinate with designated Program Heads to review student constructive comments and set 30-day pedagogical action plans.',
            ];
        }

        $lowConfidenceDepts = $departments->filter(fn($d) => $d->low_confidence)->count();
        if ($lowConfidenceDepts > 0) {
            $recommendations[] = (object) [
                'type' => 'info',
                'title' => "Low Turnout Warning in {$lowConfidenceDepts} Academic Department(s)",
                'description' => 'Response rates fall below 60% threshold. Broadcast mobile reminders to enrolled students before final examination permits are generated.',
            ];
        }

        return (object) [
            'semester' => $semester,
            'target_benchmark' => $targetBenchmark,
            'benchmark_delta' => $benchmarkDelta,
            'benchmark_status' => $benchmarkStatus,
            'total_submissions' => $totalSubmissions,
            'institutional_average' => $instAverage,
            'student_average' => $studentAvg,
            'faculty_evaluated_count' => $facultyEvaluatedCount,
            'departments' => $departments,
            'faculty_attention' => $facultyAttentionList,
            'recommendations' => $recommendations,
            'sentiment' => (object) [
                'pos_percent' => $posPercent,
                'neu_percent' => $neuPercent,
                'neg_percent' => $negPercent,
                'pos_count' => $posTotal,
                'neu_count' => $neuTotal,
                'neg_count' => $negTotal,
            ],
        ];
    }

    private function generateFacultyAttentionReason(float $avg, int $negPct, array $comments): string
    {
        if (!empty($comments)) {
            $joined = strtolower(implode(' ', $comments));
            if (str_contains($joined, 'pace') || str_contains($joined, 'mabilis') || str_contains($joined, 'fast') || str_contains($joined, 'rush')) return 'Recurring student feedback regarding lecture pacing and rapid discussion speed.';
            if (str_contains($joined, 'grade') || str_contains($joined, 'late') || str_contains($joined, 'feedback') || str_contains($joined, 'tagal')) return 'Frequent comments citing delayed return of graded coursework and feedback.';
            if (str_contains($joined, 'rubric') || str_contains($joined, 'unclear') || str_contains($joined, 'instruction') || str_contains($joined, 'criteria')) return 'Inquiries regarding assignment rubric transparency and project instructions.';
            if (str_contains($joined, 'consult') || str_contains($joined, 'reply') || str_contains($joined, 'message')) return 'Student requests for improved availability during scheduled consultation hours.';
        }
        return $avg < 3.00 ? 'Overall evaluation score falls significantly below the 3.50 satisfactory standard.' : 'Notable constructive sentiment spike (' . $negPct . '% critical) across student responses.';
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full print:hidden">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Evaluation Reports</flux:heading>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="selectedSemesterId" placeholder="Select Academic Period" class="w-full">
                    @foreach($this->semesters as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800 flex gap-2 md:gap-4 overflow-x-auto pb-0 print:hidden">
        <button 
            type="button"
            wire:click="$set('activeTab', 'individual')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'individual' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#e07a7a] dark:text-[#e07a7a] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="user" class="size-4" />
            Individual Teaching Effectiveness Report
        </button>

        <button 
            type="button"
            wire:click="$set('activeTab', 'summary')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'summary' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#e07a7a] dark:text-[#e07a7a] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="chart-bar-square" class="size-4" />
            Evaluation Summary Report
        </button>
    </div>

    <!-- Teacher Selection Bar (Only in Individual tab) -->
    @if($activeTab === 'individual')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs print:hidden space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-center">
                <!-- 1. Search Filter -->
                <div>
                    <flux:input 
                        wire:model.live.debounce.300ms="searchTeacher" 
                        icon="magnifying-glass" 
                        placeholder="Search name or ID..." 
                        clearable 
                    />
                </div>

                <!-- 2. Department Filter -->
                <div>
                    <flux:select wire:model.live="selectedDepartment" placeholder="All Departments" clearable>
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- 3. Faculty Member Select & Print Button -->
                <div class="lg:col-span-2 flex items-center gap-2">
                    <div class="flex-1 min-w-0">
                        <flux:select wire:model.live="selectedTeacherId" placeholder="Select Faculty Member / Professor" clearable>
                            <flux:select.option value="">Choose a Faculty Member ({{ $this->teachers->count() }} found)</flux:select.option>
                            @foreach($this->teachers as $teacher)
                                <flux:select.option value="{{ $teacher->id }}">
                                    {{ $teacher->full_name }} ({{ $teacher->department?->code ?? 'N/A' }} • {{ ucfirst($teacher->role) }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if($selectedTeacherId && $selectedSemesterId && !$isPrintingAll)
                        <flux:button variant="primary" icon="arrow-down-tray" onclick="window.print()" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white shrink-0 font-bold">
                            Save as PDF
                        </flux:button>
                    @endif
                    @if($selectedSemesterId && $this->teachers->isNotEmpty())
                        @if(!$isPrintingAll)
                            <flux:button variant="filled" icon="printer" wire:click="startPrintAll" class="shrink-0 font-bold text-xs" title="Generate batch print view for all {{ $this->teachers->count() }} faculty members">
                                Print All ({{ $this->teachers->count() }})
                            </flux:button>
                        @else
                            <flux:button variant="subtle" icon="x-mark" wire:click="exitPrintAll" class="shrink-0 font-bold text-xs">
                                Exit Batch View
                            </flux:button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.remove wire:target="selectedTeacherId, selectedSemesterId, activeTab, startPrintAll, exitPrintAll, nextBatchPreview, previousBatchPreview, toggleBatchShowAll, setBatchPreviewIndex">
        @if($activeTab === 'individual')
            @if($isPrintingAll)
                @php
                    $allReports = $this->allReportsData;
                    $totalReports = $allReports->count();
                    $currentReport = $allReports->get($batchPreviewIndex) ?? $allReports->first();
                @endphp

                <div 
                    x-data="{ 
                        currentIndex: {{ $batchPreviewIndex }}, 
                        showAll: {{ $batchShowAllOnScreen ? 'true' : 'false' }}, 
                        total: {{ $totalReports }},
                        prev() {
                            if (this.currentIndex > 0) {
                                this.currentIndex--;
                                window.scrollTo({ top: 180, behavior: 'smooth' });
                            }
                        },
                        next() {
                            if (this.currentIndex < this.total - 1) {
                                this.currentIndex++;
                                window.scrollTo({ top: 180, behavior: 'smooth' });
                            }
                        }
                    }"
                    class="w-full"
                >
                    <!-- Batch Print Hub & Stepper Navigator (Screen Only) -->
                    <div class="bg-amber-50 dark:bg-zinc-900 border border-amber-300 dark:border-amber-700/60 rounded-2xl p-4 md:p-5 flex flex-col gap-3.5 shadow-xs print:hidden mb-6">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 rounded-xl bg-[#9b0000]/10 text-[#9b0000] dark:bg-[#9b0000]/25 dark:text-red-400 shrink-0">
                                    <flux:icon icon="printer" class="size-6" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-bold text-sm md:text-base text-zinc-900 dark:text-zinc-100">
                                            Batch Print Hub: {{ $totalReports }} Faculty Member{{ $totalReports === 1 ? '' : 's' }}
                                        </h3>
                                        <span class="text-[10px] md:text-xs font-bold px-2 py-0.5 rounded-full bg-amber-200/80 dark:bg-amber-950/70 text-amber-950 dark:text-amber-200 border border-amber-300 dark:border-amber-700/50">
                                            {{ $totalReports * 2 }} Pages Total
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">
                                        Click "Print / Save All as PDF" to spool all {{ $totalReports }} reports into a single unified PDF file.
                                    </p>
                                </div>
                            </div>

                            <!-- Main Print Actions -->
                            <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto justify-end">
                                <flux:button variant="primary" icon="arrow-down-tray" onclick="window.print()" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white font-bold shrink-0">
                                    Print / Save All as PDF
                                </flux:button>
                                <flux:button variant="subtle" wire:click="exitPrintAll" class="font-bold shrink-0">
                                    Exit
                                </flux:button>
                            </div>
                        </div>

                        @if($totalReports > 0)
                            <!-- Stepper / Jump Dropdown & View Mode Switcher -->
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-3 border-t border-amber-200/80 dark:border-zinc-800">
                                <div x-show="!showAll" class="flex items-center gap-2 flex-1 min-w-0">
                                    <button 
                                        type="button" 
                                        @click="prev()" 
                                        :disabled="currentIndex <= 0" 
                                        class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all" 
                                        title="Previous Faculty Report"
                                    >
                                        <flux:icon icon="chevron-left" class="size-4" />
                                    </button>
                                    
                                    <div class="flex items-center gap-2 min-w-0 flex-1 sm:max-w-md">
                                        <select 
                                            x-model.number="currentIndex" 
                                            @change="window.scrollTo({ top: 180, behavior: 'smooth' })"
                                            class="w-full text-xs font-semibold rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 py-1.5 px-2.5 focus:ring-1 focus:ring-[#9b0000] focus:border-[#9b0000]"
                                        >
                                            @foreach($allReports as $idx => $r)
                                                <option value="{{ $idx }}">
                                                    [{{ $idx + 1 }}/{{ $totalReports }}] {{ $r->teacher->full_name }} ({{ $r->teacher->department?->code ?? 'N/A' }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button 
                                        type="button" 
                                        @click="next()" 
                                        :disabled="currentIndex >= total - 1" 
                                        class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all" 
                                        title="Next Faculty Report" 
                                    >
                                        <flux:icon icon="chevron-right" class="size-4" />
                                    </button>

                                    <span class="text-xs font-bold text-zinc-600 dark:text-zinc-400 shrink-0 hidden md:inline ml-1 font-mono" x-text="'Faculty ' + (currentIndex + 1) + ' of ' + total + ' (Pages ' + ((currentIndex * 2) + 1) + '–' + ((currentIndex * 2) + 2) + ')'">
                                        Faculty {{ $batchPreviewIndex + 1 }} of {{ $totalReports }} (Pages {{ ($batchPreviewIndex * 2) + 1 }}–{{ ($batchPreviewIndex * 2) + 2 }})
                                    </span>
                                </div>

                                <div x-show="showAll" class="flex items-center gap-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400" style="display: none;">
                                    <flux:icon icon="bars-3-bottom-left" class="size-4 text-zinc-500" />
                                    <span>Continuous Scroll Mode: Showing all {{ $totalReports }} faculty reports on screen</span>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                                    <button 
                                        type="button" 
                                        @click="showAll = !showAll" 
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700/60 transition-colors flex items-center gap-1.5"
                                        title="Toggle between single card stepping and scrolling through all reports at once"
                                    >
                                        <span x-show="!showAll" class="inline-flex items-center gap-1.5">
                                            <flux:icon icon="arrows-pointing-out" class="size-3.5 text-zinc-500" />
                                            <span>Show All on Screen</span>
                                        </span>
                                        <span x-show="showAll" class="inline-flex items-center gap-1.5" style="display: none;">
                                            <flux:icon icon="document" class="size-3.5 text-zinc-500" />
                                            <span>Single-Card Preview</span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Batch Reports List -->
                    @if($totalReports > 0)
                        <div class="flex flex-col gap-12 print:gap-0 w-full max-w-5xl mx-auto print:max-w-none print:w-full">
                            @foreach($allReports as $index => $report)
                                <div 
                                    x-show="showAll || currentIndex == {{ $index }}"
                                    class="batch-report-item print:!block"
                                    @if(!$batchShowAllOnScreen && $index !== $batchPreviewIndex) style="display: none;" @endif
                                >
                                    @include('livewire.reports.faculty-report-card', ['report' => $report])
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                            <flux:icon icon="document-chart-bar" class="size-16 mx-auto text-zinc-300 mb-3" />
                            <p class="font-medium text-zinc-500">No faculty members found for the current department or search filters.</p>
                        </div>
                    @endif
                </div>
            @elseif($selectedTeacherId && $selectedSemesterId && $this->individualReportData)
                @include('livewire.reports.faculty-report-card', ['report' => $this->individualReportData])
            @else
                <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                    <flux:icon icon="document-chart-bar" class="size-16 mx-auto text-zinc-300 mb-3" />
                    <p class="font-medium text-zinc-500">Please select a professor and academic semester to load the official GRC Summary Performance Report, or click "Print All" to view all faculty reports.</p>
                </div>
            @endif
        @endif

        @if($activeTab === 'summary')
            @if($selectedSemesterId && $this->summaryReportData)
                @php $summary = $this->summaryReportData; @endphp
                <div class="flex justify-end print:hidden mb-4">
                    <flux:button variant="primary" icon="printer" onclick="window.print()" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white">
                        Print Summary Report
                    </flux:button>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 flex flex-col gap-8 print:border-none print:shadow-none print:bg-white print:text-black">
                    <div class="text-center border-b-2 border-zinc-900 dark:border-zinc-100 print:border-black pb-6 flex flex-col gap-2">
                        <div class="flex items-center justify-center gap-3">
                            <h2 class="text-2xl font-black uppercase tracking-wide text-zinc-900 dark:text-zinc-50 print:text-black">Evaluation Summary Report</h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 print:border-black print:text-black">
                                Target Benchmark: {{ number_format($summary->target_benchmark, 2) }} ({{ $summary->benchmark_delta >= 0 ? '+' : '' }}{{ number_format($summary->benchmark_delta, 2) }} {{ $summary->benchmark_status }})
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-400 print:text-black">
                            Academic Period: {{ $summary->semester->academicYear->name }} — {{ $summary->semester->name }}
                        </p>
                        <p class="text-xs text-zinc-500 font-mono">Scope: All Academic Departments • Generated: {{ now()->format('M d, Y h:i A') }}</p>
                    </div>

                    <!-- Top 4 Clean KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="border-2 border-zinc-900 dark:border-zinc-100 p-5 rounded-2xl text-center bg-zinc-50 dark:bg-zinc-800/40 flex flex-col justify-center items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">Institutional Average</span>
                            <div class="text-3xl font-black text-zinc-900 dark:text-zinc-50 print:text-black mt-1 font-mono">
                                {{ number_format($summary->institutional_average, 2) }} <span class="text-xs font-normal text-zinc-500">/ 5.00</span>
                            </div>
                            <span class="text-[11px] font-bold text-zinc-700 dark:text-zinc-300 mt-1 uppercase tracking-wide">Across All Evaluations</span>
                        </div>

                        <div class="border border-zinc-300 dark:border-zinc-700 p-5 rounded-2xl text-center bg-white dark:bg-zinc-900 flex flex-col justify-center items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Student Survey Average</span>
                            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 print:text-black mt-1 font-mono">
                                {{ number_format($summary->student_average, 2) }}
                            </div>
                            <span class="text-[11px] text-zinc-500 mt-0.5">Direct Classroom Feedback</span>
                        </div>

                        <div class="border border-zinc-300 dark:border-zinc-700 p-5 rounded-2xl text-center bg-white dark:bg-zinc-900 flex flex-col justify-center items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Faculty Evaluated</span>
                            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 print:text-black mt-1 font-mono">
                                {{ $summary->faculty_evaluated_count }}
                            </div>
                            <span class="text-[11px] text-zinc-500 mt-0.5">Active Academic Teachers</span>
                        </div>

                        <div class="border border-zinc-300 dark:border-zinc-700 p-5 rounded-2xl text-center bg-white dark:bg-zinc-900 flex flex-col justify-center items-center">
                            <span class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Submissions</span>
                            <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 print:text-black mt-1 font-mono">
                                {{ $summary->total_submissions }}
                            </div>
                            <span class="text-[11px] text-zinc-500 mt-0.5">Completed Survey Records</span>
                        </div>
                    </div>

                    <!-- Prescriptive AI Action Cards -->
                    <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-sm uppercase tracking-wider flex items-center gap-2">
                            <flux:icon icon="bolt" class="size-4 text-black dark:text-zinc-100" />
                            Prescriptive AI Executive Insights & Priorities
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($summary->recommendations as $rec)
                                <div class="border border-zinc-300 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50 dark:bg-zinc-800/30 flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2 font-bold text-xs uppercase tracking-wide text-zinc-900 dark:text-zinc-100">
                                        <span class="size-2 rounded-full {{ $rec->type === 'danger' ? 'bg-rose-500' : ($rec->type === 'warning' ? 'bg-amber-500' : 'bg-emerald-500') }}"></span>
                                        {{ $rec->title }}
                                    </div>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">{{ $rec->description }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Faculty Requiring Attention Table -->
                    @if(count($summary->faculty_attention) > 0)
                        <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                            <div class="flex items-center justify-between">
                                <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-sm uppercase tracking-wider flex items-center gap-2">
                                    <flux:icon icon="exclamation-triangle" class="size-4 text-black dark:text-zinc-100" />
                                    Faculty Requiring Pedagogical Attention (Score < 3.50 or ≥30% Constructive)
                                </h3>
                                <span class="text-xs font-bold text-zinc-500">{{ count($summary->faculty_attention) }} Instructors Flagged</span>
                            </div>
                            <div class="overflow-x-auto rounded-xl border border-zinc-300 dark:border-zinc-700">
                                <table class="w-full text-left text-xs min-w-[650px]">
                                    <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-bold uppercase tracking-wider border-b border-zinc-300 dark:border-zinc-700">
                                        <tr>
                                            <th class="px-4 py-3">Instructor Name</th>
                                            <th class="px-4 py-3">Department</th>
                                            <th class="px-4 py-3 text-center">Submissions</th>
                                            <th class="px-4 py-3 text-center">Average</th>
                                            <th class="px-4 py-3 text-center">Severity</th>
                                            <th class="px-4 py-3">Primary Feedback Driver</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                        @foreach($summary->faculty_attention as $fac)
                                            <tr>
                                                <td class="px-4 py-3 font-bold text-zinc-900 dark:text-zinc-100">{{ $fac->name }}</td>
                                                <td class="px-4 py-3 text-zinc-500">{{ $fac->department_code }}</td>
                                                <td class="px-4 py-3 text-center font-mono font-bold">{{ $fac->submissions }}</td>
                                                <td class="px-4 py-3 text-center font-mono font-black {{ $fac->average < 3.00 ? 'text-rose-600' : 'text-zinc-800 dark:text-zinc-200' }}">
                                                    {{ number_format($fac->average, 2) }}
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase border {{ $fac->severity === 'Critical' ? 'border-rose-500 text-rose-600 bg-rose-50 dark:bg-rose-950/40' : 'border-amber-500 text-amber-600 bg-amber-50 dark:bg-amber-950/40' }}">
                                                        {{ $fac->severity }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $fac->reason }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Department Rankings Table with Turnout & Rating Spread -->
                    <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-sm uppercase tracking-wider">
                                All Academic Department Rankings & Performance
                            </h3>
                            <span class="text-xs text-zinc-500 font-medium">Ranked by Composite Score</span>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-zinc-300 dark:border-zinc-700">
                            <table class="w-full text-left text-xs min-w-[720px]">
                                <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-bold uppercase tracking-wider border-b border-zinc-300 dark:border-zinc-700">
                                    <tr>
                                        <th class="px-4 py-3 text-center w-12">Rank</th>
                                        <th class="px-4 py-3">Department</th>
                                        <th class="px-4 py-3 text-center">Faculty</th>
                                        <th class="px-4 py-3 text-center">Turnout</th>
                                        <th class="px-4 py-3 text-center">Rating Spread</th>
                                        <th class="px-4 py-3 text-center">Average</th>
                                        <th class="px-4 py-3 text-center">Period Delta</th>
                                        <th class="px-4 py-3 text-right">Performance Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                    @foreach($summary->departments as $index => $d)
                                        <tr>
                                            <td class="px-4 py-3 text-center font-bold text-zinc-500 font-mono">{{ $index + 1 }}</td>
                                            <td class="px-4 py-3">
                                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $d->name }}</div>
                                                <div class="text-[11px] text-zinc-500 font-mono">{{ $d->code }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center font-bold text-zinc-700 dark:text-zinc-300">{{ $d->faculty_count }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <div class="font-mono font-bold">{{ $d->evaluations_count }} evals</div>
                                                @if($d->low_confidence)
                                                    <span class="text-[10px] text-amber-600 font-bold">Low Turnout (<60%)</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center font-mono text-zinc-600 dark:text-zinc-400">
                                                Range: {{ number_format($d->min_score, 2) }} - {{ number_format($d->max_score, 2) }}
                                                @if($d->std_dev > 0)
                                                    <span class="block text-[10px] text-zinc-400">σ = {{ number_format($d->std_dev, 2) }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center font-black text-zinc-900 dark:text-zinc-100 font-mono text-sm">
                                                {{ $d->average_rating > 0 ? number_format($d->average_rating, 2) : 'N/A' }}
                                            </td>
                                            <td class="px-4 py-3 text-center font-mono font-bold">
                                                @if(!is_null($d->delta))
                                                    <span class="{{ $d->delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                        {{ $d->delta >= 0 ? '▲ +' : '▼ ' }}{{ number_format($d->delta, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-zinc-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right font-bold text-zinc-800 dark:text-zinc-200">
                                                {{ $d->performance_level }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 12mm;
            }
            body {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background-color: white !important;
                color: black !important;
            }
            [data-flux-sidebar],
            [data-flux-header],
            header,
            nav,
            .print\:hidden {
                display: none !important;
            }
            main, [data-flux-main] {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .batch-report-item {
                display: block !important;
            }
        }
    </style>
</div>
