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

    public function mount()
    {
        $activeSem = Semester::getActive();
        if ($activeSem) {
            $this->selectedSemesterId = $activeSem->id;
        }
    }

    public function getSemestersProperty()
    {
        return Semester::with('academicYear')->orderBy('id', 'desc')->get();
    }

    public function getDepartmentsProperty()
    {
        return Department::getCachedList()
            ->filter(fn ($d) => is_null($d->type) || $d->type === 'academic')
            ->values();
    }

    public function getTeachersProperty()
    {
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

        return $query->get();
    }

    public function getIndividualReportDataProperty()
    {
        if (!$this->selectedTeacherId || !$this->selectedSemesterId) return null;

        $teacher = Employee::with(['user', 'department'])->findOrFail($this->selectedTeacherId);
        $userId = $teacher->user?->id;
        if (!$userId) return null;

        $semester = Semester::with('academicYear')->findOrFail($this->selectedSemesterId);

        $evalsQuery = Evaluation::with(['sentiment', 'evaluator.employee'])
            ->where('evaluatee_id', $userId)
            ->where('semester_id', $this->selectedSemesterId);

        $evaluations = $evalsQuery->get();
        $totalSubmissions = $evaluations->count();

        // 360 Degree Weights Allocation (Default out of 200 Max Points: Student 80/40%, Dean 40/20%, PH 40/20%, Peer 30/15%, Self 10/5%)
        $studentMax = (float)($semester->upward_student_max_points ?? 80.0);
        $deanMax = (float)($semester->dean_max_points ?? 40.0);
        $phMax = (float)($semester->program_head_max_points ?? $semester->downward_max_points ?? 40.0);
        $peerMax = (float)($semester->peer_max_points ?? 30.0);
        $selfMax = (float)($semester->self_max_points ?? 10.0);
        $totalScale = $studentMax + $deanMax + $phMax + $peerMax + $selfMax;
        if ($totalScale <= 0) $totalScale = 200.0;

        $studentPct = round(($studentMax / $totalScale) * 100);
        $deanPct = round(($deanMax / $totalScale) * 100);
        $phPct = round(($phMax / $totalScale) * 100);
        $peerPct = round(($peerMax / $totalScale) * 100);
        $selfPct = round(($selfMax / $totalScale) * 100);

        // Eager-load all criteria in a single query to prevent repeated N+1 queries
        $allCriteria = EvaluationCriterion::orderBy('order')->get();

        // Helper to calculate criteria breakdown & subtotal for a specific evaluation type
        $calculateSection = function (array $evalTypes, array $evaluatorRoles = [], float $sectionMaxPoints = 50.0) use ($evaluations, $allCriteria) {
            $matchedEvals = $evaluations->filter(function ($e) use ($evalTypes, $evaluatorRoles) {
                $typeMatch = in_array($e->evaluation_type, $evalTypes);
                if (!$typeMatch) return false;
                if (!empty($evaluatorRoles)) {
                    $evaluatorRole = $e->evaluator?->employee?->role ?? ($e->evaluator?->hasRole('dean') ? 'dean' : ($e->evaluator?->hasRole('program head') ? 'program head' : null));
                    return in_array($evaluatorRole, $evaluatorRoles);
                }
                return true;
            });

            $evalCount = $matchedEvals->count();
            $evalIds = $matchedEvals->pluck('id')->toArray();

            // Fetch criteria associated with these types from preloaded collection
            $criteria = $allCriteria->filter(fn($c) => in_array($c->evaluation_type, $evalTypes))->values();

            $parts = [];
            $sectionEarnedPoints = 0.0;

            if ($criteria->isNotEmpty()) {
                $criteriaSumMax = (float)$criteria->sum('max_points');
                if ($criteriaSumMax <= 0) $criteriaSumMax = 50.0;

                $critAvgs = !empty($evalIds) ? DB::table('evaluation_answers')
                    ->join('evaluation_questions', 'evaluation_questions.id', '=', 'evaluation_answers.question_id')
                    ->whereIn('evaluation_answers.evaluation_id', $evalIds)
                    ->selectRaw('evaluation_questions.criterion_id, avg(evaluation_answers.rating) as avg_rating')
                    ->groupBy('evaluation_questions.criterion_id')
                    ->pluck('avg_rating', 'criterion_id') : collect();

                foreach ($criteria as $idx => $crit) {
                    $rawAvg = isset($critAvgs[$crit->id]) ? (float)$critAvgs[$crit->id] : null;

                    // If evaluated, scale rating (1-5) to criterion max_points
                    $score = $rawAvg ? round(((float)$rawAvg / 5.0) * (float)$crit->max_points, 2) : 0.00;
                    $parts[] = (object) [
                        'roman' => $this->toRoman($idx + 1),
                        'name' => preg_replace('/^Part\s*\d+\s*:\s*/i', '', $crit->name),
                        'score' => $score,
                        'max_points' => (float)$crit->max_points,
                        'raw_avg' => $rawAvg ? round($rawAvg, 2) : null,
                    ];
                    $sectionEarnedPoints += $score;
                }
            } else {
                // Default fallback parts if no specific criteria in db
                $avgRating = $evalCount > 0 ? (float)$matchedEvals->avg('rating_average') : 0.0;
                $sectionEarnedPoints = $avgRating > 0 ? round(($avgRating / 5.0) * $sectionMaxPoints, 2) : 0.0;
                $parts[] = (object) [
                    'roman' => 'I',
                    'name' => 'General Competence & Effectiveness',
                    'score' => $sectionEarnedPoints,
                    'max_points' => $sectionMaxPoints,
                    'raw_avg' => $avgRating > 0 ? $avgRating : null,
                ];
            }

            return (object) [
                'count' => $evalCount,
                'max_points' => $sectionMaxPoints,
                'subtotal' => round($sectionEarnedPoints, 2),
                'parts' => $parts,
                'average_5_scale' => $evalCount > 0 ? round($matchedEvals->avg('rating_average'), 2) : 0.00,
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
        $prevSemester = Semester::with('academicYear')
            ->where('id', '<', $semester->id)
            ->orderBy('id', 'desc')
            ->first();

        $prevOverallAvg = null;
        $scoreGrowth = null;
        $scoreGrowthPercent = null;

        if ($prevSemester) {
            $prevEvals = Evaluation::where('evaluatee_id', $userId)
                ->where('semester_id', $prevSemester->id)
                ->get();

            if ($prevEvals->count() > 0) {
                $prevRawAvg = (float)$prevEvals->avg('rating_average');
                $prevOverallAvg = round(($prevRawAvg / 5.0) * $totalScale, 2);
                if ($prevOverallAvg > 0) {
                    $scoreGrowth = round($totalAchievedPoints - $prevOverallAvg, 2);
                    $scoreGrowthPercent = round(($scoreGrowth / $prevOverallAvg) * 100, 1);
                }
            }
        }

        // AI Sentiment Analysis & Bilingual Theme Extraction for Page 2
        $studentEvals = $evaluations->whereIn('evaluation_type', ['student', 'upward_student']);
        $studentComments = [];
        $posCount = 0;
        $neuCount = 0;
        $negCount = 0;

        foreach ($studentEvals as $eval) {
            if ($eval->comments && trim($eval->comments) !== '') {
                $label = $eval->sentiment?->active_label;
                if (!$label) {
                    $score = $eval->sentiment?->vader_score ?? 0;
                    $label = $score > 0.05 ? 'positive' : ($score < -0.05 ? 'negative' : 'neutral');
                }
                $label = strtolower($label);
                if ($label === 'positive') $posCount++;
                elseif ($label === 'negative') $negCount++;
                else $neuCount++;

                $studentComments[] = (object) [
                    'text' => trim($eval->comments),
                    'sentiment' => $label,
                ];
            }
        }

        $totalComments = count($studentComments);
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
        $joinedComments = strtolower(implode(' ', array_map(fn($c) => $c->text, $studentComments)));

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

        // Curated comments sample
        $curatedComments = array_slice($studentComments, 0, 8);

        // Retrieve Dean & Program Head names for signatories
        $deptId = $teacher->department_id;
        $phEmp = Employee::where('department_id', $deptId)->where('role', 'program head')->where('status', 'active')->first();
        $deanEmp = Employee::where('role', 'dean')->where('status', 'active')->first();

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
                'curated_comments' => $curatedComments,
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

        $semester = Semester::with('academicYear')->findOrFail($this->selectedSemesterId);
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
        $prevSemester = Semester::where('id', '<', $semester->id)->orderBy('id', 'desc')->first();
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
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'individual' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="user" class="size-4" />
            Individual Teaching Effectiveness Report
        </button>

        <button 
            type="button"
            wire:click="$set('activeTab', 'summary')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'summary' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
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
                <div class="lg:col-span-2 flex items-center gap-3">
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
                    @if($selectedTeacherId && $selectedSemesterId)
                        <flux:button variant="primary" icon="arrow-down-tray" onclick="window.print()" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white shrink-0 font-bold">
                            Save as PDF
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.remove wire:target="selectedTeacherId, selectedSemesterId, activeTab">
        @if($activeTab === 'individual')
            @if($selectedTeacherId && $selectedSemesterId && $this->individualReportData)
                @php $report = $this->individualReportData; @endphp
                
                <!-- Print Document Container -->
                <div class="flex flex-col gap-10 w-full max-w-5xl mx-auto">
                    
                    <!-- ================= PAGE 1: SUMMARY SCORECARD (GRC EXACT REPLICA) ================= -->
                    <div class="bg-white text-black border border-zinc-400 p-6 sm:p-8 md:p-10 rounded-2xl shadow-xl flex flex-col gap-3.5 print:border-none print:shadow-none print:p-0 print:m-0 print:gap-2.5 print:rounded-none" style="page-break-after: always; break-after: page;">
                        
                        <!-- Top Header: Logo + Institutional Header + Boxed Title -->
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-black pb-2 print:pb-1.5">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Logo" class="h-12 sm:h-14 md:h-16 w-auto object-contain" />
                                <div class="flex flex-col">
                                    <p class="text-[10.5px] text-zinc-700 leading-tight">454 GRC Bldg. Rizal Ave. Ext. 9th Avenue</p>
                                    <p class="text-[10.5px] text-zinc-700 leading-tight">Grace Park, Caloocan City</p>
                                </div>
                            </div>

                            <div class="border-2 border-black px-3 py-1 text-center max-w-md">
                                <h2 class="text-xs md:text-[13px] font-black uppercase tracking-wider leading-snug">
                                    Summary of Faculty Performance Evaluation on Teaching Effectiveness
                                </h2>
                            </div>
                        </div>

                        <!-- Meta Info Grid (School Year, Semester, Faculty Name, Department) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-0.5 text-[11px] font-semibold border-b border-black pb-1.5 print:pb-1">
                            <div class="flex items-baseline gap-2">
                                <span class="uppercase tracking-wider">School Year:</span>
                                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="uppercase tracking-wider">Semester:</span>
                                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
                            </div>
                            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2 mt-0.5">
                                <span class="uppercase tracking-wider">Name of Faculty Member:</span>
                                <span class="font-black text-xs md:text-[13px] uppercase underline">{{ $report->teacher->full_name }}</span>
                            </div>
                            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2">
                                <span class="uppercase tracking-wider">College / Department:</span>
                                <span class="font-bold uppercase underline">{{ $report->teacher->department->name ?? 'Academic Faculty' }} ({{ $report->teacher->department->code ?? 'N/A' }})</span>
                            </div>
                        </div>

                        <!-- Intro Notice -->
                        <div class="text-[11px] italic font-bold text-zinc-800 -my-0.5">
                            The following are the summary of your ratings:
                        </div>

                        <!-- Evaluation Ratings Section -->
                        <div class="flex flex-col gap-2 print:gap-1 text-[11px]">
                            
                            <!-- 1. STUDENTS EVALUATION -->
                            <div class="flex flex-col gap-0.5">
                                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                                    <span>Students Evaluation ({{ $report->student_pct }}%):</span>
                                    <span class="font-mono text-xs underline">{{ number_format($report->student_section->subtotal, 2) }}</span>
                                </div>
                                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                                    @foreach($report->student_section->parts as $part)
                                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- 2. DEAN'S EVALUATION -->
                            <div class="flex flex-col gap-0.5">
                                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                                    <span>Dean's Evaluation ({{ $report->dean_pct }}%):</span>
                                    <span class="font-mono text-xs underline">{{ number_format($report->dean_section->subtotal, 2) }}</span>
                                </div>
                                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                                    @foreach($report->dean_section->parts as $part)
                                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- 3. PROGRAM HEAD'S EVALUATION -->
                            <div class="flex flex-col gap-0.5">
                                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                                    <span>Program Head's Evaluation ({{ $report->ph_pct }}%):</span>
                                    <span class="font-mono text-xs underline">{{ number_format($report->ph_section->subtotal, 2) }}</span>
                                </div>
                                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                                    @foreach($report->ph_section->parts as $part)
                                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- 4. PEER EVALUATION (360° Inclusion) -->
                            <div class="flex flex-col gap-0.5">
                                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                                    <span>Peer Evaluation ({{ $report->peer_pct }}%):</span>
                                    <span class="font-mono text-xs underline">{{ number_format($report->peer_section->subtotal, 2) }}</span>
                                </div>
                                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                                    @foreach($report->peer_section->parts as $part)
                                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- 5. SELF EVALUATION -->
                            <div class="flex flex-col gap-0.5">
                                <div class="flex justify-between items-baseline font-black uppercase tracking-wide text-[11px]">
                                    <span>Self Evaluation ({{ $report->self_pct }}%):</span>
                                    <span class="font-mono text-xs underline">{{ number_format($report->self_section->subtotal, 2) }}</span>
                                </div>
                                <div class="pl-3 flex flex-col gap-0.5 text-[10.5px]">
                                    @foreach($report->self_section->parts as $part)
                                        <div class="flex justify-between items-center py-0 border-b border-dotted border-zinc-300">
                                            <span>{{ $part->roman }}. {{ $part->name }}</span>
                                            <span class="font-mono font-bold px-1.5 py-0 border border-black min-w-[50px] text-right text-[10px]">{{ number_format($part->score, 2) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Bottom Section: Legend Table & Overall Rating Box -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1 print:pt-1">
                            <!-- Legend Table -->
                            <div class="col-span-2 border-2 border-black text-[10px]">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b-2 border-black bg-zinc-100 font-bold uppercase">
                                            <th class="p-0.5 border-r border-black w-6 text-center"></th>
                                            <th class="p-0.5 border-r border-black px-1.5">Descriptive Rating</th>
                                            <th class="p-0.5 text-center" colspan="2">Weight Equivalent</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-black font-medium">
                                        <tr class="{{ $report->rating_code === 'E' ? 'bg-zinc-200 font-bold' : '' }}">
                                            <td class="p-0.5 text-center border-r border-black font-bold">L</td>
                                            <td class="p-0.5 border-r border-black px-1.5">Excellent</td>
                                            <td class="p-0.5 text-center border-r border-black w-16">194.95</td>
                                            <td class="p-0.5 text-center w-16">200.00</td>
                                        </tr>
                                        <tr class="{{ $report->rating_code === 'VS' ? 'bg-zinc-200 font-bold' : '' }}">
                                            <td class="p-0.5 text-center border-r border-black font-bold">E</td>
                                            <td class="p-0.5 border-r border-black px-1.5">Very Satisfactory</td>
                                            <td class="p-0.5 text-center border-r border-black">181.05</td>
                                            <td class="p-0.5 text-center">194.94</td>
                                        </tr>
                                        <tr class="{{ $report->rating_code === 'S' ? 'bg-zinc-200 font-bold' : '' }}">
                                            <td class="p-0.5 text-center border-r border-black font-bold">G</td>
                                            <td class="p-0.5 border-r border-black px-1.5">Satisfactory</td>
                                            <td class="p-0.5 text-center border-r border-black">153.26</td>
                                            <td class="p-0.5 text-center">181.04</td>
                                        </tr>
                                        <tr class="{{ $report->rating_code === 'NI' ? 'bg-zinc-200 font-bold' : '' }}">
                                            <td class="p-0.5 text-center border-r border-black font-bold">E</td>
                                            <td class="p-0.5 border-r border-black px-1.5">Need Improvement</td>
                                            <td class="p-0.5 text-center border-r border-black">139.35</td>
                                            <td class="p-0.5 text-center">153.25</td>
                                        </tr>
                                        <tr class="{{ $report->rating_code === 'P' ? 'bg-zinc-200 font-bold' : '' }}">
                                            <td class="p-0.5 text-center border-r border-black font-bold">N/D</td>
                                            <td class="p-0.5 border-r border-black px-1.5">Poor</td>
                                            <td class="p-0.5 text-center border-r border-black">1.00</td>
                                            <td class="p-0.5 text-center">139.34</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Overall Rating Box -->
                            <div class="col-span-1 border-2 border-black p-2 flex flex-col justify-center items-center text-center bg-zinc-50">
                                <span class="text-[10px] font-black uppercase tracking-wider mb-0.5">Overall Rating</span>
                                <div class="text-2xl font-black font-mono tracking-tight underline">{{ number_format($report->total_achieved_points, 2) }}</div>
                                <div class="text-[10px] font-black uppercase mt-1 px-1.5 py-0 border border-black bg-white">
                                    {{ $report->descriptive_rating }}
                                </div>
                            </div>
                        </div>

                        <!-- Signatories Section -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 pt-3 print:grid-cols-3 print:pt-3 text-[11px]">
                            <div class="flex flex-col items-center text-center">
                                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Prepared by:</span>
                                <div class="w-full border-b border-black"></div>
                                <span class="font-black uppercase mt-0.5 text-[10.5px]">Evaluation Coordinator</span>
                                <span class="text-[9px] text-zinc-600">HR / Academic Affairs</span>
                            </div>
                            <div class="flex flex-col items-center text-center">
                                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Noted by:</span>
                                <div class="w-full border-b border-black"></div>
                                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->program_head_name }}</span>
                                <span class="text-[9px] text-zinc-600">Program Head</span>
                            </div>
                            <div class="flex flex-col items-center text-center">
                                <span class="text-[9.5px] text-zinc-500 uppercase tracking-wider mb-5">Approved by:</span>
                                <div class="w-full border-b border-black"></div>
                                <span class="font-black uppercase mt-0.5 text-[10.5px]">{{ $report->dean_name }}</span>
                                <span class="text-[9px] text-zinc-600">College Dean</span>
                            </div>
                        </div>
                    </div>


                    <!-- ================= PAGE 2: AI STUDENT COMMENTS ANALYSIS ================= -->
                    <div class="bg-white text-black border border-zinc-400 p-8 md:p-12 rounded-2xl shadow-xl flex flex-col gap-6 print:border-none print:shadow-none print:p-0 print:m-0 print:rounded-none">
                        
                        <!-- Top Header: Signatories banner + Logo + Boxed Title -->
                        <div class="flex justify-between text-[11px] font-bold uppercase border-b border-zinc-300 pb-2">
                            <span>Human Resource Manager</span>
                            <span>College Dean</span>
                            <span>Executive Director</span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-black pb-4">
                            <div class="flex items-center gap-3.5">
                                <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Logo" class="h-14 md:h-18 w-auto object-contain" />
                                <div class="flex flex-col">
                                    <h1 class="text-sm font-black tracking-tight uppercase leading-tight">Global Reciprocal Colleges</h1>
                                    <p class="text-[10px] text-zinc-700 leading-tight">454 GRC Bldg. Rizal Ave. Ext. 9th Avenue, Grace Park, Caloocan City</p>
                                </div>
                            </div>

                            <div class="border-2 border-black px-4 py-2 text-center max-w-md">
                                <h2 class="text-xs md:text-sm font-black uppercase tracking-wider leading-snug">
                                    Student's Comments & AI Qualitative Analysis
                                </h2>
                            </div>
                        </div>

                        <!-- Meta Info Line -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-xs font-semibold border-b border-black pb-4">
                            <div class="flex items-baseline gap-2">
                                <span class="uppercase tracking-wider">School Year:</span>
                                <span class="font-bold underline uppercase">{{ $report->semester->academicYear->name }}</span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="uppercase tracking-wider">Semester:</span>
                                <span class="font-bold underline uppercase">{{ $report->semester->name }}</span>
                            </div>
                            <div class="flex items-baseline gap-2 col-span-1 md:col-span-2 mt-1">
                                <span class="uppercase tracking-wider">Name of Faculty Member:</span>
                                <span class="font-black text-sm uppercase underline">{{ $report->teacher->full_name }}</span>
                            </div>
                        </div>

                        <!-- 1. AI Sentiment Gauge & Distribution Card -->
                        <div class="border-2 border-black p-5 rounded-xl bg-zinc-50 flex flex-col gap-3">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                                <span class="text-xs font-black uppercase tracking-wider">AI Evaluator Sentiment Distribution</span>
                                <span class="px-2.5 py-0.5 text-xs font-bold border border-black bg-white">
                                    {{ $report->ai_sentiment->dominant_label }} ({{ $report->ai_sentiment->total_comments }} Total Comments)
                                </span>
                            </div>
                            
                            <div class="flex items-center gap-4 text-xs font-bold font-mono">
                                <span>Positive: {{ $report->ai_sentiment->pos_percent }}%</span>
                                <span>Neutral: {{ $report->ai_sentiment->neu_percent }}%</span>
                                <span>Constructive: {{ $report->ai_sentiment->neg_percent }}%</span>
                            </div>

                            <div class="w-full h-3 bg-zinc-200 border border-black rounded-full overflow-hidden flex">
                                <div class="bg-black h-full" style="width: {{ $report->ai_sentiment->pos_percent }}%" title="Positive: {{ $report->ai_sentiment->pos_percent }}%"></div>
                                <div class="bg-zinc-500 h-full" style="width: {{ $report->ai_sentiment->neu_percent }}%" title="Neutral: {{ $report->ai_sentiment->neu_percent }}%"></div>
                                <div class="bg-zinc-300 h-full" style="width: {{ $report->ai_sentiment->neg_percent }}%" title="Constructive: {{ $report->ai_sentiment->neg_percent }}%"></div>
                            </div>
                        </div>

                        <!-- 2. Top Commendations & Opportunities (Two-Column Thematic Breakdown) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Positive Themes -->
                            <div class="border border-black p-4 rounded-xl flex flex-col gap-2 bg-white">
                                <div class="flex items-center gap-1.5 text-xs font-black uppercase text-black border-b border-zinc-200 pb-1.5">
                                    <flux:icon icon="hand-thumb-up" class="size-4" />
                                    <span>Top Student Commendations</span>
                                </div>
                                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                                    @foreach($report->ai_sentiment->positive_drivers as $theme)
                                        <li class="font-medium text-zinc-800">{{ $theme }}</li>
                                    @endforeach
                                </ul>
                            </div>

                            <!-- Constructive Themes -->
                            <div class="border border-black p-4 rounded-xl flex flex-col gap-2 bg-white">
                                <div class="flex items-center gap-1.5 text-xs font-black uppercase text-black border-b border-zinc-200 pb-1.5">
                                    <flux:icon icon="light-bulb" class="size-4" />
                                    <span>Key Opportunities for Growth</span>
                                </div>
                                <ul class="text-xs flex flex-col gap-1.5 mt-1 list-disc pl-4">
                                    @foreach($report->ai_sentiment->constructive_themes as $theme)
                                        <li class="font-medium text-zinc-800">{{ $theme }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <!-- 3. Curated Representative Student Feedback Highlights -->
                        <div class="border-2 border-black p-5 rounded-xl flex flex-col gap-3">
                            <span class="text-xs font-black uppercase tracking-wider border-b border-black pb-1.5">
                                Representative Student Feedback Extracts (Bilingual NLP Processed)
                            </span>

                            @if(empty($report->ai_sentiment->curated_comments))
                                <div class="text-center py-6 text-xs text-zinc-500 font-medium italic">
                                    No written student comments were recorded for this academic evaluation period.
                                </div>
                            @else
                                <div class="grid grid-cols-1 gap-2.5 max-h-80 overflow-y-auto pr-1">
                                    @foreach($report->ai_sentiment->curated_comments as $c)
                                        <div class="p-3 border border-zinc-300 rounded-lg text-xs flex flex-col gap-1 bg-zinc-50/70">
                                            <div class="flex justify-between items-center">
                                                <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500">Student Response</span>
                                                <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 border border-black {{ $c->sentiment === 'positive' ? 'bg-zinc-200 text-black' : ($c->sentiment === 'negative' ? 'bg-black text-white' : 'bg-white text-zinc-700') }}">
                                                    {{ ucfirst($c->sentiment) }}
                                                </span>
                                            </div>
                                            <p class="italic text-zinc-800 font-medium">"{{ $c->text }}"</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            @else
                <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                    <flux:icon icon="document-chart-bar" class="size-16 mx-auto text-zinc-300 mb-3" />
                    <p class="font-medium text-zinc-500">Please select a professor and academic semester to load the official GRC Summary Performance Report.</p>
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
                            <flux:icon icon="bolt" class="size-4 text-black dark:text-white" />
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
                                    <flux:icon icon="exclamation-triangle" class="size-4 text-black dark:text-white" />
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
            }
        }
    </style>
</div>
