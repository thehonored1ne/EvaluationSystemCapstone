<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Activitylog\Models\Activity;

new #[Layout('components.layouts.app')] class extends Component
{
    public bool $showReminderModal = false;

    protected ?array $cachedData = null;

    public function placeholder()
    {
        return view('livewire.placeholders.admin-dashboard-skeleton');
    }

    public function sendReminderToast(): void
    {
        $activeSem = Semester::getActive();

        if (! $activeSem || ! $activeSem->isEvaluationWindowActive()) {
            Flux::toast(
                heading: 'Evaluation Window Inactive',
                text: 'Evaluation is not open yet. Reminders cannot be broadcasted while the evaluation period is closed.',
                variant: 'warning'
            );

            return;
        }

        $this->showReminderModal = true;
    }

    public function confirmBroadcastReminders(): void
    {
        $activeSem = Semester::getActive();

        if (! $activeSem || ! $activeSem->isEvaluationWindowActive()) {
            $this->showReminderModal = false;
            Flux::toast(
                heading: 'Evaluation Window Inactive',
                text: 'Evaluation is not open yet. Reminders cannot be broadcasted while the evaluation period is closed.',
                variant: 'warning'
            );

            return;
        }

        $user = auth()->user();

        Artisan::call('evaluations:send-reminders', ['--force' => true]);

        if ($user && function_exists('activity')) {
            activity('evaluations')
                ->causedBy($user)
                ->log('Broadcasted evaluation completion reminders across all pending evaluators via Admin Dashboard.');
        }

        $this->showReminderModal = false;

        Flux::toast(
            heading: 'Reminders Broadcasted',
            text: 'Evaluation submission reminders have been processed and broadcasted to all pending evaluators.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        if ($this->cachedData !== null) {
            return $this->cachedData;
        }

        // 1. Fetch Active Semester & Year
        $activeSem = Semester::getActive();
        $activeYear = $activeSem ? $activeSem->academicYear : null;
        $activeSemId = $activeSem ? $activeSem->id : null;

        $metrics = \Illuminate\Support\Facades\Cache::remember('admin_dashboard_metrics_'.($activeSemId ?? 'none'), 30, function () use ($activeSem, $activeSemId) {
            // 2. Core Entity Counts
            $employeeCount = Employee::count();
            $studentCount = Student::where('status', 'regular')->count();
            $userCount = User::count();

            // 3. Expected & Submitted evaluations progress
            $expectedCount = 0;
            $submittedCount = 0;
            $progressPercent = 0;

            if ($activeSemId) {
                // Expected Student Upward evaluations (enrollments in active semester classes)
                $studentExpected = DB::table('class_student')
                    ->join('classes', 'classes.id', '=', 'class_student.class_id')
                    ->where('classes.semester_id', $activeSemId)
                    ->count();

                // Expected Faculty Self evaluations (1 per active non-admin employee)
                $employeeSelfExpected = Employee::where('status', 'active')->where('role', '!=', 'admin')->count();

                // Expected Faculty Peer evaluations (calculated via SQL aggregate)
                $peerFacultyCounts = DB::table('employees')
                    ->where('role', 'faculty')
                    ->where('status', 'active')
                    ->whereNotNull('department_id')
                    ->selectRaw('department_id, count(*) as total')
                    ->groupBy('department_id')
                    ->having('total', '>', 1)
                    ->pluck('total');

                $peerExpected = 0;
                foreach ($peerFacultyCounts as $c) {
                    $peerExpected += $c * ($c - 1);
                }

                // Total expected evaluations across all evaluation types
                $expectedCount = $studentExpected + $employeeSelfExpected + $peerExpected;

                // Total actual submitted evaluations in active semester
                $submittedCount = Evaluation::where('semester_id', $activeSemId)->count();

                if ($expectedCount > 0) {
                    $progressPercent = min(100.0, round(($submittedCount / $expectedCount) * 100, 1));
                }
            }

            // 4. AI Sentiment Statistics
            $sentimentStats = [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0,
                'average' => 0.0,
                'total' => 0,
            ];

            if ($activeSemId) {
                $sentimentRow = DB::table('evaluation_sentiments')
                    ->join('evaluations', 'evaluations.id', '=', 'evaluation_sentiments.evaluation_id')
                    ->where('evaluations.semester_id', $activeSemId)
                    ->selectRaw("
                        count(*) as total,
                        sum(case when vader_label = 'positive' then 1 else 0 end) as positive,
                        sum(case when vader_label = 'neutral' then 1 else 0 end) as neutral,
                        sum(case when vader_label = 'negative' then 1 else 0 end) as negative,
                        avg(vader_score) as avg_score
                    ")
                    ->first();

                if ($sentimentRow && $sentimentRow->total > 0) {
                    $sentimentStats['total'] = (int) $sentimentRow->total;
                    $sentimentStats['positive'] = (int) $sentimentRow->positive;
                    $sentimentStats['neutral'] = (int) $sentimentRow->neutral;
                    $sentimentStats['negative'] = (int) $sentimentRow->negative;
                    $sentimentStats['average'] = round((float) $sentimentRow->avg_score, 2);
                }
            }

            // 5. Active Schedule details
            $scheduleStatus = 'closed';
            $scheduleMessage = 'No active schedule window configured.';

            if ($activeSem) {
                if ($activeSem->is_evaluation_open) {
                    $starts = $activeSem->evaluation_starts_at;
                    $ends = $activeSem->evaluation_ends_at;

                    if ($starts && $ends) {
                        $now = Carbon::now('Asia/Manila');
                        if ($now->lt($starts)) {
                            $scheduleStatus = 'scheduled';
                            $scheduleMessage = 'Opens '.$starts->diffForHumans($now);
                        } elseif ($now->gt($ends)) {
                            $scheduleStatus = 'expired';
                            $scheduleMessage = 'Expired '.$ends->diffForHumans($now);
                        } else {
                            $scheduleStatus = 'active';
                            $scheduleMessage = 'Closes '.$ends->diffForHumans($now);
                        }
                    } else {
                        $scheduleStatus = 'active';
                        $scheduleMessage = 'Manually opened (No scheduled dates set).';
                    }
                } else {
                    $scheduleStatus = 'locked';
                    $scheduleMessage = 'Evaluation is currently locked/closed.';
                }
            }

            // 6. Department Stats
            $departmentStats = [];
            if ($activeSemId) {
                $departments = Department::where('type', 'academic')->orWhereNull('type')->orderBy('name')->get();

                $expectedMap = DB::table('class_student')
                    ->join('students', 'students.id', '=', 'class_student.student_id')
                    ->join('programs', 'programs.id', '=', 'students.program_id')
                    ->join('classes', 'classes.id', '=', 'class_student.class_id')
                    ->where('classes.semester_id', $activeSemId)
                    ->selectRaw('programs.department_id, count(*) as total')
                    ->groupBy('programs.department_id')
                    ->pluck('total', 'department_id');

                $submittedMap = DB::table('evaluations')
                    ->join('users', 'users.id', '=', 'evaluations.evaluator_id')
                    ->join('students', 'students.id', '=', 'users.student_id')
                    ->join('programs', 'programs.id', '=', 'students.program_id')
                    ->where('evaluations.semester_id', $activeSemId)
                    ->where('evaluations.evaluation_type', 'upward_student')
                    ->selectRaw('programs.department_id, count(*) as total')
                    ->groupBy('programs.department_id')
                    ->pluck('total', 'department_id');

                foreach ($departments as $dept) {
                    $deptExpected = (int) ($expectedMap[$dept->id] ?? 0);
                    $deptSubmitted = (int) ($submittedMap[$dept->id] ?? 0);
                    $rate = $deptExpected > 0 ? round(($deptSubmitted / $deptExpected) * 100, 1) : 0;
                    $pending = max(0, $deptExpected - $deptSubmitted);
                    $status = $rate >= 80 ? 'Complete' : ($rate >= 50 ? 'On Track' : ($deptExpected > 0 ? 'Needs Attention' : 'No Expected'));
                    $statusVariant = $rate >= 80 ? 'success' : ($rate >= 50 ? 'info' : ($deptExpected > 0 ? 'danger' : 'neutral'));
                    $barColor = $rate >= 80 ? 'bg-emerald-600 dark:bg-emerald-500' : ($rate >= 50 ? 'bg-amber-500 dark:bg-amber-400' : 'bg-rose-600 dark:bg-rose-500');

                    $departmentStats[] = [
                        'code' => $dept->code,
                        'name' => $dept->name,
                        'expected' => $deptExpected,
                        'submitted' => $deptSubmitted,
                        'pending' => $pending,
                        'rate' => $rate,
                        'status' => $status,
                        'status_variant' => $statusVariant,
                        'bar_color' => $barColor,
                    ];
                }
            }

            // 7. Role Turnout & Department Comparison Analytics
            $roleTurnoutData = [];
            $academicDeptScores = [];
            $adminDeptScores = [];
            $hasPrevComparison = false;
            $currentSemName = $activeSem ? ($activeSem->academicYear->name . ' • ' . $activeSem->name) : 'Current Term';
            $prevSemName = null;
            $prevRoleRates = [];
            $prevAcademicDeptRates = [];
            $prevAdminDeptRates = [];

            if ($activeSemId) {
                // Preload department counts and employee active maps
                $deptFacultyCountMap = DB::table('employees')->where('role', 'faculty')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
                $deptPhCountMap = DB::table('employees')->where('role', 'program head')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
                $deptStaffCountMap = DB::table('employees')->where('role', 'staff')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
                $facultyTotalCount = DB::table('employees')->where('role', 'faculty')->where('status', 'active')->count();
                $phTotalCount = DB::table('employees')->where('role', 'program head')->where('status', 'active')->count();

                $evalCountMap = DB::table('evaluations')
                    ->where('semester_id', $activeSemId)
                    ->selectRaw('evaluator_id, count(distinct evaluatee_id) as count')
                    ->groupBy('evaluator_id')
                    ->pluck('count', 'evaluator_id');

                // 1. Students (Distinct Enrolled Students who completed 100% of enrolled subjects)
                $studentEvaluatorStats = DB::table('students')
                    ->leftJoin('programs', 'programs.id', '=', 'students.program_id')
                    ->join('class_student', 'class_student.student_id', '=', 'students.id')
                    ->join('classes', 'classes.id', '=', 'class_student.class_id')
                    ->where('classes.semester_id', $activeSemId)
                    ->leftJoin('users', 'users.student_id', '=', 'students.id')
                    ->leftJoin('evaluations', function ($join) use ($activeSemId) {
                        $join->on('evaluations.evaluator_id', '=', 'users.id')
                            ->on('evaluations.class_id', '=', 'classes.id')
                            ->where('evaluations.semester_id', '=', $activeSemId);
                    })
                    ->groupBy('students.id', 'programs.department_id')
                    ->selectRaw('students.id, programs.department_id, count(distinct classes.id) as expected_count, count(distinct evaluations.id) as submitted_count')
                    ->get();

                $studentTotal = $studentEvaluatorStats->count();
                $studentCompleted = $studentEvaluatorStats->filter(fn ($s) => $s->expected_count > 0 && $s->submitted_count >= $s->expected_count)->count();
                $studentRate = $studentTotal > 0 ? min(100.0, round(($studentCompleted / $studentTotal) * 100, 1)) : 0.0;

                // Employee roles helper (Evaluators who completed 100% of assigned evaluations)
                $activeEmployees = DB::table('employees')
                    ->where('status', 'active')
                    ->where('role', '!=', 'admin')
                    ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
                    ->select('employees.id as emp_id', 'employees.role', 'employees.department_id', 'users.id as user_id')
                    ->get();

                $getRoleCompletionStats = function ($roleKey) use ($activeEmployees, $deptFacultyCountMap, $deptPhCountMap, $deptStaffCountMap, $facultyTotalCount, $phTotalCount, $evalCountMap) {
                    $emps = $activeEmployees->where('role', $roleKey);
                    $total = $emps->count();
                    $completed = 0;

                    foreach ($emps as $e) {
                        $userId = $e->user_id;
                        $sub = (int) ($evalCountMap[$userId] ?? 0);
                        $target = 1;

                        if ($roleKey === 'faculty') {
                            $deptFac = (int) ($deptFacultyCountMap[$e->department_id] ?? 0);
                            $deptPh = (int) ($deptPhCountMap[$e->department_id] ?? 0);
                            $target = 1 + max(0, $deptFac - 1) + $deptPh; // Self + Peers + Program Head(s)
                        } elseif ($roleKey === 'program head') {
                            $deptFac = (int) ($deptFacultyCountMap[$e->department_id] ?? 0);
                            $target = 1 + $deptFac + 1; // Self + Dept Faculty + Dean
                        } elseif ($roleKey === 'department head') {
                            $deptStaff = (int) ($deptStaffCountMap[$e->department_id] ?? 0);
                            $target = 1 + $deptStaff + 1; // Self + Dept Staff + Dean
                        } elseif ($roleKey === 'dean') {
                            $target = 1 + $facultyTotalCount + $phTotalCount; // Self (1) + Faculty (50) + Program Heads (4)
                        } elseif ($roleKey === 'staff') {
                            $deptStaff = (int) ($deptStaffCountMap[$e->department_id] ?? 0);
                            $target = 1 + max(0, $deptStaff - 1) + 1; // Self + Staff Peers + Dept Head
                        }

                        if ($sub >= $target && $target > 0) {
                            $completed++;
                        }
                    }

                    $rate = $total > 0 ? min(100.0, round(($completed / $total) * 100, 1)) : 0.0;

                    return [
                        'completed' => $completed,
                        'total' => $total,
                        'rate' => $rate,
                    ];
                };

                $facultyStats = $getRoleCompletionStats('faculty');
                $phStats = $getRoleCompletionStats('program head');
                $dhStats = $getRoleCompletionStats('department head');
                $deanStats = $getRoleCompletionStats('dean');
                $staffStats = $getRoleCompletionStats('staff');

                $roleTurnoutData = [
                    ['role' => 'Students', 'rate' => $studentRate, 'submitted' => $studentCompleted, 'expected' => $studentTotal],
                    ['role' => 'Faculty', 'rate' => $facultyStats['rate'], 'submitted' => $facultyStats['completed'], 'expected' => $facultyStats['total']],
                    ['role' => 'Prog. Heads', 'rate' => $phStats['rate'], 'submitted' => $phStats['completed'], 'expected' => $phStats['total']],
                    ['role' => 'Dept. Heads', 'rate' => $dhStats['rate'], 'submitted' => $dhStats['completed'], 'expected' => $dhStats['total']],
                    ['role' => 'Deans', 'rate' => $deanStats['rate'], 'submitted' => $deanStats['completed'], 'expected' => $deanStats['total']],
                    ['role' => 'Staff', 'rate' => $staffStats['rate'], 'submitted' => $staffStats['completed'], 'expected' => $staffStats['total']],
                ];

                // Academic Departments Turnout (Option 2: Distinct students in that college who completed 100% of enrolled forms)
                $academicDepts = Department::where('type', 'academic')->orWhereNull('type')->orderBy('name')->get();
                foreach ($academicDepts as $dept) {
                    $deptStudents = $studentEvaluatorStats->where('department_id', $dept->id);
                    $deptTotal = $deptStudents->count();
                    $deptCompleted = $deptStudents->filter(fn ($s) => $s->expected_count > 0 && $s->submitted_count >= $s->expected_count)->count();
                    $rate = $deptTotal > 0 ? min(100.0, round(($deptCompleted / $deptTotal) * 100, 1)) : 0.0;
                    $pending = max(0, $deptTotal - $deptCompleted);

                    $academicDeptScores[] = [
                        'name' => $dept->name,
                        'code' => $dept->code,
                        'rate' => $rate,
                        'submitted' => $deptCompleted,
                        'expected' => $deptTotal,
                        'pending' => $pending,
                    ];
                }

                // Administrative Departments Turnout (Person-Level: Distinct employees who completed 100% of required duty)
                $adminDepts = Department::where('type', 'administrative')->orderBy('name')->get();
                foreach ($adminDepts as $dept) {
                    $emps = $activeEmployees->where('department_id', $dept->id);
                    $totalEmps = $emps->count();
                    $completedEmps = 0;
                    $deptStaffCount = (int) ($deptStaffCountMap[$dept->id] ?? 0);

                    foreach ($emps as $e) {
                        $sub = (int) ($evalCountMap[$e->user_id] ?? 0);
                        $target = $e->role === 'department head'
                            ? (1 + $deptStaffCount + 1) // self + staff + superior
                            : (1 + max(0, $deptStaffCount - 1) + 1); // self + peer staff + head

                        if ($sub >= $target && $target > 0) {
                            $completedEmps++;
                        }
                    }

                    $rate = $totalEmps > 0 ? min(100.0, round(($completedEmps / $totalEmps) * 100, 1)) : 0.0;
                    $pending = max(0, $totalEmps - $completedEmps);

                    $adminDeptScores[] = [
                        'name' => $dept->name,
                        'code' => $dept->code,
                        'rate' => $rate,
                        'submitted' => $completedEmps,
                        'expected' => $totalEmps,
                        'pending' => $pending,
                    ];
                }
            }

            // 4. Institutional Rating & Previous Semester Comparison
            $institutionalAverage = 0.00;
            $ratingDelta = null;
            $ratingLabel = 'No Ratings';
            $ratingBadgeClasses = 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700';

            $positivePct = 0.0;
            $negativePct = 0.0;
            $sentimentDelta = null;
            $completionDelta = null;
            $pendingDelta = null;

            if ($activeSemId) {
                $avgRating = Evaluation::where('semester_id', $activeSemId)->avg('rating_average');
                if ($avgRating !== null) {
                    $institutionalAverage = round((float) $avgRating, 2);
                }

                $ratingLabel = match (true) {
                    $institutionalAverage >= 4.50 => 'Outstanding',
                    $institutionalAverage >= 3.50 => 'Very Satisfactory',
                    $institutionalAverage >= 2.50 => 'Satisfactory',
                    $institutionalAverage >= 1.50 => 'Fair',
                    $institutionalAverage > 0.00 => 'Poor',
                    default => 'No Ratings'
                };

                $ratingBadgeClasses = match (true) {
                    $institutionalAverage >= 3.50 => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                    $institutionalAverage >= 2.50 => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
                    $institutionalAverage > 0.00 => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800',
                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'
                };

                // Previous Semester Comparisons (Strict Chronological Precedence)
                $prevSem = $activeSem ? $activeSem->getPreviousSemester(mustHaveEvaluations: true) : null;

                if ($prevSem) {
                    $hasPrevComparison = true;
                    $prevSemName = $prevSem->academicYear->name . ' • ' . $prevSem->name;

                    $prevAvg = Evaluation::where('semester_id', $prevSem->id)->avg('rating_average');
                    if ($prevAvg !== null) {
                        $ratingDelta = round($institutionalAverage - (float) $prevAvg, 2);
                    }

                    $prevSent = DB::table('evaluation_sentiments')
                        ->join('evaluations', 'evaluations.id', '=', 'evaluation_sentiments.evaluation_id')
                        ->where('evaluations.semester_id', $prevSem->id)
                        ->selectRaw("
                            count(*) as total,
                            sum(case when vader_label = 'positive' then 1 else 0 end) as positive
                        ")->first();

                    if ($prevSent && $prevSent->total > 0) {
                        $prevPos = round(((int) $prevSent->positive / (int) $prevSent->total) * 100, 1);
                        $prevPosCurrent = $sentimentStats['total'] > 0 ? round(((int) $sentimentStats['positive'] / (int) $sentimentStats['total']) * 100, 1) : 0.0;
                        $sentimentDelta = round($prevPosCurrent - $prevPos, 1);
                    }

                    // Previous semester evaluator completion rate comparison
                    $prevStudentStats = DB::table('students')
                        ->leftJoin('programs', 'programs.id', '=', 'students.program_id')
                        ->join('class_student', 'class_student.student_id', '=', 'students.id')
                        ->join('classes', 'classes.id', '=', 'class_student.class_id')
                        ->where('classes.semester_id', $prevSem->id)
                        ->leftJoin('users', 'users.student_id', '=', 'students.id')
                        ->leftJoin('evaluations', function ($join) use ($prevSem) {
                            $join->on('evaluations.evaluator_id', '=', 'users.id')
                                ->on('evaluations.class_id', '=', 'classes.id')
                                ->where('evaluations.semester_id', '=', $prevSem->id);
                        })
                        ->groupBy('students.id', 'programs.department_id')
                        ->selectRaw('students.id, programs.department_id, count(distinct classes.id) as expected_count, count(distinct evaluations.id) as submitted_count')
                        ->get();

                    $prevStudentTotal = $prevStudentStats->count();
                    $prevStudentCompleted = $prevStudentStats->filter(fn ($s) => $s->submitted_count >= $s->expected_count && $s->expected_count > 0)->count();
                    $prevStudentRate = $prevStudentTotal > 0 ? min(100.0, round(($prevStudentCompleted / $prevStudentTotal) * 100, 1)) : 0.0;

                    $prevEmpEvaluated = DB::table('evaluations')
                        ->where('semester_id', $prevSem->id)
                        ->whereIn('evaluation_type', ['self', 'peer', 'upward_program_head', 'dean', 'staff_peer', 'downward_head'])
                        ->distinct('evaluator_id')
                        ->count('evaluator_id');

                    $prevEmpTotal = Employee::where('status', 'active')->where('role', '!=', 'admin')->count();

                    $prevTotal = $prevStudentTotal + $prevEmpTotal;
                    $prevCompleted = $prevStudentCompleted + $prevEmpEvaluated;

                    if ($prevTotal > 0) {
                        $prevRate = min(100.0, round(($prevCompleted / $prevTotal) * 100, 1));
                    }

                    // Prior Semester Turnout per Evaluator Role & Department
                    $prevEvalCountMap = DB::table('evaluations')
                        ->where('semester_id', $prevSem->id)
                        ->selectRaw('evaluator_id, count(distinct evaluatee_id) as count')
                        ->groupBy('evaluator_id')
                        ->pluck('count', 'evaluator_id');

                    $getPrevRoleRate = function ($roleKey) use ($activeEmployees, $deptFacultyCountMap, $deptPhCountMap, $deptStaffCountMap, $facultyTotalCount, $phTotalCount, $prevEvalCountMap) {
                        $emps = $activeEmployees->where('role', $roleKey);
                        $total = $emps->count();
                        $completed = 0;
                        foreach ($emps as $e) {
                            $sub = (int) ($prevEvalCountMap[$e->user_id] ?? 0);
                            $target = 1;
                            if ($roleKey === 'faculty') {
                                $deptFac = (int) ($deptFacultyCountMap[$e->department_id] ?? 0);
                                $deptPh = (int) ($deptPhCountMap[$e->department_id] ?? 0);
                                $target = 1 + max(0, $deptFac - 1) + $deptPh;
                            } elseif ($roleKey === 'program head') {
                                $deptFac = (int) ($deptFacultyCountMap[$e->department_id] ?? 0);
                                $target = 1 + $deptFac + 1;
                            } elseif ($roleKey === 'department head') {
                                $deptStaff = (int) ($deptStaffCountMap[$e->department_id] ?? 0);
                                $target = 1 + $deptStaff + 1;
                            } elseif ($roleKey === 'dean') {
                                $target = 1 + $facultyTotalCount + $phTotalCount;
                            } elseif ($roleKey === 'staff') {
                                $deptStaff = (int) ($deptStaffCountMap[$e->department_id] ?? 0);
                                $target = 1 + max(0, $deptStaff - 1) + 1;
                            }
                            if ($sub >= $target && $target > 0) {
                                $completed++;
                            }
                        }
                        return $total > 0 ? min(100.0, round(($completed / $total) * 100, 1)) : 0.0;
                    };

                    $prevRoleMap = [
                        'Students' => $prevStudentRate,
                        'Faculty' => $getPrevRoleRate('faculty'),
                        'Prog. Heads' => $getPrevRoleRate('program head'),
                        'Dept. Heads' => $getPrevRoleRate('department head'),
                        'Deans' => $getPrevRoleRate('dean'),
                        'Staff' => $getPrevRoleRate('staff'),
                    ];

                    foreach ($roleTurnoutData as &$rItem) {
                        $rItem['prev_rate'] = $prevRoleMap[$rItem['role']] ?? null;
                    }
                    unset($rItem);

                    $prevRoleRates = [
                        $prevRoleMap['Students'] ?? 0,
                        $prevRoleMap['Faculty'] ?? 0,
                        $prevRoleMap['Prog. Heads'] ?? 0,
                        $prevRoleMap['Dept. Heads'] ?? 0,
                        $prevRoleMap['Deans'] ?? 0,
                        $prevRoleMap['Staff'] ?? 0,
                    ];

                    // Academic Depts comparison
                    foreach ($academicDeptScores as &$aItem) {
                        $pStudents = $prevStudentStats->where('department_id', $academicDepts->firstWhere('code', $aItem['code'])?->id);
                        $pTotal = $pStudents->count();
                        $pComp = $pStudents->filter(fn ($s) => $s->expected_count > 0 && $s->submitted_count >= $s->expected_count)->count();
                        $pRate = $pTotal > 0 ? min(100.0, round(($pComp / $pTotal) * 100, 1)) : 0.0;
                        $aItem['prev_rate'] = $pRate;
                        $prevAcademicDeptRates[] = $pRate;
                    }
                    unset($aItem);

                    // Administrative Depts comparison
                    foreach ($adminDeptScores as &$admItem) {
                        $adminDeptModel = $adminDepts->firstWhere('code', $admItem['code']);
                        $deptId = $adminDeptModel?->id;
                        $emps = $activeEmployees->where('department_id', $deptId);
                        $totalEmps = $emps->count();
                        $compEmps = 0;
                        $deptStaffCount = (int) ($deptStaffCountMap[$deptId] ?? 0);
                        foreach ($emps as $e) {
                            $sub = (int) ($prevEvalCountMap[$e->user_id] ?? 0);
                            $target = $e->role === 'department head'
                                ? (1 + $deptStaffCount + 1)
                                : (1 + max(0, $deptStaffCount - 1) + 1);
                            if ($sub >= $target && $target > 0) {
                                $compEmps++;
                            }
                        }
                        $pRate = $totalEmps > 0 ? min(100.0, round(($compEmps / $totalEmps) * 100, 1)) : 0.0;
                        $admItem['prev_rate'] = $pRate;
                        $prevAdminDeptRates[] = $pRate;
                    }
                    unset($admItem);
                }

                $posCount = $sentimentStats['positive'];
                $negCount = $sentimentStats['negative'];
                $totalComments = $sentimentStats['total'];

                $positivePct = $totalComments > 0 ? round(($posCount / $totalComments) * 100, 1) : 0.0;
                $negativePct = $totalComments > 0 ? round(($negCount / $totalComments) * 100, 1) : 0.0;

                // Weekly distinct evaluators who submitted in the last 7 days
                $last7DaysSubmitted = DB::table('evaluations')
                    ->where('semester_id', $activeSemId)
                    ->where('created_at', '>=', Carbon::now('Asia/Manila')->subDays(7))
                    ->distinct('evaluator_id')
                    ->count('evaluator_id');

                $pendingDelta = $last7DaysSubmitted;
            }

            // 5. Evaluator Person Counts (Distinct users expected vs completed all assigned evaluations)
            $totalEvaluatorsCount = 0;
            $completedEvaluatorsCount = 0;
            $pendingEvaluatorsCount = 0;

            if ($activeSemId) {
                // Student evaluators enrolled in active classes
                $studentEvaluatorStats = DB::table('students')
                    ->join('class_student', 'class_student.student_id', '=', 'students.id')
                    ->join('classes', 'classes.id', '=', 'class_student.class_id')
                    ->where('classes.semester_id', $activeSemId)
                    ->leftJoin('users', 'users.student_id', '=', 'students.id')
                    ->leftJoin('evaluations', function ($join) use ($activeSemId) {
                        $join->on('evaluations.evaluator_id', '=', 'users.id')
                            ->on('evaluations.class_id', '=', 'classes.id')
                            ->where('evaluations.semester_id', '=', $activeSemId);
                    })
                    ->groupBy('students.id')
                    ->selectRaw('students.id, count(distinct classes.id) as expected_count, count(distinct evaluations.id) as submitted_count')
                    ->get();

                $studentTotal = $studentEvaluatorStats->count();
                $studentCompleted = $studentEvaluatorStats->filter(fn ($s) => $s->submitted_count >= $s->expected_count && $s->expected_count > 0)->count();

                $employeeTotal = $facultyStats['total'] + $phStats['total'] + $dhStats['total'] + $deanStats['total'] + $staffStats['total'];
                $employeeCompleted = $facultyStats['completed'] + $phStats['completed'] + $dhStats['completed'] + $deanStats['completed'] + $staffStats['completed'];

                $totalEvaluatorsCount = $studentTotal + $employeeTotal;
                $completedEvaluatorsCount = $studentCompleted + $employeeCompleted;
                $pendingEvaluatorsCount = max(0, $totalEvaluatorsCount - $completedEvaluatorsCount);
                $pendingStudentsCount = max(0, $studentTotal - $studentCompleted);
                $pendingEmployeesCount = max(0, $employeeTotal - $employeeCompleted);
                $pendingDelta = $completedEvaluatorsCount;

                if ($totalEvaluatorsCount > 0) {
                    $progressPercent = min(100.0, round(($completedEvaluatorsCount / $totalEvaluatorsCount) * 100, 1));
                }

                if (isset($prevRate)) {
                    $completionDelta = round($progressPercent - $prevRate, 1);
                }
            }

            $thematicDrivers = \App\Services\ThematicAnalysisService::getThematicDrivers($activeSemId, 5);

            return [
                'employeeCount' => $employeeCount,
                'studentCount' => $studentCount,
                'userCount' => $userCount,
                'expectedCount' => $expectedCount,
                'submittedCount' => $submittedCount,
                'progressPercent' => $progressPercent,
                'sentimentStats' => $sentimentStats,
                'thematicDrivers' => $thematicDrivers,
                'scheduleStatus' => $scheduleStatus,
                'scheduleMessage' => $scheduleMessage,
                'departmentStats' => $departmentStats,
                'roleTurnoutData' => $roleTurnoutData,
                'academicDeptScores' => $academicDeptScores,
                'adminDeptScores' => $adminDeptScores,
                'institutionalAverage' => $institutionalAverage,
                'ratingLabel' => $ratingLabel,
                'ratingBadgeClasses' => $ratingBadgeClasses,
                'ratingDelta' => $ratingDelta,
                'positivePct' => $positivePct,
                'negativePct' => $negativePct,
                'totalComments' => $sentimentStats['total'],
                'sentimentDelta' => $sentimentDelta,
                'completionDelta' => $completionDelta,
                'pendingDelta' => $pendingDelta,
                'totalEvaluatorsCount' => $totalEvaluatorsCount,
                'completedEvaluatorsCount' => $completedEvaluatorsCount,
                'pendingEvaluatorsCount' => $pendingEvaluatorsCount,
                'pendingStudentsCount' => $pendingStudentsCount ?? 0,
                'pendingEmployeesCount' => $pendingEmployeesCount ?? 0,
                'hasPrevComparison' => $hasPrevComparison,
                'currentSemName' => $currentSemName,
                'prevSemName' => $prevSemName,
                'prevRoleRates' => $prevRoleRates,
                'prevAcademicDeptRates' => $prevAcademicDeptRates,
                'prevAdminDeptRates' => $prevAdminDeptRates,
            ];
        });

        $employeeCount = $metrics['employeeCount'];
        $studentCount = $metrics['studentCount'];
        $userCount = $metrics['userCount'];
        $expectedCount = $metrics['expectedCount'];
        $submittedCount = $metrics['submittedCount'];
        $progressPercent = $metrics['progressPercent'];
        $sentimentStats = $metrics['sentimentStats'];
        $thematicDrivers = $metrics['thematicDrivers'] ?? ['has_data' => false, 'positive_drivers' => [], 'constructive_drivers' => []];
        $scheduleStatus = $metrics['scheduleStatus'];
        $scheduleMessage = $metrics['scheduleMessage'];
        $departmentStats = $metrics['departmentStats'];
        $roleTurnoutData = $metrics['roleTurnoutData'];
        $academicDeptScores = $metrics['academicDeptScores'];
        $adminDeptScores = $metrics['adminDeptScores'];
        $institutionalAverage = $metrics['institutionalAverage'];
        $ratingLabel = $metrics['ratingLabel'];
        $ratingBadgeClasses = $metrics['ratingBadgeClasses'];
        $ratingDelta = $metrics['ratingDelta'];
        $positivePct = $metrics['positivePct'];
        $negativePct = $metrics['negativePct'];
        $totalComments = $metrics['totalComments'];
        $sentimentDelta = $metrics['sentimentDelta'];
        $completionDelta = $metrics['completionDelta'];
        $pendingDelta = $metrics['pendingDelta'];
        $totalEvaluatorsCount = $metrics['totalEvaluatorsCount'];
        $completedEvaluatorsCount = $metrics['completedEvaluatorsCount'];
        $pendingEvaluatorsCount = $metrics['pendingEvaluatorsCount'];
        $pendingStudentsCount = $metrics['pendingStudentsCount'] ?? 0;
        $pendingEmployeesCount = $metrics['pendingEmployeesCount'] ?? 0;
        $hasPrevComparison = $metrics['hasPrevComparison'] ?? false;
        $currentSemName = $metrics['currentSemName'] ?? 'Current Term';
        $prevSemName = $metrics['prevSemName'] ?? 'Prior Term';
        $prevRoleRates = $metrics['prevRoleRates'] ?? [];
        $prevAcademicDeptRates = $metrics['prevAcademicDeptRates'] ?? [];
        $prevAdminDeptRates = $metrics['prevAdminDeptRates'] ?? [];

        // 7. Recent Submissions Anonymized Log
        $recentSubmissions = [];
        if ($activeSemId) {
            $evals = Evaluation::where('semester_id', $activeSemId)
                ->with(['evaluator.employee', 'evaluator.student', 'evaluatee.employee.department', 'evaluatee.student.program.department', 'class.subject'])
                ->latest()
                ->take(20)
                ->get();

            foreach ($evals as $eval) {
                // Determine evaluator role title
                $evaluatorRole = 'User';
                if ($eval->evaluator?->student) {
                    $evaluatorRole = 'Student';
                } elseif ($eval->evaluator?->employee) {
                    $role = strtolower($eval->evaluator->employee->role);
                    $evaluatorRole = match ($role) {
                        'dean' => 'Dean',
                        'program head', 'program_head' => 'Program Head',
                        'department head', 'department_head' => 'Department Head',
                        'faculty' => 'Professor',
                        'staff' => 'Staff',
                        default => 'Employee'
                    };
                }

                // Determine evaluatee role title
                $evaluateeRole = 'User';
                if ($eval->evaluatee?->student) {
                    $evaluateeRole = 'Student';
                } elseif ($eval->evaluatee?->employee) {
                    $role = strtolower($eval->evaluatee->employee->role);
                    $evaluateeRole = match ($role) {
                        'dean' => 'Dean',
                        'program head', 'program_head' => 'Program Head',
                        'department head', 'department_head' => 'Department Head',
                        'faculty' => 'Professor',
                        'staff' => 'Staff',
                        default => 'Employee'
                    };
                }

                $type = $eval->evaluation_type;

                // Anonymized label & description rules
                if ($type === 'self' || $eval->evaluator_id === $eval->evaluatee_id) {
                    $label = 'Self Evaluation';
                    $description = "{$evaluatorRole} evaluates Self";
                } elseif ($evaluatorRole === 'Student') {
                    $label = 'Student Evaluation';
                    $description = 'Student evaluates Professor';
                } elseif ($evaluatorRole === 'Dean') {
                    $label = 'Dean Evaluation';
                    $description = "Dean evaluates {$evaluateeRole}";
                } elseif ($evaluatorRole === 'Program Head' && in_array($evaluateeRole, ['Professor', 'Faculty'])) {
                    $label = 'Program Head Evaluation';
                    $description = 'Program Head evaluates Professor';
                } elseif ($evaluatorRole === 'Department Head') {
                    $label = 'Department Head Evaluation';
                    $description = "Department Head evaluates {$evaluateeRole}";
                } elseif ($evaluatorRole === 'Professor' && $evaluateeRole === 'Professor') {
                    $label = 'Peer Evaluation';
                    $description = 'Professor evaluates Professor';
                } elseif ($evaluatorRole === 'Staff' && $evaluateeRole === 'Staff') {
                    $label = 'Peer Evaluation';
                    $description = 'Staff evaluates Staff';
                } elseif ($type === 'upward_employee' || ($evaluatorRole === 'Program Head' && $evaluateeRole === 'Dean') || ($evaluatorRole === 'Professor' && $evaluateeRole === 'Program Head') || ($evaluatorRole === 'Staff' && in_array($evaluateeRole, ['Dean', 'Program Head', 'Department Head']))) {
                    $label = 'Supervisor Evaluation';
                    $description = "{$evaluatorRole} evaluates {$evaluateeRole}";
                } elseif ($evaluateeRole === 'Staff') {
                    $label = 'Staff Evaluation';
                    $description = "{$evaluatorRole} evaluates Staff";
                } else {
                    $label = match ($type) {
                        'upward_student' => 'Student Evaluation',
                        'peer' => 'Peer Evaluation',
                        'dean' => 'Dean Evaluation',
                        'downward' => 'Supervisor Evaluation',
                        'upward_employee' => 'Supervisor Evaluation',
                        default => 'Evaluation'
                    };
                    $description = "{$evaluatorRole} evaluates {$evaluateeRole}";
                }

                $subjectCode = $eval->class?->subject?->code;
                $sectionName = $eval->class?->section;
                $subjectDisplay = $subjectCode ? ($sectionName ? "Course: {$subjectCode} ({$sectionName})" : "Course: {$subjectCode}") : match ($type) {
                    'self' => 'Self Appraisal',
                    'peer' => 'Peer Review',
                    'downward' => 'Supervisor Review',
                    'upward_employee' => 'Supervisor Review',
                    'upward_student' => 'Class Evaluation',
                    default => 'Institutional Review'
                };

                $evaluateeName = $eval->evaluatee?->name ?? 'Faculty Member';
                $evaluateeDept = $eval->evaluatee?->employee?->department?->code ?? $eval->evaluatee?->student?->program?->department?->code ?? '';

                $categoryBadge = match ($label) {
                    'Student Evaluation' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-200 dark:border-blue-800',
                    'Peer Evaluation' => 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800',
                    'Self Evaluation' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
                    'Supervisor Evaluation', 'Dean Evaluation', 'Program Head Evaluation', 'Department Head Evaluation' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'
                };

                $recentSubmissions[] = [
                    'label' => $label,
                    'description' => $description,
                    'subject' => $subjectDisplay,
                    'evaluatee_name' => $evaluateeName,
                    'evaluatee_dept' => $evaluateeDept,
                    'category_badge' => $categoryBadge,
                    'time' => $eval->created_at->diffForHumans(),
                    'date_formatted' => $eval->created_at->format('M d, Y'),
                    'time_formatted' => $eval->created_at->format('h:i A'),
                    'full_time' => $eval->created_at->format('M d, Y h:i A'),
                ];
            }
        }

        // Determine static styling classes for sentiment
        $avg = $sentimentStats['average'];
        if ($avg > 0.05) {
            $sentimentTextClass = 'text-emerald-700 dark:text-emerald-400';
            $sentimentBadgeVariant = 'success';
            $sentimentLabel = 'Positive';
        } elseif ($avg < -0.05) {
            $sentimentTextClass = 'text-rose-700 dark:text-rose-400';
            $sentimentBadgeVariant = 'danger';
            $sentimentLabel = 'Negative';
        } else {
            $sentimentTextClass = 'text-zinc-600 dark:text-zinc-400';
            $sentimentBadgeVariant = 'neutral';
            $sentimentLabel = 'Neutral';
        }

        // 8. Analytics Visual Distribution & Department Averages
        $ratingsDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $totalRatingsCount = 0;
        $deptScores = [];

        if ($activeSemId) {
            $answers = DB::table('evaluation_answers')
                ->join('evaluations', 'evaluations.id', '=', 'evaluation_answers.evaluation_id')
                ->where('evaluations.semester_id', $activeSemId)
                ->selectRaw('evaluation_answers.rating, count(*) as total')
                ->groupBy('evaluation_answers.rating')
                ->pluck('total', 'rating');

            foreach ($ratingsDist as $rating => $val) {
                $ratingsDist[$rating] = (int) ($answers[$rating] ?? 0);
            }
            $totalRatingsCount = array_sum($ratingsDist);

            $depts = Department::where('type', 'academic')->orWhereNull('type')->orderBy('name')->get();

            $deptAveragesMap = DB::table('evaluations')
                ->join('users', 'users.id', '=', 'evaluations.evaluatee_id')
                ->join('employees', 'employees.id', '=', 'users.employee_id')
                ->where('evaluations.semester_id', $activeSemId)
                ->whereNotNull('employees.department_id')
                ->selectRaw('employees.department_id, count(*) as total_count, avg(evaluations.rating_average) as avg_rating')
                ->groupBy('employees.department_id')
                ->get()
                ->keyBy('department_id');

            foreach ($depts as $dept) {
                $data = $deptAveragesMap->get($dept->id);
                $dCount = (int) ($data?->total_count ?? 0);
                $dAvg = $dCount > 0 ? round((float) $data->avg_rating, 2) : 0.00;

                $deptScores[] = [
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'average' => $dAvg,
                    'count' => $dCount,
                ];
            }
        }

        // 9. Admin Audit Log Activities
        $auditLogs = [];
        $activities = Activity::with(['causer.roles', 'causer.employee'])
            ->where(function ($q) {
                $q->whereHasMorph('causer', [User::class], function ($sub) {
                    $sub->whereHas('roles', fn ($r) => $r->where('name', 'admin'))
                        ->orWhereHas('employee', fn ($e) => $e->where('role', 'admin'));
                })->orWhereNull('causer_id');
            })
            ->latest()
            ->take(30)
            ->get();

        foreach ($activities as $act) {
            $event = strtolower($act->event ?? $act->description ?? 'action');
            $subjectClass = class_basename($act->subject_type ?? '');
            $props = $act->properties ?? [];
            $attributes = $props['attributes'] ?? [];
            $old = $props['old'] ?? [];

            // Ignore activity entries that only touched internal timestamps
            $ignoredKeys = ['notifications_last_viewed_at', 'dismissed_notifications', 'password_changed_at', 'remember_token', 'updated_at', 'created_at'];
            $meaningfulAttrKeys = array_diff(array_keys($attributes), $ignoredKeys);

            if ($event === 'updated' && empty($meaningfulAttrKeys) && in_array($act->description, ['updated', 'created', 'deleted', null])) {
                continue;
            }

            // Check if this is a custom logged system message
            if ($act->description && ! in_array($act->description, ['created', 'updated', 'deleted', 'custom'])) {
                $eventTitle = 'System Operation';
                $eventLabel = 'OPERATION';
                $detailStr = $act->description;
                $badgeClass = 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800';
            } else {
                $subjectLabel = match ($subjectClass) {
                    'User' => 'User Account',
                    'Employee' => 'Employee Record',
                    'Student' => 'Student Record',
                    'Department' => 'Department',
                    'Program' => 'Academic Program',
                    'AcademicClass' => 'Class Section',
                    'Subject' => 'Subject',
                    'EvaluationQuestion' => 'Evaluation Question',
                    'EvaluationCriterion' => 'Evaluation Criterion',
                    'Evaluation' => 'Evaluation Record',
                    'Semester' => 'Evaluation Period & Schedule',
                    'AcademicYear' => 'Academic Year',
                    default => $subjectClass ?: 'System Record',
                };

                $eventLabel = strtoupper($event);
                $eventTitle = match ($event) {
                    'created' => "Created {$subjectLabel}",
                    'updated' => "Updated {$subjectLabel}",
                    'deleted' => "Deleted {$subjectLabel}",
                    default => ucfirst($event)." {$subjectLabel}",
                };

                $badgeClass = match ($event) {
                    'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                    'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400 border border-sky-200 dark:border-sky-800',
                    'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800',
                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
                };

                if ($subjectClass === 'User') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'User';
                    $email = $attributes['email'] ?? $old['email'] ?? $act->subject?->email ?? '';
                    if ($event === 'created') {
                        $detailStr = "Created user account for {$name}".($email ? " ({$email})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted user account for {$name}".($email ? " ({$email})" : '');
                    } elseif (isset($attributes['is_active'])) {
                        $statusText = $attributes['is_active'] ? 'Enabled' : 'Disabled';
                        $detailStr = "{$statusText} login access for {$name}";
                    } elseif (isset($attributes['show_ai_pipeline'])) {
                        $vis = $attributes['show_ai_pipeline'] ? 'visible' : 'hidden';
                        $detailStr = "Toggled AI Pipeline sidebar navigation to {$vis}";
                    } else {
                        $detailStr = "Updated profile for {$name}".($email ? " ({$email})" : '');
                    }
                } elseif ($subjectClass === 'Employee') {
                    $empNum = $attributes['employee_number'] ?? $old['employee_number'] ?? $act->subject?->employee_number ?? '';
                    $name = trim(($attributes['first_name'] ?? $old['first_name'] ?? '').' '.($attributes['last_name'] ?? $old['last_name'] ?? '')) ?: ($act->subject?->full_name ?? 'Employee');
                    $role = ucfirst($attributes['role'] ?? $old['role'] ?? $act->subject?->role ?? 'Staff');
                    if ($event === 'created') {
                        $detailStr = "Registered new {$role}: {$name}".($empNum ? " (ID: {$empNum})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted {$role} record for {$name}".($empNum ? " (ID: {$empNum})" : '');
                    } else {
                        $detailStr = "Updated {$role} details for {$name}".($empNum ? " (ID: {$empNum})" : '');
                    }
                } elseif ($subjectClass === 'Student') {
                    $studNum = $attributes['student_number'] ?? $old['student_number'] ?? $act->subject?->student_number ?? '';
                    $name = trim(($attributes['first_name'] ?? $old['first_name'] ?? '').' '.($attributes['last_name'] ?? $old['last_name'] ?? '')) ?: ($act->subject?->full_name ?? 'Student');
                    if ($event === 'created') {
                        $detailStr = "Enrolled new student: {$name}".($studNum ? " (SN: {$studNum})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted student record for {$name}".($studNum ? " (SN: {$studNum})" : '');
                    } else {
                        $detailStr = "Updated student profile for {$name}".($studNum ? " (SN: {$studNum})" : '');
                    }
                } elseif ($subjectClass === 'Department') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Department';
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $detailStr = ($event === 'created' ? 'Added new department: ' : ($event === 'deleted' ? 'Deleted department: ' : 'Updated department: '))."{$name}".($code ? " ({$code})" : '');
                } elseif ($subjectClass === 'Program') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Academic Program';
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $detailStr = ($event === 'created' ? 'Added new academic program: ' : ($event === 'deleted' ? 'Deleted program: ' : 'Updated program: '))."{$name}".($code ? " ({$code})" : '');
                } elseif ($subjectClass === 'AcademicClass') {
                    $section = $attributes['section'] ?? $old['section'] ?? $act->subject?->section ?? 'Section';
                    $detailStr = ($event === 'created' ? 'Created class section: ' : ($event === 'deleted' ? 'Deleted class section: ' : 'Updated class section: '))."{$section}";
                } elseif ($subjectClass === 'Subject') {
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $title = $attributes['title'] ?? $old['title'] ?? $act->subject?->title ?? 'Subject';
                    $detailStr = ($event === 'created' ? 'Added new subject: ' : ($event === 'deleted' ? 'Deleted subject: ' : 'Updated subject: '))."{$code} - {$title}";
                } elseif ($subjectClass === 'EvaluationQuestion') {
                    $qText = $attributes['question_text'] ?? $old['question_text'] ?? $act->subject?->question_text ?? '';
                    $snippet = $qText ? '"'.Str::limit($qText, 45).'"' : 'Evaluation question';
                    $detailStr = ($event === 'created' ? 'Added question: ' : ($event === 'deleted' ? 'Deleted question: ' : 'Modified question: ')).$snippet;
                } elseif ($subjectClass === 'EvaluationCriterion') {
                    $cName = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Criterion';
                    $detailStr = ($event === 'created' ? 'Created evaluation criterion: ' : ($event === 'deleted' ? 'Deleted criterion: ' : 'Updated criterion: '))."{$cName}";
                } elseif ($subjectClass === 'Semester') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Semester';
                    if (isset($attributes['is_evaluation_open'])) {
                        $state = $attributes['is_evaluation_open'] ? 'Opened' : 'Closed';
                        $detailStr = "{$state} active evaluation window for {$name}";
                    } elseif (isset($attributes['evaluation_starts_at']) || isset($attributes['evaluation_ends_at'])) {
                        $detailStr = "Configured evaluation schedule dates for {$name}";
                    } elseif (isset($attributes['student_weight']) || isset($attributes['overall_max_points'])) {
                        $detailStr = "Updated evaluation criteria weights & max points for {$name}";
                    } else {
                        $detailStr = "Updated semester settings for {$name}";
                    }
                } elseif ($subjectClass === 'AcademicYear') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Academic Year';
                    $detailStr = ($event === 'created' ? 'Created academic year: ' : ($event === 'deleted' ? 'Deleted academic year: ' : 'Updated academic year: '))."{$name}";
                } else {
                    $detailStr = "Administrative operation on {$subjectLabel}".($act->subject_id ? " (ID #{$act->subject_id})" : '');
                }
            }

            $causerName = $act->causer?->name ?? 'System Administrator';
            $causerRole = $act->causer?->employee?->role ? ucfirst($act->causer->employee->role) : ($act->causer ? 'Admin' : 'System');
            $moduleName = $subjectLabel ?? 'System';

            $auditLogs[] = [
                'title' => $eventTitle,
                'event' => $eventLabel,
                'module' => $moduleName,
                'description' => $detailStr,
                'causer' => $causerName,
                'causer_role' => $causerRole,
                'badge_class' => $badgeClass,
                'time' => $act->created_at ? $act->created_at->diffForHumans() : 'Recently',
                'date_formatted' => $act->created_at ? $act->created_at->format('M d, Y') : '',
                'time_formatted' => $act->created_at ? $act->created_at->format('h:i A') : '',
                'full_time' => $act->created_at ? $act->created_at->format('M d, Y h:i:s A') : '',
            ];
        }

        $pendingCount = max(0, $expectedCount - $submittedCount);

        $this->cachedData = [
            'activeSemester' => $activeSem,
            'activeYear' => $activeYear,
            'employeeCount' => $employeeCount,
            'studentCount' => $studentCount,
            'userCount' => $userCount,
            'expectedCount' => $expectedCount,
            'submittedCount' => $submittedCount,
            'pendingCount' => $pendingCount,
            'progressPercent' => $progressPercent,
            'sentimentStats' => $sentimentStats,
            'scheduleStatus' => $scheduleStatus,
            'scheduleMessage' => $scheduleMessage,
            'departmentStats' => $departmentStats,
            'recentSubmissions' => $recentSubmissions,
            'sentimentTextClass' => $sentimentTextClass,
            'sentimentBadgeVariant' => $sentimentBadgeVariant,
            'sentimentLabel' => $sentimentLabel,
            'roleTurnoutData' => $roleTurnoutData,
            'academicDeptScores' => $academicDeptScores,
            'adminDeptScores' => $adminDeptScores,
            'auditLogs' => $auditLogs,
            'institutionalAverage' => $institutionalAverage,
            'ratingLabel' => $ratingLabel,
            'ratingBadgeClasses' => $ratingBadgeClasses,
            'ratingDelta' => $ratingDelta,
            'positivePct' => $positivePct,
            'negativePct' => $negativePct,
            'totalComments' => $totalComments,
            'sentimentDelta' => $sentimentDelta,
            'completionDelta' => $completionDelta,
            'pendingDelta' => $pendingDelta,
            'totalEvaluatorsCount' => $totalEvaluatorsCount,
            'completedEvaluatorsCount' => $completedEvaluatorsCount,
            'pendingEvaluatorsCount' => $pendingEvaluatorsCount,
            'pendingStudentsCount' => $pendingStudentsCount,
            'pendingEmployeesCount' => $pendingEmployeesCount,
            'thematicDrivers' => $thematicDrivers,
            'hasPrevComparison' => $hasPrevComparison,
            'currentSemName' => $currentSemName,
            'prevSemName' => $prevSemName,
            'prevRoleRates' => $prevRoleRates,
            'prevAcademicDeptRates' => $prevAcademicDeptRates,
            'prevAdminDeptRates' => $prevAdminDeptRates,
        ];

        return $this->cachedData;
    }
}; ?>

