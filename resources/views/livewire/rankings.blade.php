<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function placeholder()
    {
        return view('livewire.placeholders.rankings-skeleton');
    }

    public string $activeTab = 'faculty';

    public string $search = '';

    public string $selectedDepartmentId = '';

    public string $sortBy = 'highest';

    public function getActiveSemesterProperty()
    {
        return Semester::getActive();
    }

    public function getDepartmentsProperty()
    {
        return Department::getCachedList()->where('type', 'academic');
    }

    /**
     * All active faculty scored and assigned their true performance rank (1..N by avg_score DESC).
     */
    public function getBaseFacultyRankingsProperty()
    {
        $sem = $this->activeSemester;
        $query = Employee::where('role', 'faculty')
            ->where('status', 'active')
            ->with(['department', 'user']);

        $evalStatsMap = collect();
        if ($sem) {
            $evalStatsMap = DB::table('evaluations')
                ->where('semester_id', $sem->id)
                ->selectRaw('evaluatee_id, count(*) as total_count, avg(rating_average) as avg_rating')
                ->groupBy('evaluatee_id')
                ->get()
                ->keyBy('evaluatee_id');
        }

        $facultyList = $query->get()->map(function ($emp) use ($evalStatsMap) {
            $user = $emp->user;
            $stat = $user ? $evalStatsMap->get($user->id) : null;
            $evalCount = (int) ($stat?->total_count ?? 0);
            $avgScore = $evalCount > 0 ? (float) $stat->avg_rating : 0.0;

            if ($evalCount === 0) {
                $performanceLevel = 'No Evaluations';
                $badgeColor = 'zinc';
            } elseif ($avgScore >= 4.50) {
                $performanceLevel = 'Outstanding';
                $badgeColor = 'success';
            } elseif ($avgScore >= 3.50) {
                $performanceLevel = 'Very Satisfactory';
                $badgeColor = 'info';
            } elseif ($avgScore >= 2.50) {
                $performanceLevel = 'Satisfactory';
                $badgeColor = 'warning';
            } else {
                $performanceLevel = 'Needs Improvement';
                $badgeColor = 'danger';
            }

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => ucfirst($emp->role),
                'department' => $emp->department,
                'avg_score' => $avgScore,
                'evaluations_count' => $evalCount,
                'level' => $performanceLevel,
                'badge_color' => $badgeColor,
            ];
        });

        // Compute permanent true performance rank (ordered by avg_score DESC)
        $evaluated = $facultyList->filter(fn ($f) => $f->evaluations_count > 0)->sortByDesc('avg_score')->values();
        $rank = 1;
        foreach ($evaluated as $f) {
            $f->rank = $rank++;
        }

        $unevaluated = $facultyList->filter(fn ($f) => $f->evaluations_count === 0)->values();
        foreach ($unevaluated as $f) {
            $f->rank = null;
        }

        return $evaluated->concat($unevaluated);
    }

    /**
     * Top-performing faculty in the semester (independent of table filters).
     */
    public function getTopFacultyProperty()
    {
        return $this->baseFacultyRankings->first(fn ($f) => $f->evaluations_count > 0);
    }

    /**
     * Faculty list filtered by department, search, and user sort choice.
     */
    public function getFacultyRankingsProperty()
    {
        $facultyList = $this->baseFacultyRankings;

        if ($this->selectedDepartmentId) {
            $facultyList = $facultyList->filter(fn ($f) => $f->department?->id == $this->selectedDepartmentId);
        }

        // Filter search
        if ($this->search) {
            $searchLower = strtolower($this->search);
            $facultyList = $facultyList->filter(function ($f) use ($searchLower) {
                return str_contains(strtolower($f->name), $searchLower) ||
                    str_contains(strtolower($f->employee_number), $searchLower);
            });
        }

        // Sort
        if ($this->sortBy === 'highest') {
            $facultyList = $facultyList->sort(function ($a, $b) {
                if ($a->evaluations_count === 0 && $b->evaluations_count === 0) {
                    return strcmp($a->name, $b->name);
                }
                if ($a->evaluations_count === 0) {
                    return 1;
                }
                if ($b->evaluations_count === 0) {
                    return -1;
                }

                return $b->avg_score <=> $a->avg_score;
            });
        } elseif ($this->sortBy === 'lowest') {
            $facultyList = $facultyList->sort(function ($a, $b) {
                if ($a->evaluations_count === 0 && $b->evaluations_count === 0) {
                    return strcmp($a->name, $b->name);
                }
                if ($a->evaluations_count === 0) {
                    return 1;
                }
                if ($b->evaluations_count === 0) {
                    return -1;
                }

                return $a->avg_score <=> $b->avg_score;
            });
        } elseif ($this->sortBy === 'most_evals') {
            $facultyList = $facultyList->sort(function ($a, $b) {
                if ($b->evaluations_count !== $a->evaluations_count) {
                    return $b->evaluations_count <=> $a->evaluations_count;
                }

                return $b->avg_score <=> $a->avg_score;
            });
        }

        return $facultyList->values();
    }

    public function getDepartmentRankingsProperty()
    {
        $departments = Department::where('type', 'academic')->with(['dean', 'programHead', 'employees'])->get();
        $allFaculty = $this->baseFacultyRankings;

        $deptList = $departments->map(function ($dept) use ($allFaculty) {
            $deptFaculty = $allFaculty->filter(fn ($f) => $f->department?->id === $dept->id && $f->evaluations_count > 0);
            $count = $deptFaculty->count();
            $avgScore = $count > 0 ? round($deptFaculty->avg('avg_score'), 2) : 0.0;

            if ($count === 0) {
                $performanceLevel = 'No Evaluations';
                $badgeColor = 'zinc';
            } elseif ($avgScore >= 4.50) {
                $performanceLevel = 'Outstanding';
                $badgeColor = 'success';
            } elseif ($avgScore >= 3.50) {
                $performanceLevel = 'Very Satisfactory';
                $badgeColor = 'info';
            } elseif ($avgScore >= 2.50) {
                $performanceLevel = 'Satisfactory';
                $badgeColor = 'warning';
            } else {
                $performanceLevel = 'Needs Improvement';
                $badgeColor = 'danger';
            }

            return (object) [
                'id' => $dept->id,
                'code' => $dept->code,
                'name' => $dept->name,
                'dean' => $dept->dean,
                'faculty_count' => $dept->employees->count(),
                'evaluated_faculty_count' => $count,
                'avg_score' => $avgScore,
                'level' => $performanceLevel,
                'badge_color' => $badgeColor,
            ];
        })->sort(function ($a, $b) {
            if ($a->evaluated_faculty_count === 0 && $b->evaluated_faculty_count === 0) {
                return strcmp($a->name, $b->name);
            }
            if ($a->evaluated_faculty_count === 0) {
                return 1;
            }
            if ($b->evaluated_faculty_count === 0) {
                return -1;
            }

            return $b->avg_score <=> $a->avg_score;
        })->values();

        $rank = 1;

        return $deptList->map(function ($d) use (&$rank) {
            $d->rank = $d->evaluated_faculty_count > 0 ? $rank++ : null;

            return $d;
        });
    }

    /**
     * Top-performing academic department in the semester.
     */
    public function getTopDepartmentProperty()
    {
        return $this->departmentRankings->first(fn ($d) => $d->evaluated_faculty_count > 0);
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1" class="text-left">Faculty & Department Rankings</flux:heading>
        </div>

        @if($this->activeSemester)
            <div class="flex items-center gap-2 bg-zinc-50 dark:bg-zinc-800/40 px-3.5 py-2 rounded-xl border border-zinc-200 dark:border-zinc-800">
                <flux:icon icon="academic-cap" class="size-4 text-indigo-500" />
                <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">Active Period:</span>
                <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100">
                    A.Y. {{ $this->activeSemester->academicYear->name }} — {{ $this->activeSemester->name }}
                </span>
            </div>
        @endif
    </div>

    <!-- Top 4 Summary Stat Cards (with 5px dark red #9b0000 left border & odometer) -->
    @php
        $faculty = $this->facultyRankings;
        $allFaculty = $this->baseFacultyRankings;
        $evaluatedFaculty = $allFaculty->filter(fn($f) => $f->evaluations_count > 0);
        $topFaculty = $this->topFaculty;
        $topDept = $this->topDepartment;

        $totalFaculty = $allFaculty->count();
        $totalEvaluatedFaculty = $evaluatedFaculty->count();
        $instAverage = $totalEvaluatedFaculty > 0 ? round($evaluatedFaculty->avg('avg_score'), 2) : 0.0;

        $instLevel = 'No Evaluations Yet';
        if ($instAverage >= 4.50) {
            $instLevel = 'Outstanding Overall';
        } elseif ($instAverage >= 3.50) {
            $instLevel = 'Very Satisfactory Overall';
        } elseif ($instAverage >= 2.50) {
            $instLevel = 'Satisfactory Overall';
        } elseif ($instAverage > 0) {
            $instLevel = 'Needs Improvement';
        }
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- Card 1: Top Performing Faculty -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Top Performing Faculty</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-bold text-zinc-900 dark:text-zinc-100 font-mono">
                    @if($topFaculty)
                        {{ number_format($topFaculty->avg_score, 2) }} <span class="text-xs font-normal text-zinc-400">/ 5.00</span>
                    @else
                        <span class="text-zinc-400 text-lg font-sans">N/A</span>
                    @endif
                </span>
                <flux:icon icon="trophy" class="size-6 text-amber-500" />
            </div>
            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate">
                @if($topFaculty)
                    🥇 {{ $topFaculty->name }}
                @else
                    <span class="text-zinc-400 font-normal">No evaluations submitted yet</span>
                @endif
            </span>
        </div>

        <!-- Card 2: Top Department -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Highest Rated Department</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl font-bold text-[#035e44] dark:text-[#03dd9f] font-mono">
                    @if($topDept)
                        {{ number_format($topDept->avg_score, 2) }} <span class="text-xs font-normal text-zinc-400">/ 5.00</span>
                    @else
                        <span class="text-zinc-400 text-lg font-sans">N/A</span>
                    @endif
                </span>
                <flux:icon icon="academic-cap" class="size-6 text-emerald-500" />
            </div>
            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200 truncate">
                @if($topDept)
                    🏛️ {{ $topDept->code }} — {{ $topDept->name }}
                @else
                    <span class="text-zinc-400 font-normal">No evaluations submitted yet</span>
                @endif
            </span>
        </div>

        <!-- Card 3: Total Faculty Monitored -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Faculty Monitored</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                    <x-odometer :value="$totalFaculty" />
                </span>
                <flux:icon icon="user-group" class="size-6 text-indigo-500" />
            </div>
            <span class="text-xs font-medium text-zinc-400">
                @if($totalEvaluatedFaculty > 0)
                    {{ $totalEvaluatedFaculty }} of {{ $totalFaculty }} evaluated
                @else
                    Active teaching staff
                @endif
            </span>
        </div>

        <!-- Card 4: Institutional Mean Rating -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Institutional Mean Score</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold text-[#9b0000] dark:text-[#f89696] font-mono">
                    @if($instAverage > 0)
                        {{ number_format($instAverage, 2) }}
                    @else
                        <span class="text-zinc-400 text-lg font-sans">N/A</span>
                    @endif
                </span>
                <flux:icon icon="chart-bar" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs font-semibold {{ $instAverage > 0 ? 'text-[#035e44] dark:text-[#03dd9f]' : 'text-zinc-400 font-normal' }}">
                {{ $instLevel }}
            </span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800 flex gap-2">
        <button 
            type="button"
            wire:click="$set('activeTab', 'faculty')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'faculty' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="trophy" class="size-4" />
            Faculty Leaderboard
        </button>

        <button 
            type="button"
            wire:click="$set('activeTab', 'department')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'department' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="academic-cap" class="size-4" />
            Department Leaderboard
        </button>
    </div>

    <!-- TAB 1: Faculty Leaderboard -->
    @if($activeTab === 'faculty')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter & Search Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search faculty name or ID..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments" class="w-full sm:w-52">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="sortBy" class="w-full sm:w-48">
                        <flux:select.option value="highest">Highest Rating First</flux:select.option>
                        <flux:select.option value="lowest">Lowest Rating First</flux:select.option>
                        <flux:select.option value="most_evals">Most Evaluations</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @if($faculty->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="trophy" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No faculty found matching search filters.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm min-w-[700px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5 text-center w-16">Rank</th>
                                <th class="px-6 py-3.5">Faculty Member</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Evaluations</th>
                                <th class="px-6 py-3.5">Rating Score</th>
                                <th class="px-6 py-3.5 text-right">Performance Level</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($faculty as $f)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <!-- Rank Badge -->
                                    <td class="px-6 py-4 text-center">
                                        @if($f->rank === 1)
                                            <span class="inline-flex items-center justify-center size-8 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 font-bold text-base shadow-2xs">🥇</span>
                                        @elseif($f->rank === 2)
                                            <span class="inline-flex items-center justify-center size-8 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-600 font-bold text-base shadow-2xs">🥈</span>
                                        @elseif($f->rank === 3)
                                            <span class="inline-flex items-center justify-center size-8 rounded-full bg-amber-900/20 text-amber-700 font-bold text-base shadow-2xs">🥉</span>
                                        @elseif($f->rank)
                                            <span class="font-bold text-zinc-500 text-sm">#{{ $f->rank }}</span>
                                        @else
                                            <span class="text-zinc-400 text-sm font-semibold">—</span>
                                        @endif
                                    </td>

                                    <!-- Name & ID -->
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $f->name }}</div>
                                        <div class="text-xs text-zinc-400 font-mono">{{ $f->employee_number }}</div>
                                    </td>

                                    <!-- Department -->
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2.5 py-1 rounded-md">
                                            {{ $f->department?->code ?: 'General' }}
                                        </span>
                                    </td>

                                    <!-- Role -->
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-600 dark:text-zinc-400">
                                        {{ $f->role }}
                                    </td>

                                    <!-- Submissions Count -->
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $f->evaluations_count }} evals
                                    </td>

                                    <!-- Score -->
                                    <td class="px-6 py-4">
                                        @if($f->evaluations_count > 0)
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-base text-zinc-900 dark:text-zinc-100">
                                                    {{ number_format($f->avg_score, 2) }}
                                                </span>
                                                <span class="text-xs text-zinc-400">/ 5.00</span>
                                            </div>
                                        @else
                                            <span class="text-xs font-medium text-zinc-400 italic">No score</span>
                                        @endif
                                    </td>

                                    <!-- Descriptor Badge -->
                                    <td class="px-6 py-4 text-right">
                                        <flux:badge variant="{{ $f->badge_color }}" size="sm" class="font-bold">
                                            {{ $f->level }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: Department Leaderboard -->
    @if($activeTab === 'department')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm min-w-[700px]">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-3.5 text-center w-16">Rank</th>
                            <th class="px-6 py-3.5">Department Name</th>
                            <th class="px-6 py-3.5">Assigned Dean</th>
                            <th class="px-6 py-3.5">Faculty Members</th>
                            <th class="px-6 py-3.5">Department Mean Rating</th>
                            <th class="px-6 py-3.5 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @forelse($this->departmentRankings as $d)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                <!-- Rank -->
                                <td class="px-6 py-4 text-center">
                                    @if($d->rank === 1)
                                        <span class="inline-flex items-center justify-center size-8 rounded-full bg-amber-100 dark:bg-amber-950/60 text-amber-600 font-bold text-base">🥇</span>
                                    @elseif($d->rank === 2)
                                        <span class="inline-flex items-center justify-center size-8 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-600 font-bold text-base">🥈</span>
                                    @elseif($d->rank === 3)
                                        <span class="inline-flex items-center justify-center size-8 rounded-full bg-amber-900/20 text-amber-700 font-bold text-base">🥉</span>
                                    @elseif($d->rank)
                                        <span class="font-bold text-zinc-500 text-sm">#{{ $d->rank }}</span>
                                    @else
                                        <span class="text-zinc-400 text-sm font-semibold">—</span>
                                    @endif
                                </td>

                                <!-- Code & Name -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs uppercase bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded">{{ $d->code }}</span>
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ $d->name }}</span>
                                    </div>
                                </td>

                                <!-- Dean -->
                                <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $d->dean ? $d->dean->full_name : 'Unassigned' }}
                                </td>

                                <!-- Faculty Count -->
                                <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ $d->faculty_count }} Faculty
                                </td>

                                <!-- Department Mean Score -->
                                <td class="px-6 py-4">
                                    @if($d->evaluated_faculty_count > 0)
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono font-bold text-base text-emerald-600 dark:text-emerald-400">
                                                {{ number_format($d->avg_score, 2) }}
                                            </span>
                                            <span class="text-xs text-zinc-400">/ 5.00</span>
                                        </div>
                                    @else
                                        <span class="text-xs font-medium text-zinc-400 italic">No evaluations</span>
                                    @endif
                                </td>

                                <!-- Performance Level -->
                                <td class="px-6 py-4 text-right">
                                    <flux:badge variant="{{ $d->badge_color }}" size="sm" class="font-bold">
                                        {{ $d->level }}
                                    </flux:badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-zinc-400">
                                    No departments created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

