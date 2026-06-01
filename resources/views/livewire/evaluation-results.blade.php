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
    public ?int $selectedDepartmentId = null;
    public ?int $selectedSemesterId = null;
    public string $selectedEvaluationType = '';
    public string $search = '';

    // Modal state
    public ?int $viewingTeacherId = null;
    public bool $showModal = false;

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

    public function getDepartmentsProperty()
    {
        return Department::orderBy('name')->get();
    }

    public function getTeachersProperty()
    {
        $semId = $this->selectedSemesterId;
        if (!$semId) return collect();

        $query = Employee::whereIn('role', ['faculty', 'program head', 'dean', 'staff'])
            ->with(['user', 'department']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                  ->orWhere('last_name', 'like', "%{$this->search}%")
                  ->orWhere('employee_number', 'like', "%{$this->search}%");
            });
        }

        return $query->get()->map(function ($teacher) use ($semId) {
            $userId = $teacher->user?->id;
            
            if (!$userId) {
                $avg = 0.00;
                $count = 0;
            } else {
                $evals = Evaluation::where('evaluatee_id', $userId)
                    ->where('semester_id', $semId);
                if ($this->selectedEvaluationType) {
                    $evals->where('evaluation_type', $this->selectedEvaluationType);
                }
                $count = $evals->count();
                $avg = $count > 0 ? round($evals->avg('rating_average'), 2) : 0.00;
            }

            return (object) [
                'id' => $teacher->id,
                'user_id' => $userId,
                'full_name' => $teacher->full_name,
                'employee_number' => $teacher->employee_number,
                'role' => ucfirst($teacher->role),
                'department_code' => $teacher->department->code ?? 'N/A',
                'average_score' => $avg,
                'submissions_count' => $count,
            ];
        })->sortByDesc('average_score');
    }

    // Details for viewing in Modal
    public function getSelectedTeacherDetailsProperty()
    {
        if (!$this->viewingTeacherId || !$this->selectedSemesterId) return null;

        $teacher = Employee::with('user')->findOrFail($this->viewingTeacherId);
        $userId = $teacher->user?->id;
        if (!$userId) return null;

        $evalsQuery = Evaluation::where('evaluatee_id', $userId)
            ->where('semester_id', $this->selectedSemesterId);
        if ($this->selectedEvaluationType) {
            $evalsQuery->where('evaluation_type', $this->selectedEvaluationType);
        }

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
            $typeAverages[$type] = (object) ['count' => $tCount, 'average' => $tAvg];
        }

        // Breakdown by Criterion
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
            'full_name' => $teacher->full_name,
            'role' => ucfirst($teacher->role),
            'employee_number' => $teacher->employee_number,
            'overall_average' => $overallAverage,
            'total_submissions' => $totalSubmissions,
            'type_averages' => $typeAverages,
            'criteria_breakdown' => $criteria,
            'comments' => $comments,
        ];
    }

    public function viewDetails($teacherId)
    {
        $this->viewingTeacherId = $teacherId;
        $this->showModal = true;
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Evaluation Results</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Review average rating scores and detailed breakdown analytics.</p>
        </div>

        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto shrink-0">
            <div class="w-full md:w-48">
                <flux:select wire:model.live="selectedSemesterId" placeholder="Select Semester">
                    @foreach($this->semesters as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full md:w-48">
                <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($this->departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="w-full md:w-48">
                <flux:select wire:model.live="selectedEvaluationType" placeholder="All Types">
                    <flux:select.option value="">All Types</flux:select.option>
                    <flux:select.option value="student">Student Evaluation</flux:select.option>
                    <flux:select.option value="peer">Peer Evaluation</flux:select.option>
                    <flux:select.option value="self">Self Evaluation</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Search input -->
    <div class="flex-1 w-full bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name or employee ID..." />
    </div>

    <!-- Results list -->
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-4">Evaluation Results Summary</flux:heading>

        @if($this->teachers->isEmpty())
            <div class="text-center py-8 text-zinc-500">
                <p class="text-sm">No evaluation results found for this search/filters.</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 font-semibold border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-4">Professor</th>
                            <th class="px-6 py-4">ID / Role</th>
                            <th class="px-6 py-4">Department</th>
                            <th class="px-6 py-4">Total Submissions</th>
                            <th class="px-6 py-4">Rating Average</th>
                            <th class="px-6 py-4 text-right">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @foreach($this->teachers as $t)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                <td class="px-6 py-4 font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ $t->full_name }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $t->employee_number }}</div>
                                    <div class="text-xs text-zinc-500">{{ $t->role }}</div>
                                </td>
                                <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300 font-medium">
                                    {{ $t->department_code }}
                                </td>
                                <td class="px-6 py-4 text-zinc-700 dark:text-zinc-300 font-semibold">
                                    {{ $t->submissions_count }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($t->submissions_count > 0)
                                        <div class="flex items-center gap-2">
                                            <span class="text-base font-extrabold text-indigo-600 dark:text-indigo-400">
                                                {{ number_format($t->average_score, 2) }}
                                            </span>
                                            <span class="text-xs text-zinc-400">/ 5.0</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-zinc-400">No evaluations yet</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="viewDetails({{ $t->id }})">
                                        View Breakdown
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>

    <!-- Detailed Modal -->
    @if($showModal && $this->selectedTeacherDetails)
        @php $details = $this->selectedTeacherDetails; @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 flex justify-center items-center p-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-y-auto flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-zinc-150 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40">
                    <div>
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-50">{{ $details->full_name }}</h2>
                        <p class="text-xs text-zinc-500 mt-0.5">ID: {{ $details->employee_number }} | Role: {{ $details->role }}</p>
                    </div>
                    <flux:button variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
                </div>

                <!-- Modal Body -->
                <div class="p-6 flex flex-col gap-6">
                    <!-- KPI summaries -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-indigo-50 dark:bg-indigo-950/20 border border-indigo-100 dark:border-indigo-950 p-4 rounded-xl text-center">
                            <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Overall Average</div>
                            <div class="text-2xl font-black text-indigo-700 dark:text-indigo-400 mt-1">
                                {{ number_format($details->overall_average, 2) }} <span class="text-xs font-normal">/ 5.0</span>
                            </div>
                        </div>

                        @foreach(['student' => 'Students', 'peer' => 'Peers / Sup', 'self' => 'Self'] as $type => $label)
                            <div class="bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-150 dark:border-zinc-800 p-4 rounded-xl text-center">
                                <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">{{ $label }} Rating</div>
                                <div class="text-xl font-bold text-zinc-800 dark:text-zinc-200 mt-1">
                                    @if(($details->type_averages[$type]->count ?? 0) > 0)
                                        {{ number_format($details->type_averages[$type]->average, 2) }}
                                        <span class="text-xs font-medium block text-zinc-400 mt-0.5">({{ $details->type_averages[$type]->count }} subs)</span>
                                    @else
                                        <span class="text-sm font-medium text-zinc-400">N/A</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Criteria Breakdown -->
                    <div>
                        <h3 class="font-bold text-zinc-800 dark:text-zinc-200 mb-3 text-base">Criteria Performance Breakdown</h3>
                        <div class="grid grid-cols-1 gap-3">
                            @foreach($details->criteria_breakdown as $c)
                                <div class="flex items-center justify-between p-3 bg-zinc-50 dark:bg-zinc-800/20 rounded-xl border border-zinc-100 dark:border-zinc-850">
                                    <div class="flex-1 pr-4">
                                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200">{{ $c->name }}</div>
                                        <div class="text-xs text-zinc-400 mt-0.5">Type: {{ $c->type }}</div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ ($c->average / 5) * 100 }}%"></div>
                                        </div>
                                        <span class="text-sm font-black text-zinc-850 dark:text-zinc-150">{{ number_format($c->average, 2) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Qualitative Comments -->
                    <div>
                        <h3 class="font-bold text-zinc-800 dark:text-zinc-200 mb-3 text-base">Comments & Suggestions</h3>
                        @if(empty($details->comments))
                            <p class="text-sm text-zinc-400 italic">No text comments submitted for this teacher.</p>
                        @else
                            <div class="flex flex-col gap-2 max-h-48 overflow-y-auto border border-zinc-100 dark:border-zinc-800 rounded-xl p-3 bg-zinc-50/50 dark:bg-zinc-850/20">
                                @foreach($details->comments as $comment)
                                    <div class="text-sm text-zinc-700 dark:text-zinc-300 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800 rounded-lg shadow-sm">
                                        "{{ $comment }}"
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-zinc-150 dark:border-zinc-800 flex justify-end">
                    <flux:button variant="primary" wire:click="$set('showModal', false)">Close Details</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
