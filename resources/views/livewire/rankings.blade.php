<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Semester;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.rankings-skeleton');
    }

    public string $activeTab = 'faculty';

    public string $search = '';

    public string $selectedDepartmentId = '';

    public string $sortBy = 'highest';

    public int $perPage = 10;

    public bool $showCertificateModal = false;

    public ?int $certificateFacultyId = null;

    public function previewCertificate(int $facultyId): void
    {
        $this->certificateFacultyId = $facultyId;
        $this->showCertificateModal = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function getCertificateDataProperty()
    {
        if (! $this->certificateFacultyId) {
            return null;
        }

        $target = $this->baseFacultyRankings->firstWhere('id', $this->certificateFacultyId);
        if (! $target) {
            return null;
        }

        $sem = $this->activeSemester;

        return (object) [
            'faculty' => $target,
            'semester' => $sem,
            'academic_year' => $sem?->academicYear?->name ?? date('Y').'-'.(date('Y') + 1),
            'semester_name' => $sem?->name ?? 'Academic Semester',
            'date_awarded' => now()->format('F d, Y'),
        ];
    }

    private ?Semester $cachedActiveSemester = null;
    private bool $activeSemesterLoaded = false;

    public function getActiveSemesterProperty()
    {
        if (! $this->activeSemesterLoaded) {
            $this->cachedActiveSemester = Semester::getActive();
            $this->activeSemesterLoaded = true;
        }

        return $this->cachedActiveSemester;
    }

    public function getDepartmentsProperty()
    {
        return Department::getCachedList()->where('type', 'academic');
    }

    private ?\Illuminate\Support\Collection $cachedBaseFacultyRankings = null;

    /**
     * All active faculty scored and assigned their true performance rank (1..N by avg_score DESC).
     */
    public function getBaseFacultyRankingsProperty()
    {
        if ($this->cachedBaseFacultyRankings !== null) {
            return $this->cachedBaseFacultyRankings;
        }

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

        return $this->cachedBaseFacultyRankings = $evaluated->concat($unevaluated);
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

        $sorted = $facultyList->values();
        $page = Paginator::resolveCurrentPage() ?: 1;
        $total = $sorted->count();
        $items = $sorted->forPage($page, $this->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $this->perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
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
    @endphp    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- Card 1: Top Performing Faculty -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-4">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Top Performing Faculty</span>
            <div class="space-y-1.5">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                        @if($topFaculty)
                            {{ number_format($topFaculty->avg_score, 2) }}
                        @else
                            <span class="text-zinc-400 text-xl font-sans font-medium">N/A</span>
                        @endif
                    </span>
                    @if($topFaculty)
                        <span class="text-xs text-zinc-400 font-mono">/ 5.00</span>
                    @endif
                </div>
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">
                    @if($topFaculty)
                        {{ $topFaculty->name }}
                    @else
                        <span class="text-zinc-400 font-normal text-xs">No evaluations submitted yet</span>
                    @endif
                </div>
                @if($topFaculty)
                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-zinc-100 dark:border-zinc-800/80">
                        <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                            <span class="font-bold text-[10px] uppercase bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded text-zinc-700 dark:text-zinc-300">
                                {{ $topFaculty->department?->code ?: 'GEN' }}
                            </span>
                            <span>•</span>
                            <span>{{ $topFaculty->evaluations_count }} {{ $topFaculty->evaluations_count === 1 ? 'review' : 'reviews' }}</span>
                        </div>
                        <button 
                            type="button" 
                            wire:click="previewCertificate({{ $topFaculty->id }})" 
                            class="text-[11px] font-bold text-[#9b0000] dark:text-[#e07a7a] hover:underline cursor-pointer inline-flex items-center gap-1"
                        >
                            Certificate →
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Card 2: Highest Rated Department -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-4">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Highest Rated Dept</span>
            <div class="space-y-1.5">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                        @if($topDept)
                            {{ number_format($topDept->avg_score, 2) }}
                        @else
                            <span class="text-zinc-400 text-xl font-sans font-medium">N/A</span>
                        @endif
                    </span>
                    @if($topDept)
                        <span class="text-xs text-zinc-400 font-mono">/ 5.00</span>
                    @endif
                </div>
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 truncate">
                    @if($topDept)
                        <span class="font-bold uppercase bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded text-xs text-zinc-800 dark:text-zinc-200 mr-1.5">
                            {{ $topDept->code }}
                        </span>
                    @else
                        <span class="text-zinc-400 font-normal text-xs">No evaluations submitted yet</span>
                    @endif
                </div>
                @if($topDept)
                    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $topDept->evaluated_faculty_count }} of {{ $topDept->faculty_count }} evaluated</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Card 3: Evaluation Coverage -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-4">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Evaluation Coverage</span>
            <div class="space-y-1.5">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                        <x-odometer :value="$totalEvaluatedFaculty" />
                    </span>
                    <span class="text-xs text-zinc-400 font-medium">/ {{ $totalFaculty }} faculty</span>
                </div>
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                    @if($totalFaculty > 0)
                        {{ round(($totalEvaluatedFaculty / $totalFaculty) * 100, 1) }}% active participation
                    @else
                        <span class="text-zinc-400 font-normal text-xs">No faculty active</span>
                    @endif
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $totalFaculty - $totalEvaluatedFaculty }} pending evaluated
                </div>
            </div>
        </div>

        <!-- Card 4: Institutional Mean Rating -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-4">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Institutional Mean</span>
            <div class="space-y-1.5">
                <div class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                        @if($instAverage > 0)
                            {{ number_format($instAverage, 2) }}
                        @else
                            <span class="text-zinc-400 text-xl font-sans font-medium">N/A</span>
                        @endif
                    </span>
                    @if($instAverage > 0)
                        <span class="text-xs text-zinc-400 font-mono">/ 5.00</span>
                    @endif
                </div>
                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
                    {{ $instLevel }}
                </div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Across {{ $totalEvaluatedFaculty }} evaluated faculty
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="border-b border-zinc-200 dark:border-zinc-800 flex gap-2">
        <button 
            type="button"
            wire:click="$set('activeTab', 'faculty')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'faculty' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#e07a7a] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="academic-cap" class="size-4" />
            Faculty Leaderboard
        </button>
        <button 
            type="button"
            wire:click="$set('activeTab', 'department')"
            class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors flex items-center gap-2 {{ $activeTab === 'department' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#e07a7a] dark:text-[#e07a7a]' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-300' }}"
        >
            <flux:icon icon="building-office-2" class="size-4" />
            Department Leaderboard
        </button>
    </div>

    <!-- TAB 1: Faculty Leaderboard -->
    @if($activeTab === 'faculty')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Search & Filters -->
            <div class="flex flex-col sm:flex-row gap-4 justify-between items-stretch sm:items-center">
                <div class="w-full sm:w-72">
                    <flux:input 
                        wire:model.live.debounce.300ms="search" 
                        icon="magnifying-glass" 
                        placeholder="Search faculty name or ID..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments" class="w-full sm:w-44">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="sortBy" class="w-full sm:w-48">
                        <flux:select.option value="highest">Highest Rating First</flux:select.option>
                        <flux:select.option value="lowest">Lowest Rating First</flux:select.option>
                        <flux:select.option value="most_evals">Most Reviews</flux:select.option>
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
                                <th class="px-6 py-3.5">Reviews Received</th>
                                <th class="px-6 py-3.5">Rating Score</th>
                                <th class="px-6 py-3.5 text-right">Performance & Action</th>
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
                                        @if($f->evaluations_count > 0)
                                            <span>{{ $f->evaluations_count }} {{ $f->evaluations_count === 1 ? 'review' : 'reviews' }}</span>
                                        @else
                                            <span class="text-zinc-400 font-normal text-xs italic">0 reviews</span>
                                        @endif
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

                                <!-- Actions -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:badge variant="{{ $f->badge_color }}" size="sm" class="font-bold">
                                            {{ $f->level }}
                                        </flux:badge>
                                        @if($f->evaluations_count > 0 && ($f->rank <= 3 || $f->avg_score >= 4.50))
                                            <flux:button size="xs" variant="subtle" icon="academic-cap" wire:click="previewCertificate({{ $f->id }})" title="Generate Certificate of Teaching Excellence">
                                                Certificate
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($faculty->hasPages())
                <div class="pt-2">
                    {{ $faculty->links() }}
                </div>
            @endif
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
                                    {{ $d->faculty_count }} {{ $d->faculty_count === 1 ? 'member' : 'members' }}
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

    <!-- Certificate of Teaching Excellence Modal -->
    <flux:modal wire:model="showCertificateModal" class="max-w-4xl p-0 overflow-hidden bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
        @if($this->certificateData)
            @php $cert = $this->certificateData; @endphp
            <div class="flex flex-col">
                <!-- Modal Top Action Bar (hidden on print) -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/60 print:hidden">
                    <div class="flex items-center gap-2">
                        <flux:icon icon="academic-cap" class="size-5 text-[#9b0000] dark:text-[#e07a7a]" />
                        <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Certificate of Teaching Excellence Preview</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="primary" icon="printer" onclick="window.print()" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white font-bold text-xs">
                            Print / Save as PDF
                        </flux:button>
                        <flux:modal.close>
                            <flux:button variant="ghost" size="sm">Close</flux:button>
                        </flux:modal.close>
                    </div>
                </div>

                <!-- Printable Certificate Canvas (Standard A4 / Letter Landscape) -->
                <div id="certificate-print-canvas" class="p-8 sm:p-12 md:p-14 bg-[#fcfbfa] text-zinc-900 flex flex-col items-center justify-between text-center relative border-[12px] border-double border-[#9b0000] m-4 md:m-6 shadow-sm min-h-[580px] print:m-0 print:border-[10px] print:border-[#9b0000] print:shadow-none print:bg-white">
                    <!-- Corner Flourish Accents -->
                    <div class="absolute top-3 left-3 text-[#9b0000] text-xs font-serif opacity-70">❖</div>
                    <div class="absolute top-3 right-3 text-[#9b0000] text-xs font-serif opacity-70">❖</div>
                    <div class="absolute bottom-3 left-3 text-[#9b0000] text-xs font-serif opacity-70">❖</div>
                    <div class="absolute bottom-3 right-3 text-[#9b0000] text-xs font-serif opacity-70">❖</div>

                    <!-- Institutional Header -->
                    <div class="flex flex-col items-center gap-2">
                        <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Institution Logo" class="h-14 md:h-16 w-auto object-contain" />
                        <div class="flex flex-col">
                            <h2 class="font-serif font-black tracking-widest uppercase text-base md:text-lg text-zinc-900">
                                Global Reciprocal Colleges
                            </h2>
                            <p class="text-[10px] md:text-xs text-zinc-500 uppercase tracking-widest font-sans">
                                Office of Academic Affairs & Faculty Development
                            </p>
                        </div>
                    </div>

                    <!-- Certificate Title -->
                    <div class="my-4">
                        <p class="text-xs uppercase tracking-[0.3em] text-[#9b0000] font-bold">Certificate of</p>
                        <h1 class="text-2xl md:text-4xl font-serif font-black text-zinc-900 uppercase tracking-wide mt-1">
                            Teaching Excellence
                        </h1>
                        <p class="text-xs text-zinc-500 italic mt-1 font-serif">
                            This formal commendation is proudly presented to
                        </p>
                    </div>

                    <!-- Recipient Name -->
                    <div class="my-2 border-b-2 border-zinc-900 pb-1.5 px-8 max-w-xl mx-auto">
                        <span class="text-2xl md:text-3xl font-serif font-black text-zinc-900 tracking-tight">
                            {{ $cert->faculty->name }}
                        </span>
                    </div>
                    <p class="text-xs text-zinc-600 font-medium">
                        {{ $cert->faculty->department?->name ?? 'College Faculty' }} ({{ $cert->faculty->department?->code ?? 'GEN' }})
                    </p>

                    <!-- Citation Text -->
                    <div class="max-w-2xl text-xs md:text-sm text-zinc-700 leading-relaxed my-4 font-serif">
                        In recognition of outstanding instructional performance, exemplary pedagogical dedication, and meritorious evaluation ratings achieved during 
                        <span class="font-bold text-zinc-900 font-sans">A.Y. {{ $cert->academic_year }} — {{ $cert->semester_name }}</span>.
                        <div class="mt-2 inline-flex items-center gap-2 bg-zinc-100 px-3 py-1 rounded-md text-xs font-mono font-bold text-zinc-800 border border-zinc-200 print:border print:border-zinc-300">
                            <span>Rating: {{ number_format($cert->faculty->avg_score, 2) }} / 5.00</span>
                            @if($cert->faculty->rank)
                                <span>•</span>
                                <span>Institutional Rank #{{ $cert->faculty->rank }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Signatures Section -->
                    <div class="w-full grid grid-cols-2 gap-8 md:gap-16 pt-8 mt-4 border-t border-zinc-200">
                        <div class="flex flex-col items-center">
                            <div class="w-48 border-b border-zinc-800 mb-1.5"></div>
                            <span class="font-bold text-xs uppercase text-zinc-900 tracking-wider">
                                {{ $cert->faculty->department?->dean?->full_name ?? 'College Dean' }}
                            </span>
                            <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Dean of Academic College</span>
                        </div>

                        <div class="flex flex-col items-center">
                            <div class="w-48 border-b border-zinc-800 mb-1.5"></div>
                            <span class="font-bold text-xs uppercase text-zinc-900 tracking-wider">Office of Academic Affairs</span>
                            <span class="text-[10px] text-zinc-500 uppercase tracking-wider">Vice President for Academic Affairs</span>
                        </div>
                    </div>

                    <!-- Date & Verification Code Footer -->
                    <div class="w-full flex items-center justify-between text-[9px] text-zinc-400 mt-6 print:text-zinc-500">
                        <span>Awarded on {{ $cert->date_awarded }}</span>
                        <span class="font-mono">Doc Ref: GRC-CTE-{{ $cert->faculty->id }}-{{ date('Ymd') }}</span>
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

