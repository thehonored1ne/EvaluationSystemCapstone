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

        $evalsQuery = Evaluation::where('evaluatee_id', $userId)
            ->where('semester_id', $this->selectedSemesterId);

        $totalSubmissions = $evalsQuery->count();

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
            $tQuery = clone $evalsQuery;
            $tCount = $tQuery->where('evaluation_type', $type)->count();
            $tQuery2 = clone $evalsQuery;
            $tAvg = $tCount > 0 ? round($tQuery2->where('evaluation_type', $type)->avg('rating_average'), 2) : 0.00;
            
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
        $evalIds = $evalsQuery->pluck('id')->toArray();
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
            })->filter(fn($c) => !is_null($c->average));

        // Comments
        $comments = $evalsQuery->whereNotNull('comments')->pluck('comments')->toArray();

        return (object) [
            'teacher' => $teacher,
            'semester' => $semester,
            'overall_average' => $overallAverage,
            'total_submissions' => $totalSubmissions,
            'type_averages' => $typeAverages,
            'criteria_breakdown' => $criteria,
            'comments' => $comments,
        ];
    }

    public function getSummaryReportDataProperty()
    {
        if (!$this->selectedSemesterId) return collect();

        $semester = Semester::with('academicYear')->findOrFail($this->selectedSemesterId);
        $teachers = $this->teachers;

        return $teachers->map(function ($teacher) use ($semester) {
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
                ];
            }

            $evalsQuery = Evaluation::where('evaluatee_id', $userId)
                ->where('semester_id', $semester->id);

            $totalSubmissions = $evalsQuery->count();

            // Set up applicable categories based on teacher's role
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
                $tQuery = clone $evalsQuery;
                $tCount = $tQuery->where('evaluation_type', $type)->count();
                $tQuery2 = clone $evalsQuery;
                $tAvg = $tCount > 0 ? round($tQuery2->where('evaluation_type', $type)->avg('rating_average'), 2) : 0.00;
                
                $typeAverages[$type] = (object) [
                    'count' => $tCount,
                    'average' => $tAvg,
                ];

                if (array_key_exists($type, $applicableCategories) && $tCount > 0) {
                    $totalSubmittedMaxPoints += $applicableCategories[$type];
                }
            }

            // Calculate category-weighted overall rating average
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
            ];
        })->sortByDesc('overall_average');
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-5xl mx-auto px-4 py-6">
    <!-- Navigation Tabs (Hidden on Print) -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 print:hidden">
        <button wire:click="$set('activeTab', 'individual')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-all duration-200 {{ $activeTab === 'individual' ? 'border-indigo-650 text-indigo-650 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
            Individual Report
        </button>
        <button wire:click="$set('activeTab', 'summary')" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-all duration-200 {{ $activeTab === 'summary' ? 'border-indigo-650 text-indigo-650 dark:border-indigo-400 dark:text-indigo-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
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
                {{ $activeTab === 'individual' ? 'Select a teacher and semester to generate and print a performance summary report.' : 'Select a semester to generate and print a performance summary report for all professors.' }}
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
        @if($activeTab === 'individual')
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
                <div class="space-y-4">
                    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    <x-skeleton type="table" :rows="4" :cols="3" />
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 space-y-8 flex flex-col gap-8 print:border-none print:shadow-none">
                <div class="text-center border-b-2 border-zinc-150 pb-6 flex flex-col items-center justify-center gap-2">
                    <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-72 shimmer"></div>
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 mt-1 shimmer"></div>
                </div>
                <x-skeleton type="table" :rows="6" :cols="8" />
            </div>
        @endif
    </div>

    <!-- Print Report Body -->
    <div wire:loading.remove wire:target="selectedTeacherId, selectedSemesterId, activeTab">
        @if($activeTab === 'individual')
        @if($this->reportData)
            @php $data = $this->reportData; @endphp
            
            <!-- Print Button (Hidden on Print) -->
            <div class="flex justify-end print:hidden">
                <flux:button variant="primary" icon="printer" onclick="window.print()">
                    Print Report
                </flux:button>
            </div>

            <!-- Printable Document -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 flex flex-col gap-8 print:border-none print:shadow-none print:bg-white print:text-black">
                
                <!-- Document Header -->
                <div class="text-center border-b-2 border-zinc-850 pb-6 flex flex-col gap-2">
                    <h2 class="text-2xl font-black uppercase tracking-wide text-zinc-900 dark:text-zinc-50 print:text-black">Performance Evaluation Report</h2>
                    <p class="text-sm font-semibold text-zinc-500 print:text-zinc-600">
                        Academic Period: {{ $data->semester->academicYear->name }} - {{ $data->semester->name }}
                    </p>
                </div>

                <!-- Profile Details -->
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

                <!-- Summary metrics -->
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
                    <div class="border-2 border-indigo-600 dark:border-indigo-400 p-4 rounded-xl text-center bg-indigo-50/20">
                        <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Overall Score</div>
                        <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
                            {{ number_format($data->overall_average, 2) }} <span class="text-xs font-normal">/ 5.0</span>
                        </div>
                    </div>

                    @foreach($data->type_averages as $type => $info)
                        <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-xl text-center">
                            <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ $info->label }} Rating</div>
                            <div class="text-xl font-bold text-zinc-850 dark:text-zinc-200 print:text-black mt-1">
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

                <!-- Criteria Performance Table -->
                <div class="flex flex-col gap-3">
                    <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Evaluation Criteria Breakdown</h3>
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 print:border-zinc-300">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 font-semibold border-b border-zinc-200 dark:border-zinc-800 print:bg-zinc-100 print:border-zinc-300">
                                <tr>
                                    <th class="px-6 py-3">Criterion</th>
                                    <th class="px-6 py-3">Evaluation Type</th>
                                    <th class="px-6 py-3 text-right">Average Rating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900 print:divide-zinc-200">
                                @foreach($data->criteria_breakdown as $c)
                                    <tr>
                                        <td class="px-6 py-3.5 font-bold text-zinc-800 dark:text-zinc-200 print:text-black">{{ $c->name }}</td>
                                        <td class="px-6 py-3.5 text-zinc-500">{{ $c->type }}</td>
                                        <td class="px-6 py-3.5 text-right font-black text-zinc-850 dark:text-zinc-150 print:text-black">
                                            {{ number_format($c->average, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Comments -->
                <div class="flex flex-col gap-3">
                    <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Submitted Comments</h3>
                    @if(empty($data->comments))
                        <p class="text-sm text-zinc-400 italic">No text comments submitted for this teacher.</p>
                    @else
                        <div class="flex flex-col gap-2 p-4 bg-zinc-50 dark:bg-zinc-800/10 rounded-xl border border-zinc-150 dark:border-zinc-800 print:bg-zinc-50 print:border-zinc-300">
                            @foreach($data->comments as $comment)
                                <div class="text-sm text-zinc-700 dark:text-zinc-300 print:text-black p-2 border-b border-zinc-200 dark:border-zinc-800 last:border-none">
                                    - "{{ $comment }}"
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
                <p class="font-medium text-zinc-500">Please select a professor and academic semester to load the report card.</p>
            </div>
        @endif
    @endif

    @if($activeTab === 'summary')
        @if($selectedSemesterId && !$this->summaryReportData->isEmpty())
            @php 
                $semester = \App\Models\Semester::with('academicYear')->find($selectedSemesterId);
                $summaryData = $this->summaryReportData;
            @endphp
            
            <!-- Print Button (Hidden on Print) -->
            <div class="flex justify-end print:hidden">
                <flux:button variant="primary" icon="printer" onclick="window.print()">
                    Print Summary Report
                </flux:button>
            </div>

            <!-- Printable Document -->
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 flex flex-col gap-8 print:border-none print:shadow-none print:bg-white print:text-black">
                
                <!-- Document Header -->
                <div class="text-center border-b-2 border-zinc-850 pb-6 flex flex-col gap-2">
                    <h2 class="text-2xl font-black uppercase tracking-wide text-zinc-900 dark:text-zinc-50 print:text-black">Evaluation Summary Report</h2>
                    <p class="text-sm font-semibold text-zinc-500 print:text-zinc-600">
                        Academic Period: {{ $semester->academicYear->name }} - {{ $semester->name }}
                    </p>
                </div>

                <!-- Scope Details -->
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
                            {{ $summaryData->count() }}
                        </span>
                    </div>
                </div>

                <!-- Summary Report Table -->
                <div class="flex flex-col gap-3">
                    <h3 class="font-black text-zinc-900 dark:text-zinc-50 print:text-black text-base uppercase tracking-wider">Evaluation Summary Overview</h3>
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800 print:border-zinc-300">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 font-semibold border-b border-zinc-200 dark:border-zinc-800 print:bg-zinc-100 print:border-zinc-300">
                                <tr>
                                    <th class="px-4 py-3">Employee ID</th>
                                    <th class="px-4 py-3">Professor Name</th>
                                    <th class="px-4 py-3">Department</th>
                                    <th class="px-4 py-3 text-center">Student</th>
                                    <th class="px-4 py-3 text-center">Subordinate</th>
                                    <th class="px-4 py-3 text-center">Superior</th>
                                    <th class="px-4 py-3 text-center">Peer</th>
                                    <th class="px-4 py-3 text-center">Self</th>
                                    <th class="px-4 py-3 text-center">Submissions</th>
                                    <th class="px-4 py-3 text-right">Overall Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900 print:divide-zinc-200">
                                @foreach($summaryData as $row)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                        <td class="px-4 py-3.5 text-zinc-700 dark:text-zinc-300 print:text-black font-mono text-xs">
                                            {{ $row->teacher->employee_number }}
                                        </td>
                                        <td class="px-4 py-3.5 font-bold text-zinc-850 dark:text-zinc-200 print:text-black">
                                            {{ $row->teacher->full_name }}
                                        </td>
                                        <td class="px-4 py-3.5 text-zinc-500 dark:text-zinc-400 print:text-black">
                                            {{ $row->teacher->department->code ?? 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-medium text-zinc-700 dark:text-zinc-300 print:text-black">
                                            {{ $row->submissions_count > 0 && $row->upward_student_count > 0 ? number_format($row->upward_student_average, 2) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-medium text-zinc-700 dark:text-zinc-300 print:text-black">
                                            {{ $row->submissions_count > 0 && $row->upward_employee_count > 0 ? number_format($row->upward_employee_average, 2) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-medium text-zinc-700 dark:text-zinc-300 print:text-black">
                                            {{ $row->submissions_count > 0 && $row->downward_count > 0 ? number_format($row->downward_average, 2) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-medium text-zinc-700 dark:text-zinc-300 print:text-black">
                                            {{ $row->submissions_count > 0 && $row->peer_count > 0 ? number_format($row->peer_average, 2) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-medium text-zinc-700 dark:text-zinc-300 print:text-black">
                                            {{ $row->submissions_count > 0 && $row->self_count > 0 ? number_format($row->self_average, 2) : 'N/A' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-center font-semibold text-zinc-850 dark:text-zinc-200 print:text-black">
                                            {{ $row->submissions_count }}
                                        </td>
                                        <td class="px-4 py-3.5 text-right font-black text-indigo-600 dark:text-indigo-400 print:text-black">
                                            {{ $row->submissions_count > 0 ? number_format($row->overall_average, 2) : '0.00' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
