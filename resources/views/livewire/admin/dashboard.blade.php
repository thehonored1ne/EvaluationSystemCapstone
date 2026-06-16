<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
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

new #[Layout('components.layouts.app')] class extends Component {
    public function with(): array
    {
        // 1. Fetch Active Semester & Year
        $activeSem = Semester::where('is_active', true)->first();
        $activeYear = $activeSem ? $activeSem->academicYear : null;
        $activeSemId = $activeSem ? $activeSem->id : null;

        // 2. Counts
        $facultyCount = Employee::where('role', 'faculty')->where('status', 'active')->count();
        $studentCount = Student::where('status', 'regular')->count();
        $userCount = User::count();

        // 3. Expected & Submitted evaluations progress
        $expectedCount = 0;
        $submittedCount = 0;
        $progressPercent = 0;

        if ($activeSemId) {
            $expectedCount = DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->where('classes.semester_id', $activeSemId)
                ->count();

            $submittedCount = Evaluation::where('semester_id', $activeSemId)
                ->where('evaluation_type', 'student')
                ->count();

            if ($expectedCount > 0) {
                $progressPercent = round(($submittedCount / $expectedCount) * 100, 1);
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
                    ->where('evaluations.evaluation_type', 'student')
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

        // 7. Recent Submissions Anonymized
        $recentSubmissions = [];
        if ($activeSemId) {
            $evals = Evaluation::where('semester_id', $activeSemId)
                ->with(['evaluatee.employee', 'class.subject'])
                ->latest()
                ->take(5)
                ->get();

            foreach ($evals as $eval) {
                $evaluatorLabel = match($eval->evaluation_type) {
                    'student' => 'Student',
                    'peer' => 'Faculty Peer',
                    'self' => 'Self',
                    'upward' => 'Subordinate',
                    'downward' => 'Supervisor',
                    default => 'User'
                };

                $targetName = $eval->evaluatee?->name ?? 'System';
                if ($eval->evaluatee?->employee) {
                    $employee = $eval->evaluatee->employee;
                    $targetName = "Prof. " . $employee->first_name . " " . $employee->last_name;
                }

                $recentSubmissions[] = [
                    'evaluator' => $evaluatorLabel,
                    'target' => $targetName,
                    'type' => ucfirst($eval->evaluation_type),
                    'subject' => $eval->class?->subject?->code ?? 'Self Evaluation',
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

        return [
            'activeSemester' => $activeSem,
            'activeYear' => $activeYear,
            'facultyCount' => $facultyCount,
            'studentCount' => $studentCount,
            'userCount' => $userCount,
            'expectedCount' => $expectedCount,
            'submittedCount' => $submittedCount,
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
        <!-- Faculty Count -->
        <flux:card class="flex flex-col gap-2 p-6 shadow-xs hover:shadow-md transition-shadow duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Faculty</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1">{{ $facultyCount }}</span>
                </div>
                <flux:icon name="users" class="size-6 text-zinc-400 dark:text-zinc-500" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Active teaching profiles</span>
        </flux:card>

        <!-- Student Count -->
        <flux:card class="flex flex-col gap-2 p-6 shadow-xs hover:shadow-md transition-shadow duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Students</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1">{{ $studentCount }}</span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-zinc-400 dark:text-zinc-500" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Enrolled in active programs</span>
        </flux:card>

        <!-- Evaluation Progress -->
        <flux:card class="flex flex-col gap-2 p-6 shadow-xs hover:shadow-md transition-shadow duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Evaluation Progress</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1">{{ $progressPercent }}%</span>
                </div>
                <flux:icon name="check-circle" class="size-6 text-zinc-400 dark:text-zinc-500" />
            </div>
            <div class="w-full bg-zinc-100 dark:bg-zinc-800 rounded-full h-2 mt-2 overflow-hidden">
                <div class="bg-emerald-500 dark:bg-emerald-400 h-2 rounded-full transition-all duration-500" style="width: {{ $progressPercent }}%"></div>
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium block mt-1">
                {{ $submittedCount }} / {{ $expectedCount }} student evaluations
            </span>
        </flux:card>

        <!-- Feedback Sentiment Score -->
        <flux:card class="flex flex-col gap-2 p-6 shadow-xs hover:shadow-md transition-shadow duration-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Average Sentiment</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1">
                        {{ $sentimentStats['average'] > 0 ? '+' : '' }}{{ $sentimentStats['average'] }}
                    </span>
                </div>
                <flux:icon name="bolt" class="size-6 {{ $sentimentTextClass }}" />
            </div>
            <div class="flex items-center gap-1.5 mt-2">
                <flux:badge variant="{{ $sentimentBadgeVariant }}" size="sm">
                    {{ $sentimentLabel }}
                </flux:badge>
                <span class="text-xs text-zinc-550 dark:text-zinc-400 font-medium">From {{ $sentimentStats['total'] }} analyzed reviews</span>
            </div>
        </flux:card>
    </div>

    <!-- Middle Row: Active Window and AI Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Active Evaluation Window Summary Card -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    Active Evaluation Window
                </h3>
                @if($scheduleStatus === 'active')
                    <flux:badge variant="success" size="md">Open & Active</flux:badge>
                @elseif($scheduleStatus === 'scheduled')
                    <flux:badge variant="warning" size="md">Scheduled</flux:badge>
                @elseif($scheduleStatus === 'expired')
                    <flux:badge variant="danger" size="md">Expired</flux:badge>
                @elseif($scheduleStatus === 'locked')
                    <flux:badge variant="danger" size="md">Closed (Locked)</flux:badge>
                @else
                    <flux:badge variant="neutral" size="md">Inactive</flux:badge>
                @endif
            </div>

            @if($activeSemester)
                <div class="space-y-4 flex-1 flex flex-col justify-between">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-zinc-50 dark:bg-zinc-800/30 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <div>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 block font-medium">Evaluation Period</span>
                            <span class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 block mt-0.5">
                                {{ $activeSemester->name }} (A.Y. {{ $activeYear->name }})
                            </span>
                        </div>
                        <div>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 block font-medium">Current Status</span>
                            <span class="text-sm font-bold text-zinc-800 dark:text-zinc-200 block mt-0.5 flex items-center gap-1.5">
                                @if($scheduleStatus === 'active')
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                                @else
                                    <span class="w-2 h-2 rounded-full bg-zinc-400 dark:bg-zinc-500"></span>
                                @endif
                                {{ $scheduleMessage }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="flex items-start gap-2.5">
                            <span class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                            <div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">Start Time & Date</p>
                                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-100 mt-0.5">
                                    {{ $activeSemester->evaluation_starts_at ? $activeSemester->evaluation_starts_at->format('M d, Y \a\t h:i A') : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full bg-rose-500 dark:bg-rose-400"></span>
                            <div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">End Time & Date</p>
                                <p class="text-sm font-bold text-zinc-800 dark:text-zinc-100 mt-0.5">
                                    {{ $activeSemester->evaluation_ends_at ? $activeSemester->evaluation_ends_at->format('M d, Y \a\t h:i A') : '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                        <flux:button href="/admin/evaluation-settings" variant="outline" size="sm" icon="cog">
                            Manage Schedule Settings
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="exclamation-circle" class="size-10 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">No active academic period is set.</p>
                    <flux:button href="/admin/evaluation-settings" variant="primary" size="sm" class="mt-2">
                        Configure Settings
                    </flux:button>
                </div>
            @endif
        </flux:card>

        <!-- Live AI Sentiment Analytics Card -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                    AI Sentiment Analysis
                </h3>
                <flux:badge variant="info" size="md">VADER Lexicon</flux:badge>
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
                <div class="space-y-6 flex-1 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1 font-semibold">
                            <span>Comment Sentiment Distribution</span>
                            <span>{{ $total }} comments analyzed</span>
                        </div>
                        <!-- Stacked Progress Bar -->
                        <div class="w-full h-5 rounded-full overflow-hidden flex bg-zinc-100 dark:bg-zinc-800">
                            @if($posCount > 0)
                                <div class="bg-emerald-500 dark:bg-emerald-400 h-full flex items-center justify-center text-[10px] text-white font-bold transition-all duration-500" style="width: {{ $posPct }}%" title="Positive: {{ $posPct }}%">
                                    {{ $posPct >= 10 ? $posPct . '%' : '' }}
                                </div>
                            @endif
                            @if($neuCount > 0)
                                <div class="bg-zinc-400 h-full flex items-center justify-center text-[10px] text-white font-bold transition-all duration-500" style="width: {{ $neuPct }}%" title="Neutral: {{ $neuPct }}%">
                                    {{ $neuPct >= 10 ? $neuPct . '%' : '' }}
                                </div>
                            @endif
                            @if($negCount > 0)
                                <div class="bg-rose-500 dark:bg-rose-400 h-full flex items-center justify-center text-[10px] text-white font-bold transition-all duration-500" style="width: {{ $negPct }}%" title="Negative: {{ $negPct }}%">
                                    {{ $negPct >= 10 ? $negPct . '%' : '' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-emerald-50/30 dark:bg-emerald-950/10 border border-emerald-100/50 dark:border-emerald-900/30 p-3 rounded-xl text-center">
                            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Positive</span>
                            <span class="text-xl font-bold text-emerald-700 dark:text-emerald-300 block mt-0.5">{{ $posCount }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold">{{ $posPct }}%</span>
                        </div>
                        <div class="bg-zinc-50/50 dark:bg-zinc-800/20 border border-zinc-200 dark:border-zinc-700 p-3 rounded-xl text-center">
                            <span class="text-[10px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Neutral</span>
                            <span class="text-xl font-bold text-zinc-700 dark:text-zinc-300 block mt-0.5">{{ $neuCount }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold">{{ $neuPct }}%</span>
                        </div>
                        <div class="bg-rose-50/30 dark:bg-rose-950/10 border border-rose-100/50 dark:border-rose-900/30 p-3 rounded-xl text-center">
                            <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider block">Negative</span>
                            <span class="text-xl font-bold text-rose-700 dark:text-rose-300 block mt-0.5">{{ $negCount }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold">{{ $negPct }}%</span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-zinc-100 dark:border-zinc-800 mt-2">
                        <flux:button href="/reports" variant="outline" size="sm" icon="chart-bar">
                            Analyze Sentiment Reports
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-6 flex-1 gap-2">
                    <flux:icon name="adjustments-horizontal" class="size-10 text-zinc-300 dark:text-zinc-650" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">No evaluation comments have been analyzed yet.</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-xs">AI sentiment ratings will compile automatically once students submit evaluations.</p>
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Bottom Row: Department Stats & Recent Submissions -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Department Completion rates -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs lg:col-span-7">
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

        <!-- Recent submissions feed -->
        <flux:card class="p-6 flex flex-col gap-4 shadow-xs lg:col-span-5">
            <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                Recent Submissions
            </h3>

            @if(count($recentSubmissions) > 0)
                <div class="flow-root">
                    <ul class="-mb-8">
                        @foreach($recentSubmissions as $index => $sub)
                            <li>
                                <div class="relative pb-8">
                                    @if($index < count($recentSubmissions) - 1)
                                        <span class="absolute top-4 left-3 -ml-px h-full w-0.5 bg-zinc-200 dark:bg-zinc-800" aria-hidden="true"></span>
                                    @endif
                                    <div class="relative flex space-x-3">
                                        <div>
                                            <span class="h-6 w-6 rounded-full border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center">
                                                <span class="h-2 w-2 rounded-full bg-indigo-500 dark:bg-indigo-400"></span>
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0 pt-0.5 flex justify-between space-x-4">
                                            <div class="text-xs text-zinc-550 dark:text-zinc-400">
                                                <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ $sub['evaluator'] }}</span> 
                                                evaluated 
                                                <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $sub['target'] }}</span>
                                                <span class="text-[10px] bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded-sm font-mono block mt-1.5 w-max">
                                                    {{ $sub['subject'] }}
                                                </span>
                                            </div>
                                            <div class="text-right text-[10px] whitespace-nowrap text-zinc-400 dark:text-zinc-550 font-semibold pt-1">
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
            <flux:card href="/admin/evaluation-settings" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="cog" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Configure Settings</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Manage schedules and criteria.</span>
                </div>
            </flux:card>

            <flux:card href="/reports" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="chart-bar" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Evaluation Reports</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Generate summary PDF reports.</span>
                </div>
            </flux:card>

            <flux:card href="/admin/questions" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs">
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl text-zinc-500 dark:text-zinc-400">
                    <flux:icon name="document-text" class="size-5" />
                </div>
                <div>
                    <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100 block">Edit Questionnaires</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 block font-medium">Customize evaluation queries.</span>
                </div>
            </flux:card>

            <flux:card href="/admin/students" class="p-5 flex items-start gap-4 hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition duration-150 cursor-pointer shadow-xs">
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
