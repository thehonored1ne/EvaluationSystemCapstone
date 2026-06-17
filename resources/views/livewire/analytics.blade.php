<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Lazy;
use App\Models\Semester;
use App\Models\Evaluation;
use App\Models\Department;
use App\Models\User;
use App\Models\EvaluationAnswer;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.analytics-skeleton');
    }
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

    public function getStatsProperty()
    {
        $semId = $this->selectedSemesterId;
        if (!$semId) {
            return [
                'total_evaluations' => 0,
                'overall_average' => 0.00,
                'total_students' => 0,
                'highest_dept' => 'N/A',
                'lowest_dept' => 'N/A',
                'ratings_dist' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'dept_scores' => [],
            ];
        }

        $evals = Evaluation::where('semester_id', $semId)->get();
        $totalEvals = $evals->count();
        $overallAvg = $totalEvals > 0 ? round($evals->avg('rating_average'), 2) : 0.00;
        
        $totalStudents = User::role('student')->count();

        // Rating distribution from answers
        $evalIds = $evals->pluck('id')->toArray();
        $ratingsDist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $answers = EvaluationAnswer::whereIn('evaluation_id', $evalIds)
            ->select('rating', \DB::raw('count(*) as total'))
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        foreach ($ratingsDist as $rating => $val) {
            $ratingsDist[$rating] = $answers[$rating] ?? 0;
        }

        // Department averages
        $depts = Department::all();
        $deptScores = [];
        foreach ($depts as $dept) {
            $deptEvals = Evaluation::where('semester_id', $semId)
                ->whereHas('evaluatee.employee', function ($q) use ($dept) {
                    $q->where('department_id', $dept->id);
                })
                ->get();
            $dCount = $deptEvals->count();
            $dAvg = $dCount > 0 ? round($deptEvals->avg('rating_average'), 2) : 0.00;
            $deptScores[] = (object) [
                'name' => $dept->name,
                'code' => $dept->code,
                'average' => $dAvg,
                'count' => $dCount,
            ];
        }

        $sortedDepts = collect($deptScores)->filter(fn($d) => $d->count > 0)->sortByDesc('average');
        $highestDept = $sortedDepts->first()?->code ?? 'N/A';
        $lowestDept = $sortedDepts->last()?->code ?? 'N/A';

        return [
            'total_evaluations' => $totalEvals,
            'overall_average' => $overallAvg,
            'total_students' => $totalStudents,
            'highest_dept' => $highestDept,
            'lowest_dept' => $lowestDept,
            'ratings_dist' => $ratingsDist,
            'dept_scores' => $deptScores,
        ];
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Evaluation Analytics</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Explore statistical distributions, averages, and comparisons across semesters.</p>
        </div>

        <div class="w-full md:w-64">
            <flux:select wire:model.live="selectedSemesterId" placeholder="Select Semester">
                @foreach($this->semesters as $sem)
                    <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- KPI Summaries -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        @php $data = $this->stats; @endphp
        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Total Evaluations</flux:heading>
            <span class="text-3xl font-black text-zinc-800 dark:text-zinc-50">{{ $data['total_evaluations'] }}</span>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Overall Average Score</flux:heading>
            <div class="flex items-center gap-2">
                <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ number_format($data['overall_average'], 2) }}</span>
                <span class="text-xs text-zinc-400">/ 5.0</span>
            </div>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Highest Department</flux:heading>
            <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $data['highest_dept'] }}</span>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Lowest Department</flux:heading>
            <span class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ $data['lowest_dept'] }}</span>
        </flux:card>
    </div>

    <!-- Visual Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- 1. Ratings Distribution -->
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Ratings Distribution (Star Frequencies)</flux:heading>
            
            <div class="flex flex-col gap-4">
                @php
                    $dist = $data['ratings_dist'];
                    $totalVotes = array_sum($dist);
                @endphp

                @for($star = 5; $star >= 1; $star--)
                    @php
                        $votes = $dist[$star] ?? 0;
                        $pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
                    @endphp
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-semibold text-zinc-500 w-12 text-right">{{ $star }} Star</span>
                        <div class="flex-1 bg-zinc-150 dark:bg-zinc-800 h-4 rounded overflow-hidden">
                            <div class="bg-indigo-600 h-4 rounded" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 w-16 text-right">
                            {{ $votes }} ({{ $pct }}%)
                        </span>
                    </div>
                @endfor
            </div>
        </flux:card>

        <!-- 2. Department Averages -->
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-6">Department Average Ratings Comparison</flux:heading>

            <div class="flex flex-col gap-6">
                @foreach($data['dept_scores'] as $dept)
                    <div class="flex flex-col gap-2">
                        <div class="flex justify-between items-center text-sm">
                            <span class="font-bold text-zinc-800 dark:text-zinc-200">
                                {{ $dept->name }} ({{ $dept->code }})
                            </span>
                            <span class="font-black text-indigo-600 dark:text-indigo-400">
                                {{ $dept->average > 0 ? number_format($dept->average, 2) : 'No submissions' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex-1 bg-zinc-150 dark:bg-zinc-800 h-3 rounded-full overflow-hidden">
                                @if($dept->average > 0)
                                    <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ ($dept->average / 5) * 100 }}%"></div>
                                @endif
                            </div>
                            <span class="text-xs text-zinc-400 w-20 text-right">
                                {{ $dept->count }} responses
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>
</div>
