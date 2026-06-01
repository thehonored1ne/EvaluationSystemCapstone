<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Semester;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationAnswer;

new #[Layout('components.layouts.app')] class extends Component {
    public ?int $selectedTeacherId = null;
    public ?int $selectedSemesterId = null;

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
        $overallAverage = $totalSubmissions > 0 ? round($evalsQuery->avg('rating_average'), 2) : 0.00;

        // Breakdown by type
        $types = ['student', 'peer', 'self'];
        $typeAverages = [];
        foreach ($types as $type) {
            $tQuery = clone $evalsQuery;
            $tCount = $tQuery->where('evaluation_type', $type)->count();
            $tQuery2 = clone $evalsQuery;
            $tAvg = $tCount > 0 ? round($tQuery2->where('evaluation_type', $type)->avg('rating_average'), 2) : 0.00;
            
            $maxPoints = match($type) {
                'student' => (float)$semester->student_max_points,
                'peer' => (float)$semester->peer_max_points,
                'self' => (float)$semester->self_max_points,
            };

            $typeAverages[$type] = (object) [
                'count' => $tCount, 
                'average' => $tAvg,
                'max_points' => $maxPoints,
            ];
        }

        // Breakdown by Criteria
        $evalIds = $evalsQuery->pluck('id')->toArray();
        $criteria = EvaluationCriterion::orderBy('evaluation_type')->orderBy('order')->get()->map(function ($criterion) use ($evalIds) {
            $answersAvg = EvaluationAnswer::whereIn('evaluation_id', $evalIds)
                ->whereHas('question', function ($q) use ($criterion) {
                    $q->where('criterion_id', $criterion->id);
                })
                ->avg('rating');

            return (object) [
                'name' => $criterion->name,
                'type' => ucfirst($criterion->evaluation_type),
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
}; ?>

<div class="flex flex-col gap-8 w-full max-w-5xl mx-auto px-4 py-6">
    <!-- Filters (Hidden on Print) -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm print:hidden">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Evaluation Reports</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Select a teacher and semester to generate and print a performance summary report.</p>
        </div>

        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto shrink-0">
            <div class="w-full md:w-64">
                <flux:select wire:model.live="selectedTeacherId" placeholder="Select Professor">
                    @foreach($this->teachers as $t)
                        <flux:select.option value="{{ $t->id }}">{{ $t->full_name }} ({{ $t->employee_number }})</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full md:w-48">
                <flux:select wire:model.live="selectedSemesterId" placeholder="Select Semester">
                    @foreach($this->semesters as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Print Report Body -->
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="border-2 border-indigo-600 dark:border-indigo-400 p-4 rounded-xl text-center bg-indigo-50/20">
                    <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">Overall Score</div>
                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ number_format($data->overall_average, 2) }} <span class="text-xs font-normal">/ 5.0</span>
                    </div>
                </div>

                @foreach(['student' => 'Students', 'peer' => 'Peers / Sup', 'self' => 'Self'] as $type => $label)
                    <div class="border border-zinc-200 dark:border-zinc-800 p-4 rounded-xl text-center">
                        <div class="text-xs font-bold uppercase tracking-wider text-zinc-500">{{ $label }} Rating</div>
                        <div class="text-xl font-bold text-zinc-850 dark:text-zinc-200 print:text-black mt-1">
                            @if($data->type_averages[$type]->count > 0)
                                {{ number_format($data->type_averages[$type]->average, 2) }}
                                <span class="text-xs font-medium text-zinc-400 block mt-0.5">({{ $data->type_averages[$type]->count }} reports)</span>
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
</div>
