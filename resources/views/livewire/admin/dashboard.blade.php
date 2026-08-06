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
                ->take(5)
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
                        'program head' => 'Program Head',
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
                        'program head' => 'Program Head',
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
                } elseif ($evaluatorRole === 'Dean' && $evaluateeRole === 'Program Head') {
                    $label = 'Dean Evaluation';
                    $description = 'Dean evaluates Program Head';
                } elseif ($evaluatorRole === 'Program Head' && $evaluateeRole === 'Professor') {
                    $label = 'Program Head Evaluation';
                    $description = 'Program Head evaluates Professor';
                } elseif ($evaluatorRole === 'Professor' && $evaluateeRole === 'Professor') {
                    $label = 'Peer Evaluation';
                    $description = 'Professor evaluates Professor';
                } elseif ($type === 'upward_employee' || ($evaluatorRole === 'Program Head' && $evaluateeRole === 'Dean') || ($evaluatorRole === 'Professor' && $evaluateeRole === 'Program Head') || ($evaluatorRole === 'Staff' && in_array($evaluateeRole, ['Dean', 'Program Head']))) {
                    $label = 'Supervisor Evaluation';
                    $description = "{$evaluatorRole} evaluates {$evaluateeRole}";
                } elseif ($evaluateeRole === 'Staff') {
                    $label = 'Staff Evaluation';
                    $description = "{$evaluatorRole} evaluates Staff";
                } else {
                    $label = match($type) {
                        'upward_student' => 'Student Evaluation',
                        'peer' => 'Peer Evaluation',
                        'downward' => 'Downward Evaluation',
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
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border: 2px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Employees</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$employeeCount" /></span>
                </div>
                <flux:icon name="users" class="size-6 text-[#800000] dark:text-red-400" />
            </div>

        </div>

        <!-- Card 2: Total Students -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border: 2px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Students</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$studentCount" /></span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-[#800000] dark:text-red-400" />
            </div>

        </div>

        <!-- Card 3: Current Evaluation Progress -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border: 2px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Current Evaluation Progress</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$progressPercent" suffix="%" /></span>
                </div>
                <flux:icon name="check-circle" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 mt-3 overflow-hidden">
                <div class="h-3 rounded-full transition-all duration-500" style="width: {{ max(0, min(100, (float)$progressPercent)) }}% !important; background-color: #800000 !important;"></div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium block mt-2">
                {{ $submittedCount }} of {{ $expectedCount }} expected evaluations submitted
            </span>
        </div>

        <!-- Card 4: Pending Submissions -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border: 2px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Pending Submissions</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$pendingCount" /></span>
                </div>
                <flux:icon name="clock" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Awaiting evaluator completion</span>
        </div>
    </div>

    <!-- Middle Row: Simplified Status & Feedback Overview Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Simplified Card 1: Evaluation Period Status -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6" style="border: 2px solid #800000 !important;">
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
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6" style="border: 2px solid #800000 !important;">
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
                        <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider block">Positive</span>
                            <span class="text-2xl font-black text-emerald-800 dark:text-emerald-300 block mt-1">{{ $posCount }}</span>
                            <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">{{ $posPct }}% of total</span>
                        </div>
                        <div class="bg-zinc-50 dark:bg-zinc-800/30 border border-zinc-200 dark:border-zinc-700 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider block">Neutral</span>
                            <span class="text-2xl font-black text-zinc-800 dark:text-zinc-200 block mt-1">{{ $neuCount }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold">{{ $neuPct }}% of total</span>
                        </div>
                        <div class="bg-rose-50 dark:bg-rose-950/20 border border-rose-200 dark:border-rose-800/50 p-3 rounded-xl text-center">
                            <span class="text-xs font-bold text-rose-700 dark:text-rose-400 uppercase tracking-wider block">Negative</span>
                            <span class="text-2xl font-black text-rose-800 dark:text-rose-300 block mt-1">{{ $negCount }}</span>
                            <span class="text-xs text-rose-600 dark:text-rose-400 font-semibold">{{ $negPct }}% of total</span>
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

    <!-- Bottom Row: Department Stats & Recent Submissions -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Department Completion rates -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs lg:col-span-7" style="border: 2px solid #800000 !important;">
            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                Department Participation Rates
            </h3>

            @if(count($departmentStats) > 0)
                <div class="w-full overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-850 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Department</th>
                                <th class="px-4 py-3 text-center">Submissions</th>
                                <th class="px-4 py-3 text-right">Completion %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($departmentStats as $dept)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                    <td class="px-4 py-3.5">
                                        <span class="font-bold text-zinc-800 dark:text-zinc-200 block">{{ $dept['code'] }}</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">{{ $dept['name'] }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center text-zinc-700 dark:text-zinc-300 font-semibold">
                                        {{ $dept['submitted'] }} <span class="text-zinc-400 text-xs">/ {{ $dept['expected'] }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2.5">
                                            <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $dept['rate'] }}%</span>
                                            <div class="w-16 bg-zinc-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                                <div class="bg-indigo-500 dark:bg-indigo-400 h-1.5 rounded-full" style="width: {{ $dept['rate'] }}%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-6 text-zinc-500">
                    <p class="text-sm">No departments or evaluations loaded.</p>
                </div>
            @endif
        </flux:card>

        <!-- Recent Submissions Log -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs lg:col-span-5" style="border: 2px solid #800000 !important;">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    Recent Submissions Log
                </h3>
   
            </div>

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
                                                <span class="h-2 w-2 rounded-full bg-[#800000]"></span>
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
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="inbox" class="size-9 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No submissions recorded for this active semester.</p>
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Quick Actions Panel -->
    <div class="flex flex-col gap-4 mt-2">
        <flux:heading size="lg">Quick System Actions</flux:heading>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <flux:card href="/admin/evaluation-settings" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs" style="border: 2px solid #800000 !important;">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="cog" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Configure Settings</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Manage schedules and criteria.</span>
                </div>
            </flux:card>

            <flux:card href="/reports" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs" style="border: 2px solid #800000 !important;">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="chart-bar" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Evaluation Reports</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Generate summary PDF reports.</span>
                </div>
            </flux:card>

            <flux:card href="/admin/questions" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs" style="border: 2px solid #800000 !important;">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="document-text" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Edit Questionnaires</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Customize evaluation queries.</span>
                </div>
            </flux:card>

            <flux:card href="/admin/students" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs" style="border: 2px solid #800000 !important;">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="user" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Manage Accounts</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Create/edit student & faculty users.</span>
                </div>
            </flux:card>
        </div>
    </div>
</div>
