<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\User;
use App\Models\Employee;
use App\Models\Student;
use App\Models\Semester;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use App\Models\EvaluationAnswer;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.admin-dashboard-skeleton');
    }
    public function with(): array
    {
        // 1. Fetch Active Semester & Year
        $activeSem = Semester::where('is_active', true)->first();
        $activeYear = $activeSem ? $activeSem->academicYear : null;
        $activeSemId = $activeSem ? $activeSem->id : null;

        // 2. Counts
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

            // Expected Faculty Self evaluations (1 per active employee)
            $employeeSelfExpected = Employee::where('status', 'active')->count();

            // Expected Faculty Peer evaluations (peer count per department)
            $facultyInDepts = Employee::where('role', 'faculty')
                ->where('status', 'active')
                ->whereNotNull('department_id')
                ->get()
                ->groupBy('department_id');

            $peerExpected = 0;
            foreach ($facultyInDepts as $deptFaculty) {
                $count = $deptFaculty->count();
                if ($count > 1) {
                    $peerExpected += $count * ($count - 1);
                }
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
            'total' => 0
        ];

        if ($activeSemId) {
            $sentiments = EvaluationSentiment::whereHas('evaluation', function ($query) use ($activeSemId) {
                $query->where('semester_id', $activeSemId);
            })->get();

            if ($sentiments->count() > 0) {
                $sentimentStats['total'] = $sentiments->count();
                $sentimentStats['positive'] = $sentiments->where('vader_label', 'positive')->count();
                $sentimentStats['neutral'] = $sentiments->where('vader_label', 'neutral')->count();
                $sentimentStats['negative'] = $sentiments->where('vader_label', 'negative')->count();
                $sentimentStats['average'] = round($sentiments->avg('vader_score'), 2);
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
                        $scheduleMessage = 'Opens ' . $starts->diffForHumans($now);
                    } elseif ($now->gt($ends)) {
                        $scheduleStatus = 'expired';
                        $scheduleMessage = 'Expired ' . $ends->diffForHumans($now);
                    } else {
                        $scheduleStatus = 'active';
                        $scheduleMessage = 'Closes ' . $ends->diffForHumans($now);
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
            $departments = Department::orderBy('name')->get();
            foreach ($departments as $dept) {
                $deptExpected = DB::table('class_student')
                    ->join('students', 'students.id', '=', 'class_student.student_id')
                    ->join('programs', 'programs.id', '=', 'students.program_id')
                    ->join('classes', 'classes.id', '=', 'class_student.class_id')
                    ->where('classes.semester_id', $activeSemId)
                    ->where('programs.department_id', $dept->id)
                    ->count();

                $deptSubmitted = DB::table('evaluations')
                    ->join('users', 'users.id', '=', 'evaluations.evaluator_id')
                    ->join('students', 'students.id', '=', 'users.student_id')
                    ->join('programs', 'programs.id', '=', 'students.program_id')
                    ->where('evaluations.semester_id', $activeSemId)
                    ->where('evaluations.evaluation_type', 'upward_student')
                    ->where('programs.department_id', $dept->id)
                    ->count();

                $rate = $deptExpected > 0 ? round(($deptSubmitted / $deptExpected) * 100, 1) : 0;

                $departmentStats[] = [
                    'code' => $dept->code,
                    'name' => $dept->name,
                    'expected' => $deptExpected,
                    'submitted' => $deptSubmitted,
                    'rate' => $rate,
                ];
            }
        }

        // 7. Recent Submissions Anonymized Log
        $recentSubmissions = [];
        if ($activeSemId) {
            $evals = Evaluation::where('semester_id', $activeSemId)
                ->with(['evaluator.employee', 'evaluator.student', 'evaluatee.employee', 'evaluatee.student', 'class.subject'])
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
                    $evaluatorRole = match($role) {
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
                    $evaluateeRole = match($role) {
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
                    $label = match($type) {
                        'upward_student' => 'Student Evaluation',
                        'peer' => 'Peer Evaluation',
                        'dean' => 'Dean Evaluation',
                        'downward' => 'Supervisor Evaluation',
                        'upward_employee' => 'Supervisor Evaluation',
                        default => 'Evaluation'
                    };
                    $description = "{$evaluatorRole} evaluates {$evaluateeRole}";
                }

                $recentSubmissions[] = [
                    'label' => $label,
                    'description' => $description,
                    'subject' => $eval->class?->subject?->code ?? match($type) {
                        'self' => 'Self Evaluation',
                        'peer' => 'Peer Evaluation',
                        'downward' => 'Downward Evaluation',
                        'upward_employee' => 'Supervisor Evaluation',
                        'upward_student' => 'Student Evaluation',
                        default => 'Evaluation'
                    },
                    'time' => $eval->created_at->diffForHumans(),
                ];
            }
        }

        // Determine static styling classes for sentiment to ensure correct Tailwind compilation
        $avg = $sentimentStats['average'];
        if ($avg > 0.05) {
            $sentimentTextClass = 'text-emerald-500 dark:text-emerald-400';
            $sentimentBadgeVariant = 'success';
            $sentimentLabel = 'Positive';
        } elseif ($avg < -0.05) {
            $sentimentTextClass = 'text-rose-500 dark:text-rose-400';
            $sentimentBadgeVariant = 'danger';
            $sentimentLabel = 'Negative';
        } else {
            $sentimentTextClass = 'text-zinc-500 dark:text-zinc-450';
            $sentimentBadgeVariant = 'neutral';
            $sentimentLabel = 'Neutral';
        }

        // 8. Analytics Visual Distribution & Department Averages
        $ratingsDist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $totalRatingsCount = 0;
        $deptScores = [];

        if ($activeSemId) {
            $activeEvalIds = Evaluation::where('semester_id', $activeSemId)->pluck('id')->toArray();
            if (!empty($activeEvalIds)) {
                $answers = EvaluationAnswer::whereIn('evaluation_id', $activeEvalIds)
                    ->select('rating', DB::raw('count(*) as total'))
                    ->groupBy('rating')
                    ->pluck('total', 'rating')
                    ->toArray();

                foreach ($ratingsDist as $rating => $val) {
                    $ratingsDist[$rating] = $answers[$rating] ?? 0;
                }
                $totalRatingsCount = array_sum($ratingsDist);
            }

            $depts = Department::where('type', 'academic')->orWhereNull('type')->orderBy('name')->get();
            foreach ($depts as $dept) {
                $deptEvals = Evaluation::where('semester_id', $activeSemId)
                    ->whereHas('evaluatee.employee', function ($q) use ($dept) {
                        $q->where('department_id', $dept->id);
                    })
                    ->get();
                $dCount = $deptEvals->count();
                $dAvg = $dCount > 0 ? round($deptEvals->avg('rating_average'), 2) : 0.00;
                $deptScores[] = [
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'average' => $dAvg,
                    'count' => $dCount,
                ];
            }
        }

        // 9. Admin Audit Log Activities (Activities performed by Admins or system operations)
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

            // Ignore activity entries that ONLY touched internal background columns or have no changes
            $ignoredKeys = ['notifications_last_viewed_at', 'dismissed_notifications', 'password_changed_at', 'remember_token', 'updated_at', 'created_at'];
            $meaningfulAttrKeys = array_diff(array_keys($attributes), $ignoredKeys);
            $meaningfulOldKeys = array_diff(array_keys($old), $ignoredKeys);

            // If it's a model update with no meaningful business attributes changed and no custom description, skip it
            if ($event === 'updated' && empty($meaningfulAttrKeys) && in_array($act->description, ['updated', 'created', 'deleted', null])) {
                continue;
            }

            // Check if this is a custom logged system message (e.g. bulk import, reminder broadcast, AI training)
            if ($act->description && !in_array($act->description, ['created', 'updated', 'deleted', 'custom'])) {
                $eventTitle = 'System Operation';
                $eventLabel = 'OPERATION';
                $detailStr = $act->description;
                $badgeClass = 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-400 border border-purple-200 dark:border-purple-800';
                $colorDot = 'bg-purple-500';
            } else {
                $subjectLabel = match($subjectClass) {
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
                $eventTitle = match($event) {
                    'created' => "Created {$subjectLabel}",
                    'updated' => "Updated {$subjectLabel}",
                    'deleted' => "Deleted {$subjectLabel}",
                    default => ucfirst($event) . " {$subjectLabel}",
                };

                $badgeClass = match($event) {
                    'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
                    'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400 border border-sky-200 dark:border-sky-800',
                    'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800',
                    default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700',
                };

                $colorDot = match($event) {
                    'created' => 'bg-emerald-500',
                    'updated' => 'bg-sky-500',
                    'deleted' => 'bg-rose-500',
                    default => 'bg-[#9b0000] dark:bg-[#f89696]',
                };

                // Natural human-readable action description
                if ($subjectClass === 'User') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'User';
                    $email = $attributes['email'] ?? $old['email'] ?? $act->subject?->email ?? '';
                    if ($event === 'created') {
                        $detailStr = "Created user account for {$name}" . ($email ? " ({$email})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted user account for {$name}" . ($email ? " ({$email})" : '');
                    } elseif (isset($attributes['is_active'])) {
                        $statusText = $attributes['is_active'] ? 'Enabled' : 'Disabled';
                        $detailStr = "{$statusText} login access for user account {$name}";
                    } elseif (isset($attributes['show_ai_pipeline'])) {
                        $vis = $attributes['show_ai_pipeline'] ? 'visible' : 'hidden';
                        $detailStr = "Toggled AI Pipeline sidebar navigation to {$vis}";
                    } else {
                        $detailStr = "Updated user account profile for {$name}" . ($email ? " ({$email})" : '');
                    }
                } elseif ($subjectClass === 'Employee') {
                    $empNum = $attributes['employee_number'] ?? $old['employee_number'] ?? $act->subject?->employee_number ?? '';
                    $name = trim(($attributes['first_name'] ?? $old['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? $old['last_name'] ?? '')) ?: ($act->subject?->full_name ?? 'Employee');
                    $role = ucfirst($attributes['role'] ?? $old['role'] ?? $act->subject?->role ?? 'Staff');
                    if ($event === 'created') {
                        $detailStr = "Registered new {$role}: {$name}" . ($empNum ? " (ID: {$empNum})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted {$role} record for {$name}" . ($empNum ? " (ID: {$empNum})" : '');
                    } else {
                        $detailStr = "Updated {$role} details for {$name}" . ($empNum ? " (ID: {$empNum})" : '');
                    }
                } elseif ($subjectClass === 'Student') {
                    $studNum = $attributes['student_number'] ?? $old['student_number'] ?? $act->subject?->student_number ?? '';
                    $name = trim(($attributes['first_name'] ?? $old['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? $old['last_name'] ?? '')) ?: ($act->subject?->full_name ?? 'Student');
                    if ($event === 'created') {
                        $detailStr = "Enrolled new student: {$name}" . ($studNum ? " (SN: {$studNum})" : '');
                    } elseif ($event === 'deleted') {
                        $detailStr = "Deleted student record for {$name}" . ($studNum ? " (SN: {$studNum})" : '');
                    } else {
                        $detailStr = "Updated student profile for {$name}" . ($studNum ? " (SN: {$studNum})" : '');
                    }
                } elseif ($subjectClass === 'Department') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Department';
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $detailStr = ($event === 'created' ? 'Added new department: ' : ($event === 'deleted' ? 'Deleted department: ' : 'Updated department: ')) . "{$name}" . ($code ? " ({$code})" : '');
                } elseif ($subjectClass === 'Program') {
                    $name = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Academic Program';
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $detailStr = ($event === 'created' ? 'Added new academic program: ' : ($event === 'deleted' ? 'Deleted program: ' : 'Updated program: ')) . "{$name}" . ($code ? " ({$code})" : '');
                } elseif ($subjectClass === 'AcademicClass') {
                    $section = $attributes['section'] ?? $old['section'] ?? $act->subject?->section ?? 'Section';
                    $detailStr = ($event === 'created' ? 'Created class section: ' : ($event === 'deleted' ? 'Deleted class section: ' : 'Updated class section: ')) . "{$section}";
                } elseif ($subjectClass === 'Subject') {
                    $code = $attributes['code'] ?? $old['code'] ?? $act->subject?->code ?? '';
                    $title = $attributes['title'] ?? $old['title'] ?? $act->subject?->title ?? 'Subject';
                    $detailStr = ($event === 'created' ? 'Added new subject: ' : ($event === 'deleted' ? 'Deleted subject: ' : 'Updated subject: ')) . "{$code} - {$title}";
                } elseif ($subjectClass === 'EvaluationQuestion') {
                    $qText = $attributes['question_text'] ?? $old['question_text'] ?? $act->subject?->question_text ?? '';
                    $snippet = $qText ? '"' . \Illuminate\Support\Str::limit($qText, 45) . '"' : 'Evaluation question';
                    $detailStr = ($event === 'created' ? 'Added question: ' : ($event === 'deleted' ? 'Deleted question: ' : 'Modified question: ')) . $snippet;
                } elseif ($subjectClass === 'EvaluationCriterion') {
                    $cName = $attributes['name'] ?? $old['name'] ?? $act->subject?->name ?? 'Criterion';
                    $detailStr = ($event === 'created' ? 'Created evaluation criterion: ' : ($event === 'deleted' ? 'Deleted criterion: ' : 'Updated criterion: ')) . "{$cName}";
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
                    $detailStr = ($event === 'created' ? 'Created academic year: ' : ($event === 'deleted' ? 'Deleted academic year: ' : 'Updated academic year: ')) . "{$name}";
                } else {
                    $detailStr = "Administrative operation on {$subjectLabel}" . ($act->subject_id ? " (ID #{$act->subject_id})" : '');
                }
            }

            $causerName = $act->causer?->name ?? 'System Administrator';

            $auditLogs[] = [
                'title' => $eventTitle,
                'event' => $eventLabel,
                'description' => $detailStr,
                'causer' => $causerName,
                'badge_class' => $badgeClass,
                'color_dot' => $colorDot,
                'time' => $act->created_at ? $act->created_at->diffForHumans() : 'Recently',
                'full_time' => $act->created_at ? $act->created_at->format('M d, Y h:i:s A') : '',
            ];
        }

        $pendingCount = max(0, $expectedCount - $submittedCount);

        return [
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
            'ratingsDist' => $ratingsDist,
            'totalRatingsCount' => $totalRatingsCount,
            'deptScores' => $deptScores,
            'auditLogs' => $auditLogs,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-8">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Admin Dashboard</flux:heading>
            <flux:subheading class="text-left">System overview, active window progress, and AI sentiment insights.</flux:subheading>
        </div>
        <div class="w-full sm:w-auto flex justify-end">
            @if($activeYear && $activeSemester)
                <flux:badge variant="info" size="md" class="w-max">
                    Active Period: {{ $activeYear->name }} - {{ $activeSemester->name }}
                </flux:badge>
            @else
                <flux:badge variant="danger" size="md" class="w-max">No Active Period Set</flux:badge>
            @endif
        </div>
    </div>

    <!-- Top Row Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1: Total Employees -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Employees</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$employeeCount" /></span>
                </div>
                <flux:icon name="users" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>

        </div>

        <!-- Card 2: Total Students -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Students</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$studentCount" /></span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>

        </div>

        <!-- Card 3: Current Evaluation Progress -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Current Evaluation Progress</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$progressPercent" suffix="%" /></span>
                </div>
                <flux:icon name="check-circle" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 mt-3 overflow-hidden">
                <div class="h-3 rounded-full transition-all duration-500 bg-[#9b0000] dark:bg-[#f89696]" style="width: {{ max(0, min(100, (float)$progressPercent)) }}% !important;"></div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium block mt-2">
                {{ $submittedCount }} of {{ $expectedCount }} expected evaluations submitted
            </span>
        </div>

        <!-- Card 4: Pending Submissions -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Pending Submissions</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$pendingCount" /></span>
                </div>
                <flux:icon name="clock" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Awaiting evaluator completion</span>
        </div>
    </div>

    <!-- Middle Row: Simplified Status & Feedback Overview Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Simplified Card 1: Evaluation Period Status -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Evaluation Period Status
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Shows whether student & employee evaluation forms can be submitted right now.</p>
                </div>
            </div>

            @if($activeSemester)
                <div class="space-y-4 flex-1 flex flex-col justify-between">
                    <!-- Big Easy-to-Read Status Box -->
                    @if($scheduleStatus === 'active')
                        <div class="bg-emerald-50 dark:bg-emerald-950/30 border-2 border-emerald-500 p-4 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="size-4 rounded-full bg-emerald-500 animate-pulse flex-shrink-0"></span>
                                <div>
                                    <p class="text-sm font-extrabold text-emerald-800 dark:text-emerald-300">EVALUATION IS OPEN</p>
                                    <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">Evaluators can submit evaluation forms.</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-amber-50 dark:bg-amber-950/30 border-2 border-amber-500 p-4 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="size-4 rounded-full bg-amber-500 flex-shrink-0"></span>
                                <div>
                                    <p class="text-sm font-extrabold text-amber-800 dark:text-amber-300">EVALUATION IS CLOSED</p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">No evaluation forms can be submitted right now.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                        <div class="flex items-center justify-between border-b border-zinc-200/80 dark:border-zinc-700/50 pb-2">
                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">
                                Current Scheduled Period
                            </span>
                            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                @if($scheduleStatus === 'active')
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span class="text-emerald-700 dark:text-emerald-400 font-semibold">{{ $scheduleMessage }}</span>
                                @elseif($scheduleStatus === 'scheduled')
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    <span class="text-amber-700 dark:text-amber-400 font-semibold">{{ $scheduleMessage }}</span>
                                @elseif($scheduleStatus === 'expired')
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                    <span class="text-rose-700 dark:text-rose-400 font-semibold">{{ $scheduleMessage }}</span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-zinc-400"></span>
                                    <span class="text-zinc-600 dark:text-zinc-400 font-semibold">{{ $scheduleMessage }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <div>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Evaluation Opens (Start)</span>
                                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block mt-1">
                                    {{ $activeSemester->evaluation_starts_at ? $activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A') : 'Not Set' }}
                                </span>
                            </div>
                            <div>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Evaluation Closes (End)</span>
                                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block mt-1">
                                    {{ $activeSemester->evaluation_ends_at ? $activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A') : 'Not Set' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button href="/admin/evaluation-settings" variant="primary" size="sm" icon="cog">
                            Change Evaluation Schedule Dates
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="exclamation-circle" class="size-10 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">No active academic period configured.</p>
                    <flux:button href="/admin/evaluation-settings" variant="primary" size="sm" class="mt-2">
                        Set Active Period & Schedule
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- Simplified Card 2: Overall Evaluation Feedback Overview -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Overall Evaluation Feedback
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Simple summary of comments submitted across all evaluators.</p>
                </div>
            </div>

            @if($sentimentStats['total'] > 0)
                @php
                    $posCount = $sentimentStats['positive'];
                    $neuCount = $sentimentStats['neutral'];
                    $negCount = $sentimentStats['negative'];
                    $total = $sentimentStats['total'];

                    $posPct = round(($posCount / $total) * 100, 1);
                    $neuPct = round(($neuCount / $total) * 100, 1);
                    $negPct = round(($negCount / $total) * 100, 1);
                @endphp
                <div class="space-y-4 flex-1 flex flex-col justify-between">
                    <!-- Big Summary Box -->
                    <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Overall Sentiment</p>
                            <p class="text-lg font-extrabold text-zinc-900 dark:text-zinc-100 mt-0.5">
                                @if($posPct >= 50)
                                    😊 Mostly Positive Feedback ({{ $posPct }}%)
                                @elseif($negPct >= 40)
                                    ⚠️ Needs Attention ({{ $negPct }}% Negative)
                                @else
                                    😐 Balanced Feedback Across Evaluators
                                @endif
                            </p>
                        </div>
                        <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700">
                            {{ $total }} total reviews
                        </span>
                    </div>

                    <!-- 3 Simple Stat Boxes -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="bg-[#dffbee] dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-[#035e44] dark:text-[#03dd9f] uppercase tracking-wider block">Positive</span>
                            <span class="text-2xl font-black text-[#035e44] dark:text-[#03dd9f] block mt-1">{{ $posCount }}</span>
                            <span class="text-xs text-[#035e44] dark:text-[#03dd9f] font-semibold">{{ $posPct }}% of total</span>
                        </div>
                        <div class="bg-[#fcf6e4] dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/50 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-[#843c06] dark:text-[#f7a15e] uppercase tracking-wider block">Neutral</span>
                            <span class="text-2xl font-black text-[#843c06] dark:text-[#f7a15e] block mt-1">{{ $neuCount }}</span>
                            <span class="text-xs text-[#843c06] dark:text-[#f7a15e] font-semibold">{{ $neuPct }}% of total</span>
                        </div>
                        <div class="bg-[#fff1f2] dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/50 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-[#a30f34] dark:text-[#f89bb2] uppercase tracking-wider block">Negative</span>
                            <span class="text-2xl font-black text-[#a30f34] dark:text-[#f89bb2] block mt-1">{{ $negCount }}</span>
                            <span class="text-xs text-[#a30f34] dark:text-[#f89bb2] font-semibold">{{ $negPct }}% of total</span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button href="/reports" variant="outline" size="sm" icon="chart-bar">
                            View Detailed Feedback Reports
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="adjustments-horizontal" class="size-10 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">No evaluation feedback comments available yet.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Analytics Charts Row: Ratings Distribution & Department Average Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" x-data="dashboardAnalyticsCharts({
        ratingsData: [{{ (int)($ratingsDist[5] ?? 0) }}, {{ (int)($ratingsDist[4] ?? 0) }}, {{ (int)($ratingsDist[3] ?? 0) }}, {{ (int)($ratingsDist[2] ?? 0) }}, {{ (int)($ratingsDist[1] ?? 0) }}],
        deptLabels: {{ json_encode(array_values(array_column($deptScores, 'code'))) }},
        deptAverages: {{ json_encode(array_values(array_column($deptScores, 'average'))) }}
    })">
        <!-- Chart 1: Ratings Distribution -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        
                        Ratings Distribution Chart
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Frequency of 1 to 5 rating scores submitted for this period.</p>
                </div>
                <flux:badge variant="neutral" size="sm" class="font-bold">
                    {{ $totalRatingsCount }} answer{{ $totalRatingsCount === 1 ? '' : 's' }}
                </flux:badge>
            </div>

            @if($totalRatingsCount > 0)
                <div class="h-64 w-full pt-2">
                    <canvas x-ref="ratingsChart" class="w-full h-full"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-8 flex-1 gap-2 h-64">
                    <flux:icon name="chart-bar" class="size-8 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No question rating answers recorded for this period.</p>
                </div>
            @endif
        </div>

        <!-- Chart 2: Academic Department Average Ratings Comparison -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        
                        Department Average Ratings Chart
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Comparative mean scores across academic departments.</p>
                </div>
                <flux:badge variant="neutral" size="sm" class="font-bold">
                    Scale: 5.00
                </flux:badge>
            </div>

            @if(count($deptScores) > 0)
                <div class="h-64 w-full pt-2">
                    <canvas x-ref="deptChart" class="w-full h-full"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-8 flex-1 gap-2 h-64">
                    <flux:icon name="building-office" class="size-8 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No departments configured.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Side-by-Side Row: Admin Audit Log & Recent Submissions Log -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 1. Audit Log -->
        <flux:card class="p-6 flex flex-col justify-between shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] h-[480px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        
                        Audit Log
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Chronological history of admin updates and system operations.</p>
                </div>
                <flux:badge variant="neutral" size="sm" class="font-bold">
                    {{ count($auditLogs) }} activities
                </flux:badge>
            </div>

            <div class="flex-1 overflow-y-auto pr-2 mt-4 space-y-4">
                @if(count($auditLogs) > 0)
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($auditLogs as $index => $log)
                                <li>
                                    <div class="relative pb-6">
                                        @if($index < count($auditLogs) - 1)
                                            <span class="absolute top-4 left-3 -ml-px h-full w-0.5 bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-6 w-6 rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center">
                                                    <span class="h-2 w-2 rounded-full {{ $log['color_dot'] }}"></span>
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0 pt-0.5 flex justify-between space-x-4">
                                                <div class="text-xs text-zinc-700 dark:text-zinc-300">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span class="font-extrabold text-zinc-900 dark:text-zinc-100 text-xs">{{ $log['title'] }}</span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $log['badge_class'] }}">
                                                            {{ $log['event'] }}
                                                        </span>
                                                    </div>
                                                    <span class="font-medium text-zinc-600 dark:text-zinc-400 block mt-1">{{ $log['description'] }}</span>
                                                    @if(!empty($log['causer']))
                                                        <span class="text-[11px] text-zinc-400 dark:text-zinc-500 mt-0.5 block">
                                                            Performed by <span class="font-semibold text-zinc-600 dark:text-zinc-400">{{ $log['causer'] }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-right text-[10px] whitespace-nowrap text-zinc-400 dark:text-zinc-500 font-semibold pt-1">
                                                    <span title="{{ $log['full_time'] }}">{{ $log['time'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center text-center p-6 h-full gap-2">
                        <flux:icon name="clock" class="size-9 text-zinc-300 dark:text-zinc-650" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">No administrator activity logs recorded yet.</p>
                    </div>
                @endif
            </div>
        </flux:card>

        <!-- 2. Recent Submissions Log -->
        <flux:card class="p-6 flex flex-col justify-between shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] h-[480px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                        
                        Recent Submissions Log
                    </h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Live incoming evaluation responses in active semester.</p>
                </div>
                <flux:badge variant="neutral" size="sm" class="font-bold">
                    {{ count($recentSubmissions) }} submissions
                </flux:badge>
            </div>

            <div class="flex-1 overflow-y-auto pr-2 mt-4 space-y-4">
                @if(count($recentSubmissions) > 0)
                    <div class="flow-root">
                        <ul class="-mb-8">
                            @foreach($recentSubmissions as $index => $sub)
                                <li>
                                    <div class="relative pb-6">
                                        @if($index < count($recentSubmissions) - 1)
                                            <span class="absolute top-4 left-3 -ml-px h-full w-0.5 bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-6 w-6 rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center">
                                                    <span class="h-2 w-2 rounded-full bg-[#9b0000] dark:bg-[#f89696]"></span>
                                                </span>
                                            </div>
                                            <div class="flex-1 min-w-0 pt-0.5 flex justify-between space-x-4">
                                                <div class="text-xs text-zinc-700 dark:text-zinc-300">
                                                    <span class="font-extrabold text-zinc-900 dark:text-zinc-100 block text-xs">{{ $sub['label'] }}</span>
                                                    <span class="font-medium text-zinc-600 dark:text-zinc-400 block mt-0.5">{{ $sub['description'] }}</span>
                                                </div>
                                                <div class="text-right text-[10px] whitespace-nowrap text-zinc-400 dark:text-zinc-500 font-semibold pt-1">
                                                    {{ $sub['time'] }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center text-center p-6 h-full gap-2">
                        <flux:icon name="inbox" class="size-9 text-zinc-300 dark:text-zinc-650" />
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">No submissions recorded for this active semester.</p>
                    </div>
                @endif
            </div>
        </flux:card>
    </div>

    <!-- Quick Actions Panel -->
    <div class="flex flex-col gap-6 mt-2">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <flux:heading size="lg">Quick System Actions</flux:heading>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Click any shortcut below to navigate directly to evaluation tracking, user management, and academic settings.</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <!-- 1. Track Evaluation Turnout -->
            <a href="/manage-evaluations" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="chart-pie" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Track Evaluation Turnout</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">View category completion progress and send reminder alerts.</span>
                </div>
            </a>

            <!-- 2. View Completed Evaluations -->
            <a href="/evaluation-results" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="clipboard-document-list" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">View Completed Evaluations</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Check individual submissions and detailed rating scores.</span>
                </div>
            </a>

            <!-- 3. Generate GRC Reports -->
            <a href="/reports" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="printer" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Generate GRC Reports</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">View official 2-page print scorecards and AI comment summaries.</span>
                </div>
            </a>

            <!-- 4. View Rankings & Leaderboards -->
            <a href="/rankings" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="trophy" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Department & Faculty Rankings</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Compare college department averages and professor rankings.</span>
                </div>
            </a>

            <!-- 5. Set Evaluation Schedule & Dates -->
            <a href="/admin/evaluation-settings" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="cog-6-tooth" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Evaluation Schedule & Settings</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Open/close evaluation periods and set score weight percentages.</span>
                </div>
            </a>

            <!-- 6. Edit Evaluation Questions -->
            <a href="/admin/questions" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="document-text" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Edit Evaluation Questions</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, edit, or reorder questions for all 7 evaluation types.</span>
                </div>
            </a>

            <!-- 7. Manage Classes & Rosters -->
            <a href="/admin/classes" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="queue-list" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Classes & Rosters</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Assign teachers, schedules, and enroll students into sections.</span>
                </div>
            </a>

            <!-- 8. Manage Student Accounts -->
            <a href="/admin/students" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="academic-cap" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Student Accounts</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, import CSV, edit, or update student profiles and statuses.</span>
                </div>
            </a>

            <!-- 9. Manage Employee Accounts -->
            <a href="/admin/employees" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="users" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Employee Accounts</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, import CSV, edit faculty, dean, head, and staff accounts.</span>
                </div>
            </a>

            <!-- 10. Manage Subject Catalog -->
            <a href="/admin/subjects" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="book-open" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Subject Catalog</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Add, import CSV, edit academic course subjects and unit credits.</span>
                </div>
            </a>

            <!-- 11. Manage Academic Departments -->
            <a href="/admin/departments" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="building-office-2" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Departments</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Configure colleges, administrative offices, and assign heads.</span>
                </div>
            </a>

            <!-- 12. Manage Academic Programs -->
            <a href="/admin/programs" wire:navigate class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-all duration-150 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 group cursor-pointer text-left">
                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-[#9b0000] dark:text-[#f89696] shrink-0 group-hover:scale-105 transition-transform">
                    <flux:icon name="academic-cap" class="size-5" />
                </div>
                <div class="min-w-0">
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block truncate group-hover:text-[#9b0000] dark:group-hover:text-[#f89696] transition-colors">Manage Academic Programs</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 block line-clamp-2">Setup degree programs (BSIT, BSA, etc.) and program heads.</span>
                </div>
            </a>
        </div>
    </div>

    <script>
        window.dashboardAnalyticsCharts = function(config) {
            return {
                ratingsInstance: null,
                deptInstance: null,
                ratingsData: config?.ratingsData || [],
                deptLabels: config?.deptLabels || [],
                deptAverages: config?.deptAverages || [],
                init() {
                    this.$nextTick(() => {
                        this.renderAll();
                    });

                    window.addEventListener('flux:appearance:changed', () => {
                        this.renderAll();
                    });
                },
                renderAll() {
                    if (typeof Chart === 'undefined') {
                        setTimeout(() => this.renderAll(), 100);
                        return;
                    }
                    this.renderRatings();
                    this.renderDept();
                },
                renderRatings() {
                    if (!this.$refs.ratingsChart || typeof Chart === 'undefined') return;
                    if (this.ratingsInstance) {
                        this.ratingsInstance.destroy();
                        this.ratingsInstance = null;
                    }
                    const isDark = document.documentElement.classList.contains('dark');
                    const textColor = isDark ? '#d4d4d8' : '#3f3f46';
                    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

                    this.ratingsInstance = new Chart(this.$refs.ratingsChart.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['Rating 5', 'Rating 4', 'Rating 3', 'Rating 2', 'Rating 1'],
                            datasets: [{
                                data: this.ratingsData,
                                backgroundColor: ['#9b0000', '#b91c1c', '#f59e0b', '#ef4444', '#71717a'],
                                borderRadius: 6,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            return Number(ctx.raw).toLocaleString() + ' answers';
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: gridColor },
                                    ticks: { precision: 0, color: textColor }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: textColor }
                                }
                            }
                        }
                    });
                },
                renderDept() {
                    if (!this.$refs.deptChart || typeof Chart === 'undefined' || !this.deptLabels || this.deptLabels.length === 0) return;
                    if (this.deptInstance) {
                        this.deptInstance.destroy();
                        this.deptInstance = null;
                    }
                    const isDark = document.documentElement.classList.contains('dark');
                    const textColor = isDark ? '#d4d4d8' : '#3f3f46';
                    const gridColor = isDark ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.06)';

                    this.deptInstance = new Chart(this.$refs.deptChart.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: this.deptLabels,
                            datasets: [{
                                data: this.deptAverages,
                                backgroundColor: isDark ? '#f89696' : '#9b0000',
                                hoverBackgroundColor: isDark ? '#fca5a5' : '#7a0000',
                                borderRadius: 6,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            return Number(ctx.raw).toFixed(2) + ' / 5.00 Rating';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    min: 0,
                                    max: 5.0,
                                    grid: { color: gridColor },
                                    ticks: { color: textColor }
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { color: textColor }
                                }
                            }
                        }
                    });
                }
            };
        };
    </script>
</div>
