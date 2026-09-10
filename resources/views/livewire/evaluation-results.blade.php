<?php

use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use App\Services\ThematicAnalysisService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.evaluation-results-skeleton');
    }

    public ?int $selectedDepartmentId = null;

    public ?int $selectedSemesterId = null;

    public string $selectedRole = '';

    public string $search = '';

    // Modal state
    public ?int $viewingUserId = null;

    public bool $showModal = false;

    public function mount()
    {
        $activeSem = Semester::where('is_active', true)->first();
        if ($activeSem) {
            $this->selectedSemesterId = $activeSem->id;
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartmentId()
    {
        $this->resetPage();
    }

    public function updatedSelectedSemesterId()
    {
        $this->resetPage();
    }

    public function updatedSelectedRole()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedDepartmentId', 'selectedRole']);
        $this->resetPage();
    }

    private ?\Illuminate\Support\Collection $cachedSemesters = null;

    public function getSemestersProperty()
    {
        if ($this->cachedSemesters !== null) {
            return $this->cachedSemesters;
        }

        return $this->cachedSemesters = Semester::with('academicYear')->orderBy('id', 'desc')->get();
    }

    public function getDepartmentsProperty()
    {
        return Department::getCachedList();
    }

    public function viewDetails($userId)
    {
        $this->viewingUserId = $userId;
        $this->showModal = true;
    }

    public function getRatingTier(float $rating): array
    {
        $label = match (true) {
            $rating >= 4.50 => 'Outstanding',
            $rating >= 3.50 => 'Very Satisfactory',
            $rating >= 2.50 => 'Satisfactory',
            $rating >= 1.50 => 'Fair',
            $rating > 0.00 => 'Poor',
            default => 'No Ratings'
        };

        $classes = match (true) {
            $rating >= 3.50 => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800',
            $rating >= 2.50 => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800',
            $rating > 0.00 => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700'
        };

        return ['label' => $label, 'classes' => $classes];
    }

    public function getSelectedUserDetailsProperty()
    {
        if (! $this->viewingUserId || ! $this->selectedSemesterId) {
            return null;
        }

        $user = User::with(['employee.department', 'student.program.department'])->find($this->viewingUserId);
        if (! $user) {
            return null;
        }

        $rawRole = $user->employee?->role ?? ($user->student ? 'student' : 'user');
        $semId = $this->selectedSemesterId;
        $semester = Semester::with('academicYear')->find($semId);

        // Received evaluations
        $evalsQuery = Evaluation::where('evaluatee_id', $user->id)->where('semester_id', $semId);
        $totalReceived = $evalsQuery->count();
        $overallAvg = $totalReceived > 0 ? round((float) $evalsQuery->avg('rating_average'), 2) : 0.00;
        $overallTier = $this->getRatingTier($overallAvg);

        // Submitted evaluations
        $submittedCount = Evaluation::where('evaluator_id', $user->id)->where('semester_id', $semId)->count();

        // Calculate expected reviews and response coverage
        $expectedReviews = 0;
        if ($user->employee) {
            $enrolledStudents = (int) DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->where('classes.teacher_id', $user->employee_id)
                ->where('classes.semester_id', $semId)
                ->count();

            $deptId = $user->employee->department_id;
            $deptFac = $deptId ? max(0, DB::table('employees')->where('department_id', $deptId)->where('role', 'faculty')->where('status', 'active')->count() - 1) : 0;

            if ($enrolledStudents > 0 || $user->employee->role === 'faculty') {
                $expectedReviews = $enrolledStudents + $deptFac + 2; // Students + Peers + 1 Self + 1 Program Head
            } else {
                $expectedReviews = max($totalReceived, 1);
            }
        } else {
            $expectedReviews = max($totalReceived, 1);
        }

        $responseRate = $expectedReviews > 0 ? min(100.0, round(($totalReceived / $expectedReviews) * 100, 1)) : 100.0;

        // Build role-specific evaluation sources
        $expectedSourceConfig = match ($rawRole) {
            'faculty' => [
                'upward_student' => ['label' => 'Student Evaluations', 'weight_key' => 'student', 'aliases' => ['student', 'upward_student']],
                'program_head' => ['label' => 'Program Head Evaluation', 'weight_key' => 'program_head', 'aliases' => ['program_head', 'ph_dh', 'downward']],
                'dean' => ['label' => 'Dean Evaluation', 'weight_key' => 'dean', 'aliases' => ['dean']],
                'peer' => ['label' => 'Peer Faculty Evaluations', 'weight_key' => 'peer', 'aliases' => ['peer']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
            'program head' => [
                'dean' => ['label' => 'Dean Evaluation', 'weight_key' => 'dean', 'aliases' => ['dean']],
                'upward_employee' => ['label' => 'Faculty Feedback (Subordinates)', 'weight_key' => 'superior', 'aliases' => ['upward_employee', 'superior']],
                'peer' => ['label' => 'Peer Program Head Evaluations', 'weight_key' => 'peer', 'aliases' => ['peer']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
            'department head' => [
                'upward_employee' => ['label' => 'Staff Feedback (Subordinates)', 'weight_key' => 'superior', 'aliases' => ['upward_employee', 'superior']],
                'peer' => ['label' => 'Peer Dept Head Evaluations', 'weight_key' => 'peer', 'aliases' => ['peer']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
            'dean' => [
                'upward_employee' => ['label' => 'Program Head Feedback', 'weight_key' => 'superior', 'aliases' => ['upward_employee', 'superior']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
            'staff' => [
                'department_head' => ['label' => 'Department Head Evaluation', 'weight_key' => 'department_head', 'aliases' => ['department_head', 'ph_dh', 'downward']],
                'peer' => ['label' => 'Peer Staff Evaluations', 'weight_key' => 'peer', 'aliases' => ['peer']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
            default => [
                'upward_student' => ['label' => 'Student Evaluations', 'weight_key' => 'student', 'aliases' => ['student', 'upward_student']],
                'peer' => ['label' => 'Peer Evaluations', 'weight_key' => 'peer', 'aliases' => ['peer']],
                'self' => ['label' => 'Self Evaluation', 'weight_key' => 'self', 'aliases' => ['self']],
            ],
        };

        $typeStats = DB::table('evaluations')
            ->where('evaluatee_id', $user->id)
            ->where('semester_id', $semId)
            ->selectRaw('evaluation_type, count(*) as total_count, sum(rating_average) as sum_rating')
            ->groupBy('evaluation_type')
            ->get()
            ->keyBy('evaluation_type');

        $typeAverages = [];
        $handledTypes = [];

        foreach ($expectedSourceConfig as $key => $cfg) {
            $totalCount = 0;
            $sumRating = 0.0;
            foreach ($cfg['aliases'] as $alias) {
                if (isset($typeStats[$alias])) {
                    $totalCount += (int) $typeStats[$alias]->total_count;
                    $sumRating += (float) $typeStats[$alias]->sum_rating;
                    $handledTypes[] = $alias;
                }
            }

            if ($totalCount > 0) {
                $avg = round($sumRating / $totalCount, 2);
                $typeAverages[$key] = (object) [
                    'label' => $cfg['label'],
                    'count' => $totalCount,
                    'average' => $avg,
                ];
            }
        }

        // Include any unexpected evaluation types that exist in the DB for this user
        foreach ($typeStats as $type => $stat) {
            if (! in_array($type, $handledTypes)) {
                $tCount = (int) $stat->total_count;
                if ($tCount > 0) {
                    $avg = round((float) ($stat->sum_rating / $tCount), 2);
                    $typeAverages[$type] = (object) [
                        'label' => ucwords(str_replace('_', ' ', $type)).' Evaluation',
                        'count' => $tCount,
                        'average' => $avg,
                    ];
                }
            }
        }

        // AI Pipeline: Sentiment & Thematic Drivers
        $thematic = ThematicAnalysisService::getEvaluateeThematicAnalysis($user->id, $semId, 4);

        $deptName = $user->employee?->department?->name ?? $user->student?->program?->department?->name ?? 'Unassigned';
        $identifier = $user->employee?->employee_number ?? $user->student?->student_number ?? $user->email;
        $rawRole = $user->employee?->role ?? ($user->student ? 'student' : 'user');
        $roleLabel = match ($rawRole) {
            'faculty' => 'Professor',
            'program head' => 'Program Head',
            'department head' => 'Department Head',
            'dean' => 'Dean',
            'staff' => 'Staff',
            'student' => 'Student',
            default => ucfirst($rawRole)
        };

        return (object) [
            'user' => $user,
            'full_name' => $user->employee?->formatted_name ?? $user->student?->formatted_name ?? $user->name,
            'role' => $roleLabel,
            'identifier' => $identifier,
            'department' => $deptName,
            'semester_name' => $semester ? ('A.Y. '.($semester->academicYear?->name ?? '').' • '.$semester->name) : 'Current Semester',
            'total_received' => $totalReceived,
            'expected_reviews' => $expectedReviews,
            'response_rate' => $responseRate,
            'submitted_count' => $submittedCount,
            'overall_average' => $overallAvg,
            'overall_tier' => $overallTier,
            'type_averages' => $typeAverages,
            'thematic' => $thematic,
        ];
    }

    public function with(): array
    {
        $semId = $this->selectedSemesterId;

        $query = User::query()
            ->leftJoin('employees', 'users.employee_id', '=', 'employees.id')
            ->leftJoin('students', 'users.student_id', '=', 'students.id')
            ->select('users.*')
            ->where(function ($q) {
                $q->whereNotNull('users.employee_id')
                    ->orWhereNotNull('users.student_id');
            })
            ->with(['employee.department', 'student.program.department']);

        if ($this->selectedRole) {
            if ($this->selectedRole === 'student') {
                $query->whereNotNull('users.student_id');
            } elseif ($this->selectedRole === 'professor' || $this->selectedRole === 'faculty') {
                $query->where('employees.role', 'faculty');
            } else {
                $role = $this->selectedRole;
                $query->where('employees.role', $role);
            }
        }

        if ($this->selectedDepartmentId) {
            $deptId = $this->selectedDepartmentId;
            $query->where(function ($q) use ($deptId) {
                $q->where('employees.department_id', $deptId)
                    ->orWhereHas('student.program', fn ($pq) => $pq->where('department_id', $deptId));
            });
        }

        if ($this->search) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('users.name', 'like', "%{$s}%")
                    ->orWhere('users.email', 'like', "%{$s}%")
                    ->orWhere('employees.employee_number', 'like', "%{$s}%")
                    ->orWhere('employees.first_name', 'like', "%{$s}%")
                    ->orWhere('employees.last_name', 'like', "%{$s}%")
                    ->orWhere('students.student_number', 'like', "%{$s}%")
                    ->orWhere('students.first_name', 'like', "%{$s}%")
                    ->orWhere('students.last_name', 'like', "%{$s}%");
            });
        }

        $users = $query
            ->orderByRaw('COALESCE(employees.last_name, students.last_name, users.name) ASC')
            ->orderByRaw('COALESCE(employees.first_name, students.first_name) ASC')
            ->paginate(10);
        $userIds = $users->pluck('id')->toArray();

        $evaluateeStats = [];
        $studentSubmittedCounts = [];

        if ($semId && ! empty($userIds)) {
            $evaluateeStats = DB::table('evaluations')
                ->where('semester_id', $semId)
                ->whereIn('evaluatee_id', $userIds)
                ->selectRaw('evaluatee_id, count(*) as total_reviews, round(avg(rating_average), 2) as avg_rating')
                ->groupBy('evaluatee_id')
                ->get()
                ->keyBy('evaluatee_id');

            $studentSubmittedCounts = DB::table('evaluations')
                ->where('semester_id', $semId)
                ->whereIn('evaluator_id', $userIds)
                ->selectRaw('evaluator_id, count(*) as total_submitted')
                ->groupBy('evaluator_id')
                ->pluck('total_submitted', 'evaluator_id')
                ->toArray();
        }

        return [
            'users' => $users,
            'evaluateeStats' => $evaluateeStats,
            'studentSubmittedCounts' => $studentSubmittedCounts,
        ];
    }
}; ?>

<div class="flex flex-col gap-6 sm:gap-8 w-full px-4 sm:px-6 lg:px-8 py-6 text-left">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Results</flux:heading>
        </div>
    </div>

    <!-- Filter & Search Controls Bar -->
    <div class="flex flex-col gap-4 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 w-full">
            <!-- Search Input -->
            <div class="flex-1 min-w-[220px]">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, ID number, or email..." class="w-full" />
            </div>

            <!-- Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1 items-center">
                <!-- Semester Filter -->
                <div>
                    <flux:select wire:model.live="selectedSemesterId" class="w-full" placeholder="Select Semester">
                        @foreach($this->semesters as $sem)
                            <flux:select.option value="{{ $sem->id }}">A.Y. {{ $sem->academicYear?->name }} — {{ $sem->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Role Filter -->
                <div>
                    <flux:select wire:model.live="selectedRole" class="w-full" placeholder="All Roles">
                        <flux:select.option value="">All Roles</flux:select.option>
                        <flux:select.option value="dean">Dean</flux:select.option>
                        <flux:select.option value="program head">Program Head</flux:select.option>
                        <flux:select.option value="department head">Department Head</flux:select.option>
                        <flux:select.option value="faculty">Professor / Faculty</flux:select.option>
                        <flux:select.option value="staff">Staff</flux:select.option>
                        <flux:select.option value="student">Student</flux:select.option>
                    </flux:select>
                </div>

                <!-- Department Filter -->
                <div>
                    <flux:select wire:model.live="selectedDepartmentId" class="w-full" placeholder="All Departments">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" class="shrink-0 self-end lg:self-center" />
        </div>
    </div>

    <!-- Skeleton Loading State -->
    <div wire:loading wire:target="search, selectedRole, selectedDepartmentId, selectedSemesterId, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <!-- Results Table -->
    <div wire:loading.remove wire:target="search, selectedRole, selectedDepartmentId, selectedSemesterId, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full min-w-[850px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[24%] min-w-[180px] px-5 py-3.5 whitespace-nowrap">Full Name</th>
                        <th class="w-[12%] min-w-[100px] px-4 py-3.5 whitespace-nowrap">Role</th>
                        <th class="w-[24%] min-w-[170px] px-4 py-3.5 whitespace-nowrap">Department</th>
                        <th class="w-[16%] min-w-[130px] px-4 py-3.5 text-center whitespace-nowrap">Reviews Received</th>
                        <th class="w-[14%] min-w-[130px] px-4 py-3.5 text-center whitespace-nowrap">Overall Rating</th>
                        <th class="w-[10%] min-w-[90px] px-5 py-3.5 text-right whitespace-nowrap">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse($users as $user)
                        @php
                            $fullName = $user->employee?->formatted_name ?? $user->student?->formatted_name ?? $user->name;
                            $identifier = $user->employee?->employee_number ?? $user->student?->student_number ?? $user->email;
                            $dept = $user->employee?->department ?? $user->student?->program?->department;
                            $rawRole = $user->employee?->role ?? ($user->student ? 'student' : 'user');
                            
                            $roleLabel = match($rawRole) {
                                'faculty' => 'Professor',
                                'program head' => 'Program Head',
                                'department head' => 'Dept Head',
                                'dean' => 'Dean',
                                'staff' => 'Staff',
                                'student' => 'Student',
                                default => ucfirst($rawRole)
                            };

                            $isStudent = (bool)$user->student;
                            $stat = $evaluateeStats[$user->id] ?? null;
                            $reviewCount = $stat ? (int) $stat->total_reviews : 0;
                            $avgRating = $stat && $reviewCount > 0 ? (float) $stat->avg_rating : null;
                            $tier = $avgRating !== null ? $this->getRatingTier($avgRating) : null;
                            $studentSubmitted = $studentSubmittedCounts[$user->id] ?? 0;
                        @endphp
                        <tr wire:key="usr-{{ $user->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Full Name -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 truncate max-w-[220px]" title="{{ $fullName }}">
                                    {{ $fullName }}
                                </div>
                                <div class="text-xs text-zinc-400 font-mono">
                                    {{ $identifier }}
                                </div>
                            </td>

                            <!-- Role -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <flux:badge size="sm" variant="neutral" class="font-semibold whitespace-nowrap">
                                    {{ $roleLabel }}
                                </flux:badge>
                            </td>

                            <!-- Department -->
                            <td class="px-4 py-3.5 text-xs text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                @if($dept)
                                    <span class="font-semibold block truncate max-w-[200px]" title="{{ $dept->name }}">{{ $dept->name }}</span>
                                    <span class="text-zinc-400 font-mono text-[11px]">({{ $dept->code }})</span>
                                @else
                                    <span class="text-zinc-400 italic">Unassigned</span>
                                @endif
                            </td>

                            <!-- Reviews Received -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($isStudent)
                                    <span class="font-black font-mono text-zinc-800 dark:text-zinc-200">
                                        {{ $studentSubmitted }}
                                    </span>
                                    <span class="text-[11px] text-zinc-400 block font-medium">
                                        forms submitted
                                    </span>
                                @else
                                    <span class="font-black font-mono text-zinc-800 dark:text-zinc-200">
                                        {{ $reviewCount }}
                                    </span>
                                    <span class="text-[11px] text-zinc-400 block font-medium">
                                        {{ $reviewCount === 1 ? 'review received' : 'reviews received' }}
                                    </span>
                                @endif
                            </td>

                            <!-- Overall Rating -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($isStudent)
                                    <span class="text-xs text-zinc-400 italic">Evaluator</span>
                                @elseif($avgRating !== null && $reviewCount > 0)
                                    <div class="inline-flex items-center gap-1.5 font-extrabold text-sm tabular-nums text-zinc-900 dark:text-zinc-100">
                                        <flux:icon name="star" variant="solid" class="size-3.5 text-amber-500 fill-amber-500" />
                                        <span>{{ number_format($avgRating, 2) }}</span>
                                    </div>
                                    <div class="mt-0.5">
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider {{ $tier['classes'] }}">
                                            {{ $tier['label'] }}
                                        </span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700">
                                        No Ratings
                                    </span>
                                @endif
                            </td>

                            <!-- Details Action -->
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                <flux:button size="sm" variant="subtle" icon="chart-bar" wire:click="viewDetails({{ $user->id }})">
                                    Breakdown
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-zinc-400">
                                <flux:icon name="magnifying-glass" class="size-8 mx-auto mb-2 text-zinc-400 dark:text-zinc-600" />
                                No evaluation records found matching your filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $users->links() }}
        </div>
    </div>

    <!-- Detailed Breakdown Modal -->
    @if($showModal && $this->selectedUserDetails)
        @php 
            $details = $this->selectedUserDetails; 
            $thematic = $details->thematic;
            $hasThematicData = $thematic['has_data'] ?? false;
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex justify-center items-center p-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-3xl shadow-2xl max-h-[90vh] overflow-y-auto flex flex-col">
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-zinc-150 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-50">{{ $details->full_name }}</h2>
                            <flux:badge size="sm" variant="neutral">{{ $details->role }}</flux:badge>
                        </div>
                        <p class="text-xs text-zinc-500 mt-1">
                            ID: <span class="font-mono text-zinc-700 dark:text-zinc-300 font-semibold">{{ $details->identifier }}</span> &bull; 
                            Dept: <span class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $details->department }}</span> &bull; 
                            <span class="text-zinc-500">{{ $details->semester_name }}</span>
                        </p>
                    </div>
                    <flux:button variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
                </div>

                <!-- Modal Body -->
                <div class="p-6 flex flex-col gap-6">
                    <!-- Top 3 Simplified KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <!-- Card 1: Overall Rating -->
                        <div class="bg-zinc-50/70 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/80 p-4 rounded-xl text-center flex flex-col justify-between">
                            <div>
                                <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Overall Rating</span>
                                <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100 mt-2 tabular-nums">
                                    {{ $details->total_received > 0 ? number_format($details->overall_average, 2) : '—' }} 
                                    <span class="text-xs font-normal text-zinc-400">/ 5.00</span>
                                </div>
                            </div>
                            <div class="mt-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $details->overall_tier['classes'] }}">
                                    {{ $details->overall_tier['label'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Card 2: Reviews Received -->
                        <div class="bg-zinc-50/70 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/80 p-4 rounded-xl text-center flex flex-col justify-between">
                            <div>
                                <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Reviews Received</span>
                                <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100 mt-2 tabular-nums">
                                    {{ number_format($details->total_received) }}
                                    @if($details->expected_reviews > 0)
                                        <span class="text-xs font-normal text-zinc-400">/ {{ number_format($details->expected_reviews) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $details->response_rate >= 80 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' }}">
                                    {{ $details->response_rate }}% Response Rate
                                </span>
                            </div>
                        </div>

                        <!-- Card 3: Positive Feedback -->
                        <div class="bg-zinc-50/70 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/80 p-4 rounded-xl text-center flex flex-col justify-between">
                            <div>
                                <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Positive Feedback</span>
                                <div class="text-3xl font-black text-zinc-900 dark:text-zinc-100 mt-2 tabular-nums">
                                    {{ $hasThematicData ? $thematic['positive_pct'] . '%' : '—' }}
                                </div>
                            </div>
                            <div class="mt-2.5">
                                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-medium block truncate">
                                    {{ $hasThematicData ? ($thematic['positive_count'] . ' of ' . $thematic['total_analyzed'] . ' positive') : 'No comments submitted' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Reviews by Evaluation Source -->
                    @if(!empty($details->type_averages))
                        <div class="space-y-3">
                            <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">Reviews by Evaluation Source</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                @foreach($details->type_averages as $type => $info)
                                    @php
                                        $sourceTier = $this->getRatingTier($info->average);
                                    @endphp
                                    <div class="p-3.5 bg-zinc-50/70 dark:bg-zinc-800/30 rounded-xl border border-zinc-200 dark:border-zinc-700/80 flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate block">{{ $info->label }}</span>
                                            <span class="text-[11px] text-zinc-400 block mt-0.5">
                                                {{ $info->count }} {{ $info->count === 1 ? 'review' : 'reviews' }}
                                            </span>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-sm font-black font-mono text-zinc-900 dark:text-zinc-100 tabular-nums">
                                                ★ {{ number_format($info->average, 2) }}
                                            </span>
                                            <span class="text-[10px] text-zinc-400 block font-medium">
                                                {{ $sourceTier['label'] }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Feedback Analysis Section -->
                    <div class="border border-zinc-200 dark:border-zinc-700/80 rounded-xl p-4.5 bg-zinc-50/40 dark:bg-zinc-800/20 space-y-4">
                        <div class="flex items-center justify-between border-b border-zinc-200/80 dark:border-zinc-700/60 pb-3">
                            <div class="flex items-center gap-2">
                                <flux:icon name="sparkles" class="size-4 text-[#9b0000] dark:text-[#e07a7a]" />
                                <h3 class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">
                                    Feedback &amp; Comment Analysis
                                </h3>
                            </div>
                            <span class="text-[11px] text-zinc-400 font-medium">AI Sentiment &amp; Topic Analysis</span>
                        </div>

                        @if($hasThematicData)
                            <!-- Sentiment Distribution Pills -->
                            <div class="flex items-center gap-2 text-xs flex-wrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 font-bold tabular-nums text-[11px]">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $thematic['positive_count'] }} Positive ({{ $thematic['positive_pct'] }}%)
                                </span>
                                @if($thematic['neutral_count'] > 0)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 font-semibold tabular-nums text-[11px]">
                                        <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                        {{ $thematic['neutral_count'] }} Neutral
                                    </span>
                                @endif
                                @if($thematic['negative_count'] > 0)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800 font-bold tabular-nums text-[11px]">
                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                        {{ $thematic['negative_count'] }} Constructive
                                    </span>
                                @endif
                            </div>

                            <!-- Overall Feedback Summary -->
                            <div class="p-3.5 rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700/80 flex items-start gap-3">
                                <flux:icon name="document-text" class="size-4 text-[#9b0000] dark:text-[#e07a7a] shrink-0 mt-0.5" />
                                <div class="space-y-0.5">
                                    <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 block">Overall Feedback Summary</span>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed font-normal">
                                        {{ $thematic['narrative_summary'] }}
                                    </p>
                                </div>
                            </div>

                            <!-- Key Strengths & Areas for Improvement -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <!-- Strengths -->
                                <div class="bg-white dark:bg-zinc-900 border border-emerald-200/80 dark:border-emerald-800/40 rounded-xl p-3.5 space-y-2">
                                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5 uppercase tracking-wider">
                                        <flux:icon name="hand-thumb-up" class="size-3.5" />
                                        Key Strengths Mentioned
                                    </span>
                                    @if(!empty($thematic['positive_drivers']))
                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            @foreach($thematic['positive_drivers'] as $pos)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50/80 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                                                    <span>{{ $pos['term'] }}</span>
                                                    <span class="text-[10px] opacity-70">({{ $pos['count'] }})</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-zinc-400 italic">No recurring positive topics extracted.</p>
                                    @endif
                                </div>

                                <!-- Areas for Improvement -->
                                <div class="bg-white dark:bg-zinc-900 border border-amber-200/80 dark:border-amber-800/40 rounded-xl p-3.5 space-y-2">
                                    <span class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1.5 uppercase tracking-wider">
                                        <flux:icon name="light-bulb" class="size-3.5" />
                                        Areas for Improvement
                                    </span>
                                    @if(!empty($thematic['constructive_drivers']))
                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            @foreach($thematic['constructive_drivers'] as $neg)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50/80 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                                                    <span>{{ $neg['term'] }}</span>
                                                    <span class="text-[10px] opacity-70">({{ $neg['count'] }})</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-xs text-zinc-400 italic">No recurring areas for improvement detected.</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-zinc-400 italic">No written feedback has been submitted for this person in the selected semester.</p>
                        @endif
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 border-t border-zinc-150 dark:border-zinc-800 flex justify-end">
                    <flux:button variant="primary" wire:click="$set('showModal', false)" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white">
                        Close Breakdown
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
