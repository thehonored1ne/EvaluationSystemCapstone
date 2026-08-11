<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Semester;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationAnswer;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.reports-skeleton');
    }

    public ?int $selectedTeacherId = null;
    public ?int $selectedSemesterId = null;
    public string $activeTab = 'individual';

    public function mount()
    {
        $activeSem = Semester::where('is_active', true)->first();
        if ($activeSem) {
            $this->selectedSemesterId = $activeSem->id;
        }
    }

    public function getSemestersProperty()
    {
        return Semester::with('academicYear')->orderBy('id', 'desc')->get();
    }

    public function getTeachersProperty()
    {
        $user = auth()->user();
        $query = Employee::whereIn('role', ['faculty', 'program head', 'dean'])
            ->with('department')
            ->orderBy('first_name');

        if ($user->hasRole('program head')) {
            $query->where('department_id', $user->employee->department_id);
        } elseif ($user->hasRole('dean')) {
            $query->where('department_id', $user->employee->department_id);
        }

        return $query->get();
    }

    public function getReportDataProperty()
    {
        if (!$this->selectedTeacherId || !$this->selectedSemesterId) return null;

        $teacher = Employee::with(['user', 'department'])->findOrFail($this->selectedTeacherId);
        $userId = $teacher->user?->id;
        if (!$userId) return null;

        $semester = Semester::with('academicYear')->findOrFail($this->selectedSemesterId);

        $evalsQuery = Evaluation::with('sentiment')
            ->where('evaluatee_id', $userId)
            ->where('semester_id', $this->selectedSemesterId);

        $evaluations = $evalsQuery->get();
        $totalSubmissions = $evaluations->count();

        // Configure applicable categories based on teacher's role
        $role = $teacher->role;
        if ($role === 'faculty') {
            $applicableCategories = [
                'upward_student' => [
                    'label' => 'Student',
                    'max_points' => (float)$semester->upward_student_max_points,
                ],
                'peer' => [
                    'label' => 'Peer',
                    'max_points' => (float)$semester->peer_max_points,
                ],
                'downward' => [
                    'label' => 'Superior',
                    'max_points' => (float)$semester->downward_max_points,
                ],
                'self' => [
                    'label' => 'Self',
                    'max_points' => (float)$semester->self_max_points,
                ],
            ];
        } elseif ($role === 'program head') {
            $applicableCategories = [
                'upward_employee' => [
                    'label' => 'Subordinate',
                    'max_points' => (float)$semester->upward_employee_max_points,
                ],
                'downward' => [
                    'label' => 'Superior',
                    'max_points' => (float)$semester->downward_max_points,
                ],
                'self' => [
                    'label' => 'Self',
                    'max_points' => (float)$semester->self_max_points,
                ],
            ];
        } elseif ($role === 'dean') {
            $applicableCategories = [
                'upward_employee' => [
                    'label' => 'Subordinate',
                    'max_points' => (float)$semester->upward_employee_max_points,
                ],
                'self' => [
                    'label' => 'Self',
                    'max_points' => (float)$semester->self_max_points,
                ],
            ];
        } else {
            $applicableCategories = [];
        }

        $typeAverages = [];
        $totalSubmittedMaxPoints = 0.0;

        foreach ($applicableCategories as $type => $config) {
            $tCount = $evaluations->where('evaluation_type', $type)->count();
            $tAvg = $tCount > 0 ? round($evaluations->where('evaluation_type', $type)->avg('rating_average'), 2) : 0.00;
            
            $typeAverages[$type] = (object) [
                'label' => $config['label'],
                'count' => $tCount, 
                'average' => $tAvg,
                'max_points' => $config['max_points'],
            ];

            if ($tCount > 0) {
                $totalSubmittedMaxPoints += $config['max_points'];
            }
        }

        // Calculate category-weighted overall rating average
        $overallAverage = 0.00;
        if ($totalSubmittedMaxPoints > 0) {
            foreach ($typeAverages as $type => $info) {
                if ($info->count > 0) {
                    $weight = $info->max_points / $totalSubmittedMaxPoints;
                    $overallAverage += $info->average * $weight;
                }
            }
        }
        $overallAverage = round($overallAverage, 2);

        // Breakdown by Criteria
        $evalIds = $evaluations->pluck('id')->toArray();
        $criteria = EvaluationCriterion::whereIn('evaluation_type', array_keys($applicableCategories))
            ->orderBy('evaluation_type')
            ->orderBy('order')
            ->get()
            ->map(function ($criterion) use ($evalIds, $applicableCategories) {
                $answersAvg = EvaluationAnswer::whereIn('evaluation_id', $evalIds)
                    ->whereHas('question', function ($q) use ($criterion) {
                        $q->where('criterion_id', $criterion->id);
                    })
                    ->avg('rating');

                $label = $applicableCategories[$criterion->evaluation_type]['label'] ?? ucfirst($criterion->evaluation_type);

                return (object) [
                    'name' => $criterion->name,
                    'type' => $label,
                    'average' => $answersAvg ? round($answersAvg, 2) : null,
                ];
            })->filter(fn($c) => !is_null($c->average))->values();

        // AI Sentiment Analysis
        $commentsData = [];
        $posCount = 0;
        $neuCount = 0;
        $negCount = 0;

        foreach ($evaluations as $eval) {
            if ($eval->comments) {
                $label = $eval->sentiment?->active_label;
                if (!$label) {
                    $score = $eval->sentiment?->vader_score ?? 0;
                    $label = $score > 0.05 ? 'positive' : ($score < -0.05 ? 'negative' : 'neutral');
                }
                $label = strtolower($label);
                if ($label === 'positive') $posCount++;
                elseif ($label === 'negative') $negCount++;
                else $neuCount++;

                $commentsData[] = (object) [
                    'text' => $eval->comments,
                    'sentiment' => $label,
                    'type' => $applicableCategories[$eval->evaluation_type]['label'] ?? ucfirst($eval->evaluation_type),
                ];
            }
        }

        $totalComments = count($commentsData);
        $posPercent = $totalComments > 0 ? round(($posCount / $totalComments) * 100) : 0;
        $neuPercent = $totalComments > 0 ? round(($neuCount / $totalComments) * 100) : 0;
        $negPercent = $totalComments > 0 ? round(($negCount / $totalComments) * 100) : 0;

        if ($totalComments === 0) {
            $dominantSentiment = 'Neutral / No Comments';
        } elseif ($posPercent >= 60) {
            $dominantSentiment = 'Strongly Positive';
        } elseif ($posPercent > $negPercent) {
            $dominantSentiment = 'Mostly Positive';
        } elseif ($negPercent >= 40) {
            $dominantSentiment = 'Constructive / Critical';
        } else {
            $dominantSentiment = 'Balanced';
        }

        // Performance status text
        if ($overallAverage >= 4.50) {
            $performanceBadge = 'Outstanding';
        } elseif ($overallAverage >= 4.00) {
            $performanceBadge = 'Very Satisfactory';
        } elseif ($overallAverage >= 3.00) {
            $performanceBadge = 'Satisfactory';
        } else {
            $performanceBadge = 'Needs Improvement';
        }

        return (object) [
            'teacher' => $teacher,
            'semester' => $semester,
            'overall_average' => $overallAverage,
            'performance_badge' => $performanceBadge,
            'total_submissions' => $totalSubmissions,
            'type_averages' => $typeAverages,
            'criteria_breakdown' => $criteria,
            'comments_data' => $commentsData,
            'ai_sentiment' => (object) [
                'pos_percent' => $posPercent,
                'neu_percent' => $neuPercent,
                'neg_percent' => $negPercent,
                'pos_count' => $posCount,
                'neu_count' => $neuCount,
                'neg_count' => $negCount,
                'dominant_label' => $dominantSentiment,
                'total_comments' => $totalComments,
            ],
        ];
    }

    public function getSummaryReportDataProperty()
    {
        if (!$this->selectedSemesterId) return null;

        $semester = Semester::with('academicYear')->findOrFail($this->selectedSemesterId);
        $teachers = $this->teachers;

        $allEvals = Evaluation::with('sentiment')
            ->where('semester_id', $semester->id)
            ->get();

        $teacherRows = $teachers->map(function ($teacher) use ($semester, $allEvals) {
            $userId = $teacher->user?->id;
            
            if (!$userId) {
                return (object) [
                    'teacher' => $teacher,
                    'upward_student_average' => 0.00,
                    'upward_student_count' => 0,
                    'upward_employee_average' => 0.00,
                    'upward_employee_count' => 0,
                    'downward_average' => 0.00,
                    'downward_count' => 0,
                    'peer_average' => 0.00,
                    'peer_count' => 0,
                    'self_average' => 0.00,
                    'self_count' => 0,
                    'overall_average' => 0.00,
                    'submissions_count' => 0,
                    'sentiment_label' => 'Neutral',
                ];
            }

            $teacherEvals = $allEvals->where('evaluatee_id', $userId);
            $totalSubmissions = $teacherEvals->count();

            // Applicable categories
            $role = $teacher->role;
            if ($role === 'faculty') {
                $applicableCategories = [
                    'upward_student' => (float)$semester->upward_student_max_points,
                    'peer' => (float)$semester->peer_max_points,
                    'downward' => (float)$semester->downward_max_points,
                    'self' => (float)$semester->self_max_points,
                ];
            } elseif ($role === 'program head') {
                $applicableCategories = [
                    'upward_employee' => (float)$semester->upward_employee_max_points,
                    'downward' => (float)$semester->downward_max_points,
                    'self' => (float)$semester->self_max_points,
                ];
            } elseif ($role === 'dean') {
                $applicableCategories = [
                    'upward_employee' => (float)$semester->upward_employee_max_points,
                    'self' => (float)$semester->self_max_points,
                ];
            } else {
                $applicableCategories = [];
            }

            $typeAverages = [];
            $totalSubmittedMaxPoints = 0.0;

            foreach (['upward_student', 'upward_employee', 'downward', 'peer', 'self'] as $type) {
                $tEvals = $teacherEvals->where('evaluation_type', $type);
                $tCount = $tEvals->count();
                $tAvg = $tCount > 0 ? round($tEvals->avg('rating_average'), 2) : 0.00;
                
                $typeAverages[$type] = (object) [
                    'count' => $tCount,
                    'average' => $tAvg,
                ];

                if (array_key_exists($type, $applicableCategories) && $tCount > 0) {
                    $totalSubmittedMaxPoints += $applicableCategories[$type];
                }
            }

            // Category-weighted overall average
            $overallAverage = 0.00;
            if ($totalSubmittedMaxPoints > 0) {
                foreach ($applicableCategories as $type => $maxPoints) {
                    $info = $typeAverages[$type];
                    if ($info->count > 0) {
                        $weight = $maxPoints / $totalSubmittedMaxPoints;
                        $overallAverage += $info->average * $weight;
                    }
                }
            }
            $overallAverage = round($overallAverage, 2);

            // Teacher sentiment
            $pos = 0; $neg = 0; $neu = 0;
            foreach ($teacherEvals as $e) {
                if ($e->comments) {
                    $l = strtolower($e->sentiment?->active_label ?? '');
                    if ($l === 'positive') $pos++;
                    elseif ($l === 'negative') $neg++;
                    else $neu++;
                }
            }
            if ($pos > $neg && $pos > 0) $sLabel = 'Positive';
            elseif ($neg > $pos) $sLabel = 'Constructive';
            else $sLabel = 'Neutral';

            return (object) [
                'teacher' => $teacher,
                'upward_student_average' => $typeAverages['upward_student']->average,
                'upward_student_count' => $typeAverages['upward_student']->count,
                'upward_employee_average' => $typeAverages['upward_employee']->average,
                'upward_employee_count' => $typeAverages['upward_employee']->count,
                'downward_average' => $typeAverages['downward']->average,
                'downward_count' => $typeAverages['downward']->count,
                'peer_average' => $typeAverages['peer']->average,
                'peer_count' => $typeAverages['peer']->count,
                'self_average' => $typeAverages['self']->average,
                'self_count' => $typeAverages['self']->count,
                'overall_average' => $overallAverage,
                'submissions_count' => $totalSubmissions,
                'sentiment_label' => $sLabel,
            ];
        })->sortByDesc('overall_average')->values();

        // Compute overall institutional metrics across all semester evaluations
        $totalInstSubmissions = $allEvals->count();
        $instOverallAvg = $teacherRows->where('submissions_count', '>', 0)->avg('overall_average');
        $instOverallAvg = $instOverallAvg ? round($instOverallAvg, 2) : 0.00;

        // Role Category Institutional Averages
        $studentAvg = $allEvals->where('evaluation_type', 'upward_student')->avg('rating_average');
        $subordinateAvg = $allEvals->where('evaluation_type', 'upward_employee')->avg('rating_average');
        $superiorAvg = $allEvals->where('evaluation_type', 'downward')->avg('rating_average');
        $peerAvg = $allEvals->where('evaluation_type', 'peer')->avg('rating_average');
        $selfAvg = $allEvals->where('evaluation_type', 'self')->avg('rating_average');

        // Overall AI Sentiment across all semester comments
        $posCount = 0; $neuCount = 0; $negCount = 0;
        foreach ($allEvals as $e) {
            if ($e->comments) {
                $l = strtolower($e->sentiment?->active_label ?? '');
                if ($l === 'positive') $posCount++;
                elseif ($l === 'negative') $negCount++;
                else $neuCount++;
            }
        }
        $totalComments = $posCount + $neuCount + $negCount;
        $posPercent = $totalComments > 0 ? round(($posCount / $totalComments) * 100) : 0;
        $neuPercent = $totalComments > 0 ? round(($neuCount / $totalComments) * 100) : 0;
        $negPercent = $totalComments > 0 ? round(($negCount / $totalComments) * 100) : 0;

        return (object) [
            'semester' => $semester,
            'teachers' => $teacherRows,
            'total_teachers' => $teacherRows->count(),
            'total_submissions' => $totalInstSubmissions,
            'institutional_average' => $instOverallAvg,
            'category_averages' => (object) [
                'student' => $studentAvg ? round($studentAvg, 2) : 0.00,
                'subordinate' => $subordinateAvg ? round($subordinateAvg, 2) : 0.00,
                'superior' => $superiorAvg ? round($superiorAvg, 2) : 0.00,
                'peer' => $peerAvg ? round($peerAvg, 2) : 0.00,
                'self' => $selfAvg ? round($selfAvg, 2) : 0.00,
            ],
            'ai_sentiment' => (object) [
                'pos_percent' => $posPercent,
                'neu_percent' => $neuPercent,
                'neg_percent' => $negPercent,
                'pos_count' => $posCount,
                'neu_count' => $neuCount,
                'neg_count' => $negCount,
                'total_comments' => $totalComments,
            ],
        ];
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-5xl mx-auto px-4 py-6">
    <!-- Navigation Tabs (Hidden on Print) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 print:hidden">
        <button wire:click="$set('activeTab', 'individual')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-all duration-200 {{ $activeTab === 'individual' ? 'border-[#800000] text-[#800000] dark:border-red-500 dark:text-red-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Individual Report
        </button>
        <button wire:click="$set('activeTab', 'summary')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-all duration-200 {{ $activeTab === 'summary' ? 'border-[#800000] text-[#800000] dark:border-red-500 dark:text-red-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Summary Report
        </button>
    </div>

    <!-- Filters (Hidden on Print) -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm print:hidden">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                {{ $activeTab === 'individual' ? 'Evaluation Reports' : 'Summary Evaluation Reports' }}
            </h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                {{ $activeTab === 'individual' ? 'Select a professor and semester to generate and print a performance summary report.' : 'Select a semester to generate and print an executive performance summary report.' }}
            </p>
        </div>

        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto shrink-0">
            @if($activeTab === 'individual')
                <div class="w-full md:w-64">
                    <x-searchable-select 
                        name="selectedTeacherId" 
                        placeholder="Select Professor" 
                        :live="true" 
                        :options="$this->teachers->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->full_name . ' (' . $t->employee_number . ')'])->toArray()" 
                    />
                </div>
            @endif

            <div class="w-full md:w-48">
                <flux:select wire:model.live="selectedSemesterId" placeholder="Select Semester">
                    @foreach($this->semesters as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Loading Skeleton Placeholder -->
    <div wire:loading wire:target="selectedTeacherId, selectedSemesterId, activeTab">
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 space-y-8 flex flex-col gap-8 print:border-none print:shadow-none">
            <div class="text-center border-b-2 border-zinc-150 pb-6 flex flex-col items-center justify-center gap-2">
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-72 shimmer"></div>
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 mt-1 shimmer"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-zinc-50 dark:bg-zinc-800/20 p-6 rounded-xl border border-zinc-150 dark:border-zinc-800">
                <div class="space-y-2"><div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div><div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div></div>
                <div class="space-y-2"><div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div><div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <x-skeleton type="card" />
                <x-skeleton type="card" />
                <x-skeleton type="card" />
                <x-skeleton type="card" />
            </div>
        </div>
    </div>

    <!-- Print Report Body -->
    <div wire:loading.remove wire:target="selectedTeacherId, selectedSemesterId, activeTab">
        <!-- INDIVIDUAL REPORT TAB -->
        @if($activeTab === 'individual')
            @if($this->reportData)
                @php $data = $this->reportData; @endphp
                
                <!-- Print Button (Hidden on Print) -->
                <div class="flex justify-end print:hidden mb-4">
                    <flux:button variant="primary" icon="printer" onclick="window.print()" class="bg-[#800000] hover:bg-[#990000] text-white">
                        Print Individual Report
                    </flux:button>
                </div>

                <!-- Printable Document Container -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 flex flex-col gap-8 print:border-none print:shadow-none print:bg-white print:text-black">
                    
                    <!-- Header -->
                    <div class="text-center border-b-2 border-zinc-850 pb-6 flex flex-col gap-2">
                        <h2 class="text-2xl font-black uppercase tracking-wide text-zinc-900 dark:text-zinc-50 print:text-black">Performance Evaluation Report</h2>
                        <p class="text-sm font-semibold text-zinc-500 print:text-zinc-600">
                            Academic Period: {{ $data->semester->academicYear->name }} - {{ $data->semester->name }}
                        </p>
                    </div>

                    <!-- Profile Details Card -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-zinc-50 dark:bg-zinc-800/20 p-6 rounded-xl border border-zinc-150 dark:border-zinc-800 print:bg-zinc-50 print:text-black print:border-zinc-300">
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Professor Name</span>
                            <span class="font-bold text-zinc-850 dark:text-zinc-50 print:text-black text-lg">{{ $data->teacher->full_name }}</span>
                        </div>
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Department / College</span>
                            <span class="font-bold text-zinc-850 dark:text-zinc-50 print:text-black text-lg">
                                {{ $data->teacher->department->name ?? 'N/A' }} ({{ $data->teacher->department->code ?? 'N/A' }})
                            </span>
                        </div>
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Employee ID</span>
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300 print:text-black">{{ $data->teacher->employee_number }}</span>
                        </div>
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Employee Designation</span>
                            <span class="font-semibold text-zinc-700 dark:text-zinc-300 print:text-black">{{ ucfirst($data->teacher->role) }}</span>
                        </div>
                    </div>

                    <!-- Overall & Category Score Cards -->
                    @php
                        $numItems = count($data->type_averages) + 1;
                        $gridClass = match($numItems) {
                            3 => 'grid-cols-1 sm:grid-cols-3',
                            4 => 'grid-cols-1 sm:grid-cols-2 md:grid-cols-4',
                            5 => 'grid-cols-1 sm:grid-cols-3 md:grid-cols-5',
                            default => 'grid-cols-1 sm:grid-cols-3',
                        };
                    @endphp
                    <div class="grid {{ $gridClass }} gap-4">
                        <div class="border-2 border-[#800000] dark:border-red-500 p-5 rounded-2xl text-center bg-red-50/20 dark:bg-red-950/20 shadow-sm flex flex-col justify-center items-center">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Overall Score</div>
                            <div class="text-3xl font-black text-[#800000] dark:text-red-400 mt-1">
                                {{ number_format($data->overall_average, 2) }} <span class="text-xs font-normal text-zinc-500">/ 5.0</span>
                            </div>
                            <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#800000]/10 text-[#800000] dark:bg-red-950 dark:text-red-300">
                                {{ $data->performance_badge }}
                            </span>
                        </div>

                        @foreach($data->type_averages as $type => $info)
                            <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl text-center flex flex-col justify-center items-center bg-white dark:bg-zinc-900">
                                <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $info->label }} Rating</div>
                                <div class="text-2xl font-bold text-zinc-850 dark:text-zinc-200 print:text-black mt-1">
                                    @if($info->count > 0)
                                        {{ number_format($info->average, 2) }}
                                        <span class="text-xs font-medium text-zinc-400 block mt-0.5">({{ $info->count }} {{ $info->count == 1 ? 'report' : 'reports' }})</span>
                                    @else
                                        <span class="text-sm font-semibold text-zinc-400 block mt-0.5">N/A</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Criteria Performance Progress Cards (No Table) -->
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Evaluation Criteria Breakdown</h3>
                            <span class="text-xs font-semibold text-zinc-500">{{ $data->criteria_breakdown->count() }} Criteria Evaluated</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($data->criteria_breakdown as $c)
                                @php
                                    $percent = min(100, max(0, ($c->average / 5.0) * 100));
                                @endphp
                                <div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col gap-3">
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-bold text-zinc-900 dark:text-zinc-100 print:text-black text-sm">{{ $c->name }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-zinc-200/60 dark:bg-zinc-700/60 text-zinc-700 dark:text-zinc-300 w-max">
                                                {{ $c->type }}
                                            </span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-lg font-black text-[#800000] dark:text-red-400 print:text-black">
                                                {{ number_format($c->average, 2) }}
                                            </span>
                                            <span class="text-xs text-zinc-400 font-medium">/ 5.0</span>
                                        </div>
                                    </div>
                                    <!-- Progress Bar -->
                                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2.5 rounded-full overflow-hidden">
                                        <div class="bg-[#800000] dark:bg-red-500 h-full rounded-full transition-all duration-300" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- AI Sentiment & Performance Analysis Block -->
                    <div class="flex flex-col gap-4 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 rounded-lg bg-red-100 dark:bg-red-950/60 text-[#800000] dark:text-red-400">
                                    <flux:icon icon="sparkles" class="size-5" />
                                </div>
                                <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">AI Sentiment & Feedback Analysis</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                Overall Sentiment: {{ $data->ai_sentiment->dominant_label }}
                            </span>
                        </div>

                        <div class="bg-gradient-to-br from-zinc-50 via-white to-red-50/20 dark:from-zinc-900 dark:to-zinc-800/40 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 flex flex-col gap-6">
                            <!-- Sentiment Bar Distribution -->
                            <div class="flex flex-col gap-2">
                                <div class="flex justify-between items-center text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                    <span>Sentiment Distribution ({{ $data->ai_sentiment->total_comments }} Comments Analyzed)</span>
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <span class="size-2 rounded-full bg-emerald-500"></span> Positive: {{ $data->ai_sentiment->pos_percent }}%
                                        </span>
                                        <span class="flex items-center gap-1 text-zinc-500 dark:text-zinc-400">
                                            <span class="size-2 rounded-full bg-zinc-400"></span> Neutral: {{ $data->ai_sentiment->neu_percent }}%
                                        </span>
                                        <span class="flex items-center gap-1 text-rose-600 dark:text-rose-400">
                                            <span class="size-2 rounded-full bg-rose-500"></span> Constructive: {{ $data->ai_sentiment->neg_percent }}%
                                        </span>
                                    </div>
                                </div>

                                <!-- Multi-segment progress bar -->
                                <div class="w-full h-3 bg-zinc-150 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                                    <div class="bg-emerald-500 h-full transition-all duration-300" style="width: {{ $data->ai_sentiment->pos_percent }}%"></div>
                                    <div class="bg-zinc-400 dark:bg-zinc-600 h-full transition-all duration-300" style="width: {{ $data->ai_sentiment->neu_percent }}%"></div>
                                    <div class="bg-rose-500 h-full transition-all duration-300" style="width: {{ $data->ai_sentiment->neg_percent }}%"></div>
                                </div>
                            </div>

                            <!-- Executive Key Insights -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div class="p-4 bg-white dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex flex-col gap-1.5">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1 text-xs">
                                        <flux:icon icon="hand-thumb-up" class="size-4 text-emerald-500" />
                                        Key Strengths Identified
                                    </span>
                                    <p class="text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                        @if($data->criteria_breakdown->isNotEmpty())
                                            Demonstrates strong performance in <span class="font-bold text-zinc-800 dark:text-zinc-200">'{{ $data->criteria_breakdown->first()->name }}'</span> with an average score of <span class="font-bold text-[#800000] dark:text-red-400">{{ number_format($data->criteria_breakdown->first()->average, 2) }}</span>.
                                        @else
                                            Overall positive feedback received across key instructional indicators.
                                        @endif
                                    </p>
                                </div>

                                <div class="p-4 bg-white dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex flex-col gap-1.5">
                                    <span class="font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-1 text-xs">
                                        <flux:icon icon="chart-bar" class="size-4 text-amber-500" />
                                        Development & Focus Area
                                    </span>
                                    <p class="text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                        @if($data->criteria_breakdown->count() > 1)
                                            Opportunities for enhancement in <span class="font-bold text-zinc-800 dark:text-zinc-200">'{{ $data->criteria_breakdown->last()->name }}'</span> (average score: <span class="font-bold text-amber-600 dark:text-amber-400">{{ number_format($data->criteria_breakdown->last()->average, 2) }}</span>).
                                        @else
                                            Continue maintaining existing instructional quality standards.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submitted Comments Cards Stream -->
                    <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Submitted Evaluation Comments</h3>
                        @if(empty($data->comments_data))
                            <p class="text-sm text-zinc-400 italic">No text comments submitted for this professor.</p>
                        @else
                            <div class="grid grid-cols-1 gap-3">
                                @foreach($data->comments_data as $cItem)
                                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200 dark:border-zinc-800 flex flex-col gap-2">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-bold text-zinc-500 uppercase tracking-wider text-[10px]">{{ $cItem->type }} Feedback</span>
                                            @if($cItem->sentiment === 'positive')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Positive</span>
                                            @elseif($cItem->sentiment === 'negative')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">Constructive</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">Neutral</span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-zinc-800 dark:text-zinc-200 print:text-black italic">
                                            "{{ $cItem->text }}"
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Signature Lines (Visible on Print) -->
                    <div class="hidden print:flex justify-between mt-16 pt-8 border-t border-zinc-200 text-sm">
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-48 border-b border-zinc-900"></div>
                            <span class="font-bold mt-1">Evaluated Professor Signature</span>
                            <span class="text-xs text-zinc-500">Date Signed</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-48 border-b border-zinc-900"></div>
                            <span class="font-bold mt-1">Dean / Department Head Signature</span>
                            <span class="text-xs text-zinc-500">Date Signed</span>
                        </div>
                    </div>

                </div>
            @else
                <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                    <flux:icon icon="document-chart-bar" class="size-16 mx-auto text-zinc-300 mb-3" />
                    <p class="font-medium text-zinc-500">Please select a professor and academic semester to load the performance report.</p>
                </div>
            @endif
        @endif

        <!-- SUMMARY REPORT TAB -->
        @if($activeTab === 'summary')
            @if($selectedSemesterId && $this->summaryReportData)
                @php 
                    $summary = $this->summaryReportData;
                @endphp
                
                <!-- Print Button (Hidden on Print) -->
                <div class="flex justify-end print:hidden mb-4">
                    <flux:button variant="primary" icon="printer" onclick="window.print()" class="bg-[#800000] hover:bg-[#990000] text-white">
                        Print Summary Report
                    </flux:button>
                </div>

                <!-- Printable Document Container -->
                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 flex flex-col gap-8 print:border-none print:shadow-none print:bg-white print:text-black">
                    
                    <!-- Document Header -->
                    <div class="text-center border-b-2 border-zinc-850 pb-6 flex flex-col gap-2">
                        <h2 class="text-2xl font-black uppercase tracking-wide text-zinc-900 dark:text-zinc-50 print:text-black">Evaluation Summary Report</h2>
                        <p class="text-sm font-semibold text-zinc-500 print:text-zinc-600">
                            Academic Period: {{ $summary->semester->academicYear->name }} - {{ $summary->semester->name }}
                        </p>
                    </div>

                    <!-- Scope Details Card -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-zinc-50 dark:bg-zinc-800/20 p-6 rounded-xl border border-zinc-150 dark:border-zinc-800 print:bg-zinc-50 print:text-black print:border-zinc-300">
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Report Scope</span>
                            <span class="font-bold text-zinc-850 dark:text-zinc-50 print:text-black text-lg">
                                @if(auth()->user()->hasRole('admin'))
                                    All Departments / Institution-wide
                                @else
                                    {{ auth()->user()->employee->department->name ?? 'Department-wide' }}
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-col gap-1 text-sm">
                            <span class="text-xs uppercase tracking-wider text-zinc-400 font-bold">Total Faculty Members</span>
                            <span class="font-bold text-zinc-850 dark:text-zinc-50 print:text-black text-lg">
                                {{ $summary->total_teachers }} Evaluated
                            </span>
                        </div>
                    </div>

                    <!-- Executive Metric Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="border-2 border-[#800000] dark:border-red-500 p-5 rounded-2xl text-center bg-red-50/20 dark:bg-red-950/20 flex flex-col justify-center items-center">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Institutional Average</div>
                            <div class="text-3xl font-black text-[#800000] dark:text-red-400 mt-1">
                                {{ number_format($summary->institutional_average, 2) }} <span class="text-xs font-normal text-zinc-500">/ 5.0</span>
                            </div>
                            <span class="text-xs text-zinc-400 mt-1">Across all categories</span>
                        </div>

                        <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl text-center flex flex-col justify-center items-center bg-white dark:bg-zinc-900">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Student Average</div>
                            <div class="text-2xl font-bold text-zinc-850 dark:text-zinc-200 mt-1">
                                {{ number_format($summary->category_averages->student, 2) }}
                            </div>
                            <span class="text-xs text-zinc-400 mt-0.5">Upward Student</span>
                        </div>

                        <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl text-center flex flex-col justify-center items-center bg-white dark:bg-zinc-900">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Peer Average</div>
                            <div class="text-2xl font-bold text-zinc-850 dark:text-zinc-200 mt-1">
                                {{ number_format($summary->category_averages->peer, 2) }}
                            </div>
                            <span class="text-xs text-zinc-400 mt-0.5">Peer Evaluations</span>
                        </div>

                        <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-2xl text-center flex flex-col justify-center items-center bg-white dark:bg-zinc-900">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Submissions</div>
                            <div class="text-2xl font-bold text-zinc-850 dark:text-zinc-200 mt-1">
                                {{ $summary->total_submissions }}
                            </div>
                            <span class="text-xs text-zinc-400 mt-0.5">Evaluations Completed</span>
                        </div>
                    </div>

                    <!-- AI Institutional Analysis Block -->
                    <div class="flex flex-col gap-4 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 rounded-lg bg-red-100 dark:bg-red-950/60 text-[#800000] dark:text-red-400">
                                    <flux:icon icon="sparkles" class="size-5" />
                                </div>
                                <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">AI Institutional Analysis & Sentiment</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                {{ $summary->ai_sentiment->pos_percent }}% Positive Sentiment
                            </span>
                        </div>

                        <div class="bg-gradient-to-br from-zinc-50 via-white to-red-50/20 dark:from-zinc-900 dark:to-zinc-800/40 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 flex flex-col gap-5">
                            <!-- Sentiment distribution bar -->
                            <div class="flex flex-col gap-2">
                                <div class="flex justify-between items-center text-xs font-bold text-zinc-700 dark:text-zinc-300">
                                    <span>Institution-wide Feedback Sentiment ({{ $summary->ai_sentiment->total_comments }} Total Comments)</span>
                                    <div class="flex items-center gap-4">
                                        <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <span class="size-2 rounded-full bg-emerald-500"></span> Positive: {{ $summary->ai_sentiment->pos_percent }}%
                                        </span>
                                        <span class="flex items-center gap-1 text-zinc-500 dark:text-zinc-400">
                                            <span class="size-2 rounded-full bg-zinc-400"></span> Neutral: {{ $summary->ai_sentiment->neu_percent }}%
                                        </span>
                                        <span class="flex items-center gap-1 text-rose-600 dark:text-rose-400">
                                            <span class="size-2 rounded-full bg-rose-500"></span> Constructive: {{ $summary->ai_sentiment->neg_percent }}%
                                        </span>
                                    </div>
                                </div>

                                <div class="w-full h-3 bg-zinc-150 dark:bg-zinc-800 rounded-full overflow-hidden flex">
                                    <div class="bg-emerald-500 h-full transition-all duration-300" style="width: {{ $summary->ai_sentiment->pos_percent }}%"></div>
                                    <div class="bg-zinc-400 dark:bg-zinc-600 h-full transition-all duration-300" style="width: {{ $summary->ai_sentiment->neu_percent }}%"></div>
                                    <div class="bg-rose-500 h-full transition-all duration-300" style="width: {{ $summary->ai_sentiment->neg_percent }}%"></div>
                                </div>
                            </div>

                            <p class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">Executive Summary:</span> For academic period <span class="font-semibold">{{ $summary->semester->academicYear->name }} - {{ $summary->semester->name }}</span>, institutional performance averaged <span class="font-bold text-[#800000] dark:text-red-400">{{ number_format($summary->institutional_average, 2) }} / 5.0</span> across {{ $summary->total_submissions }} submitted evaluation forms. Sentiment analysis indicates high satisfaction with faculty instruction and professional delivery.
                            </p>
                        </div>
                    </div>

                    <!-- Faculty Performance Grid Cards (No Table) -->
                    <div class="flex flex-col gap-4 border-t border-zinc-200 dark:border-zinc-800 pt-6">
                        <div class="flex items-center justify-between">
                            <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Faculty Performance Overview</h3>
                            <span class="text-xs font-semibold text-zinc-500">{{ $summary->teachers->count() }} Faculty Members</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($summary->teachers as $row)
                                <div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-5 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col gap-3.5 hover:border-red-900/40 transition-all">
                                    <!-- Top Row: Name, Dept, Score -->
                                    <div class="flex justify-between items-start gap-3">
                                        <div class="flex flex-col gap-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-zinc-900 dark:text-zinc-100 print:text-black text-base">{{ $row->teacher->full_name }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                    {{ $row->teacher->department->code ?? 'N/A' }}
                                                </span>
                                            </div>
                                            <span class="text-xs text-zinc-400 font-mono">{{ $row->teacher->employee_number }} • {{ ucfirst($row->teacher->role) }}</span>
                                        </div>

                                        <div class="text-right shrink-0">
                                            @if($row->submissions_count > 0)
                                                <span class="text-xl font-black text-[#800000] dark:text-red-400 print:text-black">
                                                    {{ number_format($row->overall_average, 2) }}
                                                </span>
                                                <span class="text-xs text-zinc-400 font-medium">/ 5.0</span>
                                            @else
                                                <span class="text-sm font-semibold text-zinc-400">N/A</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Middle Row: Category Score Pills -->
                                    <div class="flex flex-wrap gap-2 text-[11px]">
                                        @if($row->upward_student_count > 0)
                                            <span class="px-2 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                Student: <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($row->upward_student_average, 2) }}</span>
                                            </span>
                                        @endif
                                        @if($row->peer_count > 0)
                                            <span class="px-2 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                Peer: <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($row->peer_average, 2) }}</span>
                                            </span>
                                        @endif
                                        @if($row->downward_count > 0)
                                            <span class="px-2 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                Superior: <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($row->downward_average, 2) }}</span>
                                            </span>
                                        @endif
                                        @if($row->self_count > 0)
                                            <span class="px-2 py-1 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                Self: <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($row->self_average, 2) }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Bottom Row: Submissions & AI Sentiment Badge -->
                                    <div class="flex items-center justify-between border-t border-zinc-200/60 dark:border-zinc-700/60 pt-2.5 text-xs text-zinc-500">
                                        <span>{{ $row->submissions_count }} {{ $row->submissions_count == 1 ? 'Submission' : 'Submissions' }}</span>
                                        @if($row->sentiment_label === 'Positive')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Positive Sentiment</span>
                                        @elseif($row->sentiment_label === 'Constructive')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">Constructive Feedback</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-zinc-200 text-zinc-800 dark:bg-zinc-700 dark:text-zinc-300">Neutral Sentiment</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Signature Lines (Visible on Print) -->
                    <div class="hidden print:flex justify-between mt-16 pt-8 border-t border-zinc-200 text-sm">
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-48 border-b border-zinc-900"></div>
                            <span class="font-bold mt-1">Prepared By</span>
                            <span class="text-xs text-zinc-500">Signature Over Printed Name</span>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-48 border-b border-zinc-900"></div>
                            <span class="font-bold mt-1">Approved By</span>
                            <span class="text-xs text-zinc-500">Dean / Administrator Signature</span>
                        </div>
                    </div>

                </div>
            @else
                <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                    <flux:icon icon="document-chart-bar" class="size-16 mx-auto text-zinc-300 mb-3" />
                    <p class="font-medium text-zinc-500">No evaluation data found for the selected semester.</p>
                </div>
            @endif
        @endif
    </div>
</div>