<div class="w-full flex flex-col gap-8">
    <!-- Header Section with Academic Term & Live Status Context -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <div class="flex items-center gap-3 flex-wrap">
                <flux:heading size="xl" level="1" class="text-left font-extrabold tracking-tight">Admin Dashboard</flux:heading>
                @if($activeSemester)
                    <flux:badge variant="neutral" size="sm" class="font-bold shrink-0">
                        {{ $activeSemester->academicYear?->name }} &bull; {{ $activeSemester->name }}
                    </flux:badge>
                @endif
                @if($scheduleStatus === 'active')
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Evaluation Open
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-700">
                        <span class="size-1.5 rounded-full bg-zinc-400"></span>
                        Evaluation Closed
                    </span>
                @endif
            </div>

        </div>
        <div class="flex items-center gap-2">
            <flux:button href="/admin/evaluation-settings" variant="subtle" size="sm" icon="cog-6-tooth">
                Settings
            </flux:button>
            <flux:button href="/reports" variant="primary" size="sm" icon="printer" class="bg-[#9b0000] hover:bg-[#800000] text-white dark:bg-[#9b0000] dark:hover:bg-[#800000]">
                Reports
            </flux:button>
        </div>
    </div>

    <!-- Top Row: 4 Executive KPI Cards (Consistent Borders & Hierarchy) -->
    <!-- Top Row: 4 Executive KPI Cards (Consistent Height, Spacing & Aligned Baselines) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Card 1: Overall Institutional Rating -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] p-5.5 flex flex-col justify-between min-h-[196px]">
            <div>
                <div class="h-6 flex items-center justify-between">
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">Overall Institutional Rating</span>
                </div>
                <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                            <x-odometer :value="$institutionalAverage" decimals="2" />
                        </span>
                        <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">/ 5.00</span>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $ratingBadgeClasses }}">
                        {{ $ratingLabel }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs">
                    @if($ratingDelta !== null)
                        <span class="font-semibold tabular-nums {{ $ratingDelta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                            {{ $ratingDelta >= 0 ? '▲ +' : '▼ ' }}{{ number_format($ratingDelta, 2) }} vs last sem
                        </span>
                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                    @endif
                    <span class="text-zinc-500 dark:text-zinc-400 font-medium">Target: 3.50+</span>
                </div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-auto pt-3.5 block font-normal leading-relaxed">
                Institutional rating mean across all 360° evaluation roles
            </span>
        </div>

        <!-- Card 2: Positive Feedback Rate -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] p-5.5 flex flex-col justify-between min-h-[196px]">
            <div>
                <div class="h-6 flex items-center justify-between">
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">Positive Feedback Rate</span>
                </div>
                <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                        <x-odometer :value="$positivePct" suffix="%" />
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                        Positive
                    </span>
                </div>
                <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs">
                    @if($negativePct > 0)
                        <span class="inline-flex items-center gap-1 font-semibold text-rose-700 dark:text-rose-400 tabular-nums">
                            <span class="size-1.5 rounded-full bg-rose-500"></span>
                            {{ $negativePct }}% flagged for review
                        </span>
                    @else
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium">0% negative flags</span>
                    @endif
                    @if($sentimentDelta !== null)
                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                        <span class="font-semibold tabular-nums {{ $sentimentDelta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                            {{ $sentimentDelta >= 0 ? '▲ +' : '▼ ' }}{{ number_format($sentimentDelta, 1) }}% vs last sem
                        </span>
                    @endif
                </div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-auto pt-3.5 block font-normal leading-relaxed">
                Based on {{ number_format($totalComments) }} evaluator comments analyzed
            </span>
        </div>

        <!-- Card 3: Overall Completion Rate -->
        @php
            $progressColorClass = match (true) {
                $progressPercent >= 80 => 'bg-emerald-600 dark:bg-emerald-500',
                $progressPercent >= 50 => 'bg-amber-500 dark:bg-amber-400',
                default => 'bg-rose-600 dark:bg-rose-500',
            };
        @endphp
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] p-5.5 flex flex-col justify-between min-h-[196px]">
            <div>
                <div class="h-6 flex items-center justify-between">
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">Overall Completion Rate</span>
                </div>
                <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                        <x-odometer :value="$progressPercent" suffix="%" />
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $progressPercent >= 80 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : ($progressPercent >= 50 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800') }}">
                        {{ $progressPercent >= 80 ? 'Target Met' : 'In Progress' }}
                    </span>
                </div>
                <div class="mt-2.5 space-y-1.5">
                    <div class="w-full bg-zinc-200/80 dark:bg-zinc-700 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full transition-all duration-500 {{ $progressColorClass }}" style="width: {{ max(0, min(100, (float)$progressPercent)) }}% !important;"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        @if($completionDelta !== null)
                            <span class="font-semibold tabular-nums {{ $completionDelta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                {{ $completionDelta >= 0 ? '▲ +' : '▼ ' }}{{ number_format($completionDelta, 1) }}% vs last sem
                            </span>
                        @else
                            <span>Active Period</span>
                        @endif
                        <span class="font-medium">Target: 80%</span>
                    </div>
                </div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-auto pt-3.5 block font-normal leading-relaxed tabular-nums">
                {{ number_format($completedEvaluatorsCount) }} / {{ number_format($totalEvaluatorsCount) }} evaluators completed all assigned evaluations
            </span>
        </div>

        <!-- Card 4: Pending Evaluators -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] p-5.5 flex flex-col justify-between min-h-[196px]">
            <div>
                <div class="h-6 flex items-center justify-between gap-2">
                    <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">Pending Evaluators</span>
                    <flux:button wire:click="sendReminderToast" wire:loading.attr="disabled" variant="subtle" size="xs" icon="bell" class="font-bold text-[#9b0000] dark:text-[#f89696] hover:bg-zinc-100 dark:hover:bg-zinc-800 shrink-0 cursor-pointer h-6 text-[11px]">
                        Send Reminder
                    </flux:button>
                </div>
                <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                    <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                        <x-odometer :value="$pendingEvaluatorsCount" />
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $pendingEvaluatorsCount > 0 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' }}">
                        {{ $pendingEvaluatorsCount > 0 ? 'Pending' : 'All Clear' }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs">
                    @if($pendingDelta !== null && $pendingDelta > 0)
                        <span class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                            {{ number_format($pendingDelta) }} completed in past 7 days
                        </span>
                    @else
                        <span class="text-zinc-500 dark:text-zinc-400 font-medium">
                            0 completed in past 7 days
                        </span>
                    @endif
                </div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-auto pt-3.5 block font-normal leading-relaxed tabular-nums">
                {{ number_format($pendingStudentsCount) }} students &bull; {{ number_format($pendingEmployeesCount) }} employees pending
            </span>
        </div>
    </div>

    <!-- Analytics Charts Row: Evaluator Role Turnout & Department Performance Benchmark -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" x-data="dashboardAnalyticsCharts({
        currentSemName: {{ json_encode($currentSemName) }},
        prevSemName: {{ json_encode($prevSemName) }},
        hasPrevComparison: {{ json_encode($hasPrevComparison) }},
        roleLabels: {{ json_encode(array_values(array_column($roleTurnoutData, 'role'))) }},
        roleRates: {{ json_encode(array_values(array_column($roleTurnoutData, 'rate'))) }},
        prevRoleRates: {{ json_encode($prevRoleRates) }},
        roleDetails: {{ json_encode($roleTurnoutData) }},
        academicDeptLabels: {{ json_encode(array_values(array_column($academicDeptScores, 'code'))) }},
        academicDeptNames: {{ json_encode(array_values(array_column($academicDeptScores, 'name'))) }},
        academicDeptRates: {{ json_encode(array_values(array_column($academicDeptScores, 'rate'))) }},
        prevAcademicDeptRates: {{ json_encode($prevAcademicDeptRates) }},
        academicDeptSubmitted: {{ json_encode(array_values(array_column($academicDeptScores, 'submitted'))) }},
        academicDeptExpected: {{ json_encode(array_values(array_column($academicDeptScores, 'expected'))) }},
        adminDeptLabels: {{ json_encode(array_values(array_column($adminDeptScores, 'code'))) }},
        adminDeptNames: {{ json_encode(array_values(array_column($adminDeptScores, 'name'))) }},
        adminDeptRates: {{ json_encode(array_values(array_column($adminDeptScores, 'rate'))) }},
        prevAdminDeptRates: {{ json_encode($prevAdminDeptRates) }},
        adminDeptSubmitted: {{ json_encode(array_values(array_column($adminDeptScores, 'submitted'))) }},
        adminDeptExpected: {{ json_encode(array_values(array_column($adminDeptScores, 'expected'))) }}
    })">
        <!-- Chart 1: Evaluation Turnout by Evaluator Role -->
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[400px]">
            <div class="flex items-center justify-between gap-2 sm:gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <h2 class="text-sm sm:text-base font-bold text-zinc-900 dark:text-zinc-100 truncate">
                    Completion Rate by Role
                </h2>
            </div>

            <!-- Fixed-height Utility / Sub-bar with Persistent Active Term Legend -->
            <div class="h-6 flex items-center justify-center text-xs px-0.5">
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-zinc-700 dark:text-zinc-300">
                        <span class="size-2 rounded-xs bg-[#d97706] dark:bg-[#f59e0b]"></span>
                        <span x-text="currentSemName" title="Current Active Semester" class="max-w-[120px] sm:max-w-[150px] truncate"></span>
                    </span>
                    <span x-show="compareRole" x-cloak class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                    <span x-show="compareRole" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-zinc-500 dark:text-zinc-400">
                        <span class="size-2 rounded-xs bg-zinc-400 dark:bg-zinc-500"></span>
                        <span x-text="prevSemName" title="Prior Historical Semester" class="max-w-[120px] sm:max-w-[150px] truncate"></span>
                    </span>
                </div>
            </div>

            @if(count($roleTurnoutData) > 0)
                <div class="h-72 w-full pt-1.5">
                    <canvas x-ref="roleTurnoutChart" class="w-full h-full"></canvas>
                </div>

                <!-- Footer Action Toolbar -->
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:modal.trigger name="role-breakdown-modal">
                        <button type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#9b0000] dark:text-[#f89696] hover:text-[#800000] dark:hover:text-[#fca5a5] transition-colors cursor-pointer group">
                            <flux:icon name="arrow-top-right-on-square" class="size-3.5 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                            <span>View detailed breakdown</span>
                        </button>
                    </flux:modal.trigger>

                    <template x-if="hasPrevComparison">
                        <button type="button" @click="toggleComparison('role')" 
                            :title="compareRole ? 'Hide Comparison' : 'Compare vs Last Sem'"
                            :aria-label="compareRole ? 'Hide Comparison' : 'Compare vs Last Sem'"
                            :class="compareRole ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-xs border-zinc-900 dark:border-zinc-100' : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/60'"
                            class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer shrink-0">
                            <flux:icon name="arrows-right-left" class="size-3.5" />
                            <span class="hidden sm:inline" x-text="compareRole ? 'Hide Comparison' : 'Compare vs Last Sem'"></span>
                            <span class="sm:hidden" x-text="compareRole ? 'Hide' : 'Compare'"></span>
                        </button>
                    </template>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-8 flex-1 gap-2 h-72">
                    <flux:icon name="chart-bar" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">No evaluator submissions recorded for this period.</p>
                </div>
            @endif
        </div>

        <!-- Chart 2: Completion Rate by Department -->
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[400px]">
            <div class="flex items-center justify-between gap-2.5 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <h2 class="text-sm sm:text-base font-bold text-zinc-900 dark:text-zinc-100 truncate">
                    Completion Rate by Department
                </h2>

                <!-- Academic vs Administrative Toggle (Anchored on the right, no layout shift) -->
                <div class="flex items-center gap-0.5 sm:gap-1 p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs shrink-0">
                    <button type="button" @click="switchDeptType('academic')" :class="activeDeptType === 'academic' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer text-xs">
                        Academic
                    </button>
                    <button type="button" @click="switchDeptType('administrative')" :class="activeDeptType === 'administrative' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-2.5 py-1 rounded-md font-semibold transition-colors cursor-pointer text-xs">
                        Admin
                    </button>
                </div>
            </div>

            <!-- Fixed-height Utility / Sub-bar with Persistent Active Term Legend -->
            <div class="h-6 flex items-center justify-center text-xs px-0.5">
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 font-semibold text-zinc-700 dark:text-zinc-300">
                        <span class="size-2 rounded-xs bg-[#d97706] dark:bg-[#f59e0b]"></span>
                        <span x-text="currentSemName" title="Current Active Semester" class="max-w-[120px] sm:max-w-[150px] truncate"></span>
                    </span>
                    <span x-show="compareDept" x-cloak class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                    <span x-show="compareDept" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-zinc-500 dark:text-zinc-400">
                        <span class="size-2 rounded-xs bg-zinc-400 dark:bg-zinc-500"></span>
                        <span x-text="prevSemName" title="Prior Historical Semester" class="max-w-[120px] sm:max-w-[150px] truncate"></span>
                    </span>
                </div>
            </div>

            <div class="h-72 w-full pt-1.5">
                <canvas x-ref="deptChart" class="w-full h-full"></canvas>
            </div>

            <!-- Footer Action Toolbar -->
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.trigger name="dept-breakdown-modal">
                    <button type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#9b0000] dark:text-[#f89696] hover:text-[#800000] dark:hover:text-[#fca5a5] transition-colors cursor-pointer group">
                        <flux:icon name="arrow-top-right-on-square" class="size-3.5 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
                        <span>View detailed breakdown</span>
                    </button>
                </flux:modal.trigger>

                <template x-if="hasPrevComparison">
                    <button type="button" @click="toggleComparison('dept')" 
                        :title="compareDept ? 'Hide Comparison' : 'Compare vs Last Sem'"
                        :aria-label="compareDept ? 'Hide Comparison' : 'Compare vs Last Sem'"
                        :class="compareDept ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-xs border-zinc-900 dark:border-zinc-100' : 'bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700/60'"
                        class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-all cursor-pointer shrink-0">
                        <flux:icon name="arrows-right-left" class="size-3.5" />
                        <span class="hidden sm:inline" x-text="compareDept ? 'Hide Comparison' : 'Compare vs Last Sem'"></span>
                        <span class="sm:hidden" x-text="compareDept ? 'Hide' : 'Compare'"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Middle Row: Evaluation Window Status & Overall Feedback Insights -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Panel 1: Evaluation Period Status -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Evaluation Period Status
                    </h2>
                </div>
                @if($activeSemester)
                    <flux:badge variant="neutral" size="sm" class="font-bold shrink-0">
                        {{ $activeSemester->academicYear?->name }} • {{ $activeSemester->name }}
                    </flux:badge>
                @endif
            </div>

            @if($activeSemester)
                @php
                    $startAt = $activeSemester->evaluation_starts_at;
                    $endAt = $activeSemester->evaluation_ends_at;
                    $now = \Illuminate\Support\Carbon::now('Asia/Manila');

                    $totalDays = ($startAt && $endAt) ? max(1, (int) round($startAt->diffInDays($endAt))) : null;
                    $elapsedDays = ($startAt && $now->greaterThan($startAt)) ? min($totalDays ?? 1, (int) round($startAt->diffInDays($now))) : 0;
                    $remainingDays = ($endAt && $endAt->greaterThan($now)) ? max(0, (int) round($now->diffInDays($endAt))) : 0;

                    $windowProgressPct = ($startAt && $endAt && $endAt->greaterThan($startAt))
                        ? min(100, max(0, round(($startAt->diffInSeconds($now) / max(1, $startAt->diffInSeconds($endAt))) * 100)))
                        : 0;
                @endphp

                <div class="space-y-3.5 flex-1 flex flex-col justify-between">
                    <!-- Status Banner -->
                    @if($scheduleStatus === 'active')
                        <div class="bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-800 p-3.5 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="size-3.5 rounded-full bg-emerald-500 animate-pulse flex-shrink-0"></span>
                                <div>
                                    <p class="text-sm font-bold text-emerald-900 dark:text-emerald-300">EVALUATION IS OPEN</p>
                                    <p class="text-xs text-emerald-700 dark:text-emerald-400">Evaluators can submit evaluation forms right now.</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 p-3.5 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="size-3.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                                <div>
                                    <p class="text-sm font-bold text-amber-900 dark:text-amber-300">EVALUATION IS CLOSED</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400">No evaluation forms can be submitted right now.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Scheduled Window & Timeline Progress Box -->
                    <div class="bg-zinc-50 dark:bg-zinc-800/40 p-5 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex-1 flex flex-col justify-between gap-4">
                        <div class="flex items-center justify-between border-b border-zinc-200/80 dark:border-zinc-700/50 pb-2.5">
                            <span class="text-xs font-bold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider">
                                Scheduled Window
                            </span>
                            <span class="text-xs font-semibold text-zinc-800 dark:text-zinc-200">
                                {{ $scheduleMessage }}
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-1">
                            <div>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Opens</span>
                                <span class="text-base font-extrabold text-zinc-900 dark:text-zinc-100 block mt-1">
                                    {{ $activeSemester->evaluation_starts_at ? $activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A') : 'Not Set' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Closes</span>
                                <span class="text-base font-extrabold text-zinc-900 dark:text-zinc-100 block mt-1">
                                    {{ $activeSemester->evaluation_ends_at ? $activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A') : 'Not Set' }}
                                </span>
                            </div>
                        </div>

                        @if($startAt && $endAt)
                            <div class="pt-3 border-t border-zinc-200/60 dark:border-zinc-700/40 space-y-2">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-medium text-zinc-600 dark:text-zinc-400">
                                        @if($scheduleStatus === 'active')
                                            Timeline: Day {{ max(1, $elapsedDays) }} of {{ $totalDays }} ({{ $windowProgressPct }}% elapsed)
                                        @elseif($now->lt($startAt))
                                            Opens in {{ $now->diffForHumans($startAt, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]) }}
                                        @else
                                            Window ended {{ $endAt->diffForHumans() }}
                                        @endif
                                    </span>
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">
                                        @if($scheduleStatus === 'active')
                                            {{ $remainingDays }} {{ \Illuminate\Support\Str::plural('day', $remainingDays) }} left
                                        @endif
                                    </span>
                                </div>
                                <div class="w-full bg-zinc-200 dark:bg-zinc-700/80 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-emerald-500 dark:bg-emerald-400 h-2.5 rounded-full transition-all duration-500" style="width: {{ $windowProgressPct }}%"></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-auto">
                        <flux:button href="/admin/evaluation-settings" variant="primary" size="sm" icon="cog">
                            Edit Schedule
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="exclamation-circle" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 font-medium">No active academic period configured.</p>
                    <flux:button href="/admin/evaluation-settings" variant="primary" size="sm" class="mt-2">
                        Configure Period
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- Panel 2: Overall Evaluation Feedback Overview -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            @php
                $posCount = $sentimentStats['positive'];
                $neuCount = $sentimentStats['neutral'];
                $negCount = $sentimentStats['negative'];
                $total = $sentimentStats['total'];

                $posPct = $total > 0 ? round(($posCount / $total) * 100, 1) : 0;
                $neuPct = $total > 0 ? round(($neuCount / $total) * 100, 1) : 0;
                $negPct = $total > 0 ? round(($negCount / $total) * 100, 1) : 0;
            @endphp
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Overall Evaluation Feedback
                    </h2>

                </div>
                @if($total > 0)
                    <flux:badge variant="neutral" size="sm" class="font-bold shrink-0">
                        {{ number_format($total) }} Reviews
                    </flux:badge>
                @endif
            </div>

            @if($sentimentStats['total'] > 0)
                <div class="space-y-4 flex-1 flex flex-col justify-between">
                    <!-- 3 Stat Blocks -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="bg-[#dffbee] dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 p-2 sm:p-3 rounded-xl text-center">
                            <span class="text-[10px] sm:text-xs font-bold text-[#035e44] dark:text-[#03dd9f] uppercase tracking-wider block">Positive</span>
                            <span class="text-lg sm:text-2xl font-black text-[#035e44] dark:text-[#03dd9f] block mt-0.5 sm:mt-1 tabular-nums tracking-tight">{{ number_format($posCount) }}</span>
                            <span class="text-[11px] sm:text-xs text-[#035e44] dark:text-[#03dd9f] font-semibold tabular-nums">{{ $posPct }}%</span>
                        </div>
                        <div class="bg-[#fcf6e4] dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/50 p-2 sm:p-3 rounded-xl text-center">
                            <span class="text-[10px] sm:text-xs font-bold text-[#843c06] dark:text-[#f7a15e] uppercase tracking-wider block">Neutral</span>
                            <span class="text-lg sm:text-2xl font-black text-[#843c06] dark:text-[#f7a15e] block mt-0.5 sm:mt-1 tabular-nums tracking-tight">{{ number_format($neuCount) }}</span>
                            <span class="text-[11px] sm:text-xs text-[#843c06] dark:text-[#f7a15e] font-semibold tabular-nums">{{ $neuPct }}%</span>
                        </div>
                        <div class="bg-[#fff1f2] dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/50 p-2 sm:p-3 rounded-xl text-center">
                            <span class="text-[10px] sm:text-xs font-bold text-[#a30f34] dark:text-[#f89bb2] uppercase tracking-wider block">Negative</span>
                            <span class="text-lg sm:text-2xl font-black text-[#a30f34] dark:text-[#f89bb2] block mt-0.5 sm:mt-1 tabular-nums tracking-tight">{{ number_format($negCount) }}</span>
                            <span class="text-[11px] sm:text-xs text-[#a30f34] dark:text-[#f89bb2] font-semibold tabular-nums">{{ $negPct }}%</span>
                        </div>
                    </div>

                    <!-- AI-Extracted Key Feedback Highlights -->
                    @if(!empty($thematicDrivers['has_data']))
                        <div class="pt-3 border-t border-zinc-100 dark:border-zinc-800 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider flex items-center gap-1.5">
                                    <flux:icon name="sparkles" class="size-3.5 text-amber-500 shrink-0" />
                                    Key Feedback Highlights
                                </span>
                                <span class="text-[10px] text-zinc-500 dark:text-zinc-400 font-medium">Top recurring comment topics</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- Commendations / Strengths -->
                                <div class="p-3 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/70 dark:border-emerald-800/40 flex flex-col gap-2">
                                    <div class="flex items-center justify-between border-b border-emerald-100 dark:border-emerald-900/40 pb-1.5">
                                        <span class="text-[11px] font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wide">Top Strengths</span>
                                        <span class="text-[10px] text-emerald-700 dark:text-emerald-300 font-semibold">Praise</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        @forelse($thematicDrivers['positive_drivers'] as $pDriver)
                                            <div class="flex items-center justify-between gap-3 text-xs" title="{{ number_format($pDriver['count']) }} comments highlighted this strength">
                                                <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $pDriver['term'] }}</span>
                                                <span class="text-[11px] font-mono font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-100/70 dark:bg-emerald-900/50 px-2 py-0.5 rounded-md shrink-0 whitespace-nowrap">{{ number_format($pDriver['count']) }} mentions</span>
                                            </div>
                                        @empty
                                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400 italic">No recurring praise detected yet.</span>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Areas for Improvement / Focus Areas -->
                                <div class="p-3 rounded-xl bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/70 dark:border-rose-800/40 flex flex-col gap-2">
                                    <div class="flex items-center justify-between border-b border-rose-100 dark:border-rose-900/40 pb-1.5">
                                        <span class="text-[11px] font-bold text-rose-800 dark:text-rose-300 uppercase tracking-wide">Focus Areas</span>
                                        <span class="text-[10px] text-rose-700 dark:text-rose-300 font-semibold">Needs Attention</span>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        @forelse($thematicDrivers['constructive_drivers'] as $cDriver)
                                            <div class="flex items-center justify-between gap-3 text-xs" title="{{ number_format($cDriver['count']) }} comments highlighted this focus area">
                                                <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $cDriver['term'] }}</span>
                                                <span class="text-[11px] font-mono font-semibold text-rose-700 dark:text-rose-300 bg-rose-100/70 dark:bg-rose-950/50 px-2 py-0.5 rounded-md shrink-0 whitespace-nowrap">{{ number_format($cDriver['count']) }} mentions</span>
                                            </div>
                                        @empty
                                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400 italic">No recurring issues detected.</span>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="adjustments-horizontal" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 font-medium">No evaluation feedback comments available yet.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Unified High-Density Activity & Completion Table (Tables Over Cards) -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex flex-col overflow-hidden" x-data="{ activeTab: 'audit' }">
        <!-- Tab Header Bar -->
        <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    System Activity & Progress Logs
                </h2>
            </div>

            <!-- Tab Switcher -->
            <div class="flex items-center gap-1 overflow-x-auto max-w-full p-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs shrink-0">
                <button type="button" @click="activeTab = 'audit'" :class="activeTab === 'audit' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-3 py-1.5 rounded-md font-semibold transition-colors shrink-0 whitespace-nowrap">
                    Audit Log ({{ count($auditLogs) }})
                </button>
                <button type="button" @click="activeTab = 'submissions'" :class="activeTab === 'submissions' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-3 py-1.5 rounded-md font-semibold transition-colors shrink-0 whitespace-nowrap">
                    Recent Submissions ({{ count($recentSubmissions) }})
                </button>
            </div>
        </div>

        <!-- Tab 1: Audit Log Table -->
        <div x-cloak x-show="activeTab === 'audit'" class="max-h-[380px] overflow-y-auto overflow-x-auto">
            @if(count($auditLogs) > 0)
                <table class="w-full text-left border-collapse text-xs min-w-[720px] lg:min-w-0 lg:table-fixed">
                    <thead class="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 shadow-2xs">
                        <tr class="text-zinc-500 dark:text-zinc-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-5 w-44 lg:w-[18%]">Timestamp</th>
                            <th class="py-3.5 px-5 w-28 lg:w-[12%]">Event</th>
                            <th class="py-3.5 px-5 w-36 lg:w-[18%]">Module</th>
                            <th class="py-3.5 px-5 w-44 lg:w-[18%]">Actor</th>
                            <th class="py-3.5 px-5 min-w-[220px] lg:w-[34%]">Operation & Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 bg-white dark:bg-zinc-900">
                        @foreach($auditLogs as $log)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3.5 px-5 whitespace-nowrap" title="{{ $log['full_time'] }}">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $log['date_formatted'] }}</div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $log['time_formatted'] }} &bull; {{ $log['time'] }}</div>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $log['badge_class'] }}">
                                        {{ $log['event'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <span class="inline-block max-w-full truncate px-2 py-0.5 rounded text-[11px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700" title="{{ $log['module'] }}">
                                        {{ $log['module'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate" title="{{ $log['causer'] }}">{{ $log['causer'] }}</div>
                                    <div class="text-[10px] uppercase font-semibold text-zinc-500 dark:text-zinc-400 tracking-wider truncate">{{ $log['causer_role'] }}</div>
                                </td>
                                <td class="py-3.5 px-5 text-zinc-700 dark:text-zinc-300">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $log['title'] }}:</span>
                                    <span>{{ $log['description'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="flex flex-col items-center justify-center text-center p-10 gap-2">
                    <flux:icon name="clock" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">No administrator activity logs recorded yet.</p>
                </div>
            @endif
        </div>

        <!-- Tab 2: Recent Submissions Table -->
        <div x-cloak x-show="activeTab === 'submissions'" class="max-h-[380px] overflow-y-auto overflow-x-auto" style="display: none;">
            @if(count($recentSubmissions) > 0)
                <table class="w-full text-left border-collapse text-xs min-w-[720px] lg:min-w-0 lg:table-fixed">
                    <thead class="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 shadow-2xs">
                        <tr class="text-zinc-500 dark:text-zinc-400 uppercase tracking-wider font-semibold">
                            <th class="py-3.5 px-5 w-44 lg:w-[18%]">Submitted</th>
                            <th class="py-3.5 px-5 w-40 lg:w-[18%]">Evaluation Type</th>
                            <th class="py-3.5 px-5 w-44 lg:w-[22%]">Subject / Scope</th>
                            <th class="py-3.5 px-5 w-44 lg:w-[18%]">Target Faculty</th>
                            <th class="py-3.5 px-5 min-w-[200px] lg:w-[24%]">Evaluation Flow</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/60 bg-white dark:bg-zinc-900">
                        @foreach($recentSubmissions as $sub)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3.5 px-5 whitespace-nowrap" title="{{ $sub['full_time'] }}">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $sub['date_formatted'] }}</div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $sub['time_formatted'] }} &bull; {{ $sub['time'] }}</div>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $sub['category_badge'] }} truncate max-w-full">
                                        {{ $sub['label'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap font-medium">
                                    <span class="inline-block max-w-full truncate px-2 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 font-semibold text-zinc-800 dark:text-zinc-200" title="{{ $sub['subject'] }}">
                                        {{ $sub['subject'] }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate" title="{{ $sub['evaluatee_name'] }}">{{ $sub['evaluatee_name'] }}</div>
                                    @if($sub['evaluatee_dept'])
                                        <div class="text-[10px] text-zinc-500 dark:text-zinc-400 font-semibold uppercase">{{ $sub['evaluatee_dept'] }}</div>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-zinc-700 dark:text-zinc-300">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="truncate" title="{{ $sub['description'] }}">{{ $sub['description'] }}</span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 shrink-0">
                                            <flux:icon name="check" class="size-3" /> Submitted
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="flex flex-col items-center justify-center text-center p-10 gap-2">
                    <flux:icon name="inbox" class="size-8 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">No submissions recorded for this active semester.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick System Actions (Structured into 3 Functional Groups) -->
    <div class="flex flex-col gap-8 mt-2">
        <div>
            <flux:heading size="lg">Quick System Actions</flux:heading>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">Direct shortcuts to evaluation monitoring, questionnaire configuration, and master user records.</p>
        </div>

        <!-- 1. Evaluation Monitoring & Reports -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">Evaluation Monitoring & Reports</span>
                <flux:badge variant="neutral" size="sm">4 shortcuts</flux:badge>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. Track Submissions -->
                <a href="/manage-evaluations" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="chart-pie" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Track Submissions</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">See who has submitted and send deadline reminders.</span>
                    </div>
                </a>

                <!-- 2. View Results -->
                <a href="/evaluation-results" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="clipboard-document-list" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">View Results</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">View raw scores, question ratings, and comments.</span>
                    </div>
                </a>

                <!-- 3. Generate Reports -->
                <a href="/reports" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="printer" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Generate Reports</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Export official 2-page scorecards and AI analysis to PDF.</span>
                    </div>
                </a>

                <!-- 4. Compare Rankings -->
                <a href="/rankings" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="trophy" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Compare Rankings</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Compare top-rated teachers and highest-ranked colleges.</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- 2. Schedules & Questionnaires -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">Schedules & Questionnaires</span>
                <flux:badge variant="neutral" size="sm">4 shortcuts</flux:badge>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 5. Configure Settings -->
                <a href="/admin/evaluation-settings" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="cog-6-tooth" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Configure Settings</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Open or close periods and set score weight percentages.</span>
                    </div>
                </a>

                <!-- 6. Manage Questions -->
                <a href="/admin/questions" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="document-text" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Questions</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, edit, or reorder questions for all evaluation forms.</span>
                    </div>
                </a>

                <!-- 7. Assign Classes -->
                <a href="/admin/classes" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="queue-list" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Assign Classes</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Assign teachers to subjects and enroll students into sections.</span>
                    </div>
                </a>

                <!-- 8. Manage Subjects -->
                <a href="/admin/subjects" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="book-open" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Subjects</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, edit, or import course subjects and unit credits.</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- 3. User Accounts & Organization -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">User Accounts & Organization</span>
                <flux:badge variant="neutral" size="sm">4 shortcuts</flux:badge>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 9. Manage Students -->
                <a href="/admin/students" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="academic-cap" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Students</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, edit, import, or search student profiles and status.</span>
                    </div>
                </a>

                <!-- 10. Manage Employees -->
                <a href="/admin/employees" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="users" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Employees</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Manage faculty, deans, department heads, and staff accounts.</span>
                    </div>
                </a>

                <!-- 11. Manage Departments -->
                <a href="/admin/departments" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="building-office-2" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Departments</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Configure academic colleges, offices, and assign heads.</span>
                    </div>
                </a>

                <!-- 12. Manage Programs -->
                <a href="/admin/programs" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                        <flux:icon name="academic-cap" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Programs</span>
                        <span class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5 block line-clamp-2">Setup degree courses (BSIT, BSA, etc.) and assign program heads.</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Broadcast Reminders Confirmation Modal -->
    <flux:modal wire:model="showReminderModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-md !p-4 sm:!p-6">
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-950/40 flex items-center justify-center text-amber-700 dark:text-amber-400">
                    <flux:icon icon="bell-alert" variant="outline" class="size-5" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                        Broadcast Evaluation Reminders
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                        Are you sure you want to broadcast completion reminders?
                    </p>
                </div>
            </div>

            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-2 text-xs text-zinc-600 dark:text-zinc-300">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">Active Period:</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $activeSemester ? $activeSemester->name : 'N/A' }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-zinc-500 dark:text-zinc-400">Pending Evaluators:</span>
                    <span class="font-bold text-[#9b0000] dark:text-[#f89696]">{{ number_format($pendingEvaluatorsCount) }} evaluators</span>
                </div>
                <p class="pt-2 border-t border-zinc-200/60 dark:border-zinc-700/50 text-zinc-500 dark:text-zinc-400">
                    This will send automated notification alerts to all students and employees who have not completed their evaluations.
                </p>
            </div>

            <div class="flex justify-end items-center gap-3">
                <flux:button wire:click="$set('showReminderModal', false)" variant="subtle" size="sm">
                    Cancel
                </flux:button>
                <flux:button wire:click="confirmBroadcastReminders" wire:loading.attr="disabled" variant="primary" size="sm" class="bg-[#9b0000] hover:bg-[#800000] text-white dark:bg-[#9b0000] dark:hover:bg-[#800000]">
                    <span wire:loading.remove wire:target="confirmBroadcastReminders">Broadcast Reminders</span>
                    <span wire:loading wire:target="confirmBroadcastReminders">Broadcasting...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Evaluator Turnout by Role Detailed Breakdown Modal -->
    <flux:modal name="role-breakdown-modal" class="w-[calc(100vw-2rem)] sm:w-full max-w-2xl !p-4 sm:!p-6 overflow-hidden">
        <div class="space-y-4 w-full min-w-0 max-w-full">
            <!-- Header (Exact Match with Chart Title, No Subheader) -->
            <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    Completion Rate by Role
                </h3>
            </div>

            <!-- Mobile scroll hint -->
            <div class="flex items-center justify-between text-[11px] text-zinc-400 dark:text-zinc-500 sm:hidden">
                <span>Scroll table horizontally to view all columns</span>
                <flux:icon name="arrows-right-left" class="size-3.5" />
            </div>

            <!-- Role Breakdown Table -->
            <div class="w-full min-w-0 max-w-full max-h-[60vh] overflow-y-auto overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                <table class="w-full text-left text-xs border-collapse min-w-[500px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-[11px] sticky top-0 bg-white dark:bg-zinc-900 z-10">
                            <th class="py-2.5 px-3 whitespace-nowrap">Role</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Submitted / Target</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Pending</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">Rate</th>
                            @if($hasPrevComparison)
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Prior Sem</th>
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Delta</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/70">
                        @foreach($roleTurnoutData as $roleItem)
                            @php
                                $rolePending = max(0, $roleItem['expected'] - $roleItem['submitted']);
                                $hasPrev = isset($roleItem['prev_rate']);
                                $delta = $hasPrev ? round($roleItem['rate'] - $roleItem['prev_rate'], 1) : null;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3 px-3 font-bold text-zinc-900 dark:text-zinc-100 text-sm whitespace-nowrap">
                                    {{ $roleItem['role'] }}
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($roleItem['submitted']) }}</span> / {{ number_format($roleItem['expected']) }}
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums font-semibold whitespace-nowrap {{ $rolePending > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                    {{ $rolePending > 0 ? number_format($rolePending) : 'Completed' }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-extrabold text-sm whitespace-nowrap {{ $roleItem['rate'] >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($roleItem['rate'] >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400') }}">
                                    {{ $roleItem['rate'] }}%
                                </td>
                                @if($hasPrevComparison)
                                    <td class="py-3 px-3 text-right tabular-nums font-medium text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                        {{ $hasPrev ? $roleItem['prev_rate'] . '%' : '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums font-extrabold whitespace-nowrap {{ $delta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        {{ $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    Total: <strong class="text-zinc-700 dark:text-zinc-300">{{ count($roleTurnoutData) }} roles</strong>
                </span>
                <flux:modal.close>
                    <flux:button variant="subtle" size="sm">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <!-- Department Completion Rate Detailed Breakdown Modal -->
    <flux:modal name="dept-breakdown-modal" class="w-[calc(100vw-2rem)] sm:w-full max-w-2xl !p-4 sm:!p-6 overflow-hidden">
        <div class="space-y-4 w-full min-w-0 max-w-full" x-data="{ modalDeptTab: 'academic' }">
            <!-- Header (Exact Match with Chart Title, No Subheader) -->
            <div class="pb-3 border-b border-zinc-200 dark:border-zinc-800">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    Completion Rate by Department
                </h3>
            </div>

            <!-- Department Type Segmented Switcher (Positioned cleanly below header, away from close button) -->
            <div class="flex items-center gap-1 p-0.5 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs w-fit max-w-full overflow-x-auto">
                <button type="button" @click="modalDeptTab = 'academic'" :class="modalDeptTab === 'academic' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-3 py-1.5 rounded-md font-semibold transition-colors cursor-pointer text-xs whitespace-nowrap">
                    Academic ({{ count($academicDeptScores) }})
                </button>
                <button type="button" @click="modalDeptTab = 'administrative'" :class="modalDeptTab === 'administrative' ? 'bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100'" class="px-3 py-1.5 rounded-md font-semibold transition-colors cursor-pointer text-xs whitespace-nowrap">
                    Administrative ({{ count($adminDeptScores) }})
                </button>
            </div>

            <!-- Mobile scroll hint -->
            <div class="flex items-center justify-between text-[11px] text-zinc-400 dark:text-zinc-500 sm:hidden">
                <span>Scroll table horizontally to view all columns</span>
                <flux:icon name="arrows-right-left" class="size-3.5" />
            </div>

            <!-- Academic Departments Table -->
            <div x-show="modalDeptTab === 'academic'" class="w-full min-w-0 max-w-full max-h-[60vh] overflow-y-auto overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                <table class="w-full text-left text-xs border-collapse min-w-[520px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-[11px] sticky top-0 bg-white dark:bg-zinc-900 z-10">
                            <th class="py-2.5 px-3 whitespace-nowrap">College</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Submitted / Target</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Pending</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">Rate</th>
                            @if($hasPrevComparison)
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Prior Sem</th>
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Delta</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/70">
                        @foreach($academicDeptScores as $deptItem)
                            @php
                                $deptPending = max(0, $deptItem['expected'] - $deptItem['submitted']);
                                $hasPrev = isset($deptItem['prev_rate']);
                                $delta = $hasPrev ? round($deptItem['rate'] - $deptItem['prev_rate'], 1) : null;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3 px-3">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm whitespace-nowrap">{{ $deptItem['code'] }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-xs ml-1.5 whitespace-nowrap">&mdash; {{ $deptItem['name'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($deptItem['submitted']) }}</span> / {{ number_format($deptItem['expected']) }}
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums font-semibold whitespace-nowrap {{ $deptPending > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                    {{ $deptPending > 0 ? number_format($deptPending) : 'Completed' }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-extrabold text-sm whitespace-nowrap {{ $deptItem['rate'] >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($deptItem['rate'] >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400') }}">
                                    {{ $deptItem['rate'] }}%
                                </td>
                                @if($hasPrevComparison)
                                    <td class="py-3 px-3 text-right tabular-nums font-medium text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                        {{ $hasPrev ? $deptItem['prev_rate'] . '%' : '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums font-extrabold whitespace-nowrap {{ $delta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        {{ $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Administrative Offices Table -->
            <div x-cloak x-show="modalDeptTab === 'administrative'" class="w-full min-w-0 max-w-full max-h-[60vh] overflow-y-auto overflow-x-auto rounded-lg border border-zinc-200/80 dark:border-zinc-800">
                <table class="w-full text-left text-xs border-collapse min-w-[520px]">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider text-[11px] sticky top-0 bg-white dark:bg-zinc-900 z-10">
                            <th class="py-2.5 px-3 whitespace-nowrap">Office</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Submitted / Target</th>
                            <th class="py-2.5 px-3 text-center whitespace-nowrap">Pending</th>
                            <th class="py-2.5 px-3 text-right whitespace-nowrap">Rate</th>
                            @if($hasPrevComparison)
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Prior Sem</th>
                                <th class="py-2.5 px-3 text-right whitespace-nowrap">Delta</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/70">
                        @foreach($adminDeptScores as $deptItem)
                            @php
                                $deptPending = max(0, $deptItem['expected'] - $deptItem['submitted']);
                                $hasPrev = isset($deptItem['prev_rate']);
                                $delta = $hasPrev ? round($deptItem['rate'] - $deptItem['prev_rate'], 1) : null;
                            @endphp
                            <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/30 transition-colors">
                                <td class="py-3 px-3">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm whitespace-nowrap">{{ $deptItem['code'] }}</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-xs ml-1.5 whitespace-nowrap">&mdash; {{ $deptItem['name'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums text-zinc-600 dark:text-zinc-400 font-medium whitespace-nowrap">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($deptItem['submitted']) }}</span> / {{ number_format($deptItem['expected']) }}
                                </td>
                                <td class="py-3 px-3 text-center tabular-nums font-semibold whitespace-nowrap {{ $deptPending > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                    {{ $deptPending > 0 ? number_format($deptPending) : 'Completed' }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-extrabold text-sm whitespace-nowrap {{ $deptItem['rate'] >= 80 ? 'text-emerald-700 dark:text-emerald-400' : ($deptItem['rate'] >= 50 ? 'text-amber-700 dark:text-amber-400' : 'text-rose-700 dark:text-rose-400') }}">
                                    {{ $deptItem['rate'] }}%
                                </td>
                                @if($hasPrevComparison)
                                    <td class="py-3 px-3 text-right tabular-nums font-medium text-zinc-500 dark:text-zinc-400 whitespace-nowrap">
                                        {{ $hasPrev ? $deptItem['prev_rate'] . '%' : '—' }}
                                    </td>
                                    <td class="py-3 px-3 text-right tabular-nums font-extrabold whitespace-nowrap {{ $delta >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        {{ $delta !== null ? ($delta >= 0 ? '+' : '') . $delta . '%' : '—' }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    <span x-show="modalDeptTab === 'academic'">Total: <strong class="text-zinc-700 dark:text-zinc-300">{{ count($academicDeptScores) }} academic colleges</strong></span>
                    <span x-cloak x-show="modalDeptTab === 'administrative'">Total: <strong class="text-zinc-700 dark:text-zinc-300">{{ count($adminDeptScores) }} administrative offices</strong></span>
                </span>
                <flux:modal.close>
                    <flux:button variant="subtle" size="sm">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>


