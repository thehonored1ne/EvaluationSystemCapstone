<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Semester;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationAnswer;
use App\Models\AcademicClass;

new #[Layout('components.layouts.app')] class extends Component {
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

    public function updatedSearch() { $this->resetPage(); }
    public function updatedSelectedDepartmentId() { $this->resetPage(); }
    public function updatedSelectedSemesterId() { $this->resetPage(); }
    public function updatedSelectedRole() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedDepartmentId', 'selectedRole']);
        $this->resetPage();
    }

    public function getSemestersProperty()
    {
        return Semester::with('academicYear')->orderBy('id', 'desc')->get();
    }

    public function getDepartmentsProperty()
    {
        return Department::orderBy('name')->get();
    }

    public function viewDetails($userId)
    {
        $this->viewingUserId = $userId;
        $this->showModal = true;
    }

    public function getSelectedUserDetailsProperty()
    {
        if (!$this->viewingUserId || !$this->selectedSemesterId) return null;
        
        $user = User::with(['employee.department', 'student.program.department'])->find($this->viewingUserId);
        if (!$user) return null;

        $semId = $this->selectedSemesterId;

        // Received evaluations
        $evalsQuery = Evaluation::where('evaluatee_id', $user->id)->where('semester_id', $semId);
        $totalReceived = $evalsQuery->count();
        $overallAvg = $totalReceived > 0 ? round($evalsQuery->avg('rating_average'), 2) : 0.00;

        // Submitted evaluations
        $submittedCount = Evaluation::where('evaluator_id', $user->id)->where('semester_id', $semId)->count();

        // Categorical breakdown
        $evalTypeLabels = [
            'upward_student' => 'Student Evaluation',
            'peer' => 'Peer Evaluation',
            'downward' => 'Superior / Head Evaluation',
            'self' => 'Self Evaluation',
            'upward_employee' => 'Subordinate Evaluation',
        ];

        $typeAverages = [];
        foreach ($evalTypeLabels as $type => $label) {
            $tCount = Evaluation::where('evaluatee_id', $user->id)->where('semester_id', $semId)->where('evaluation_type', $type)->count();
            if ($tCount > 0) {
                $tAvg = round(Evaluation::where('evaluatee_id', $user->id)->where('semester_id', $semId)->where('evaluation_type', $type)->avg('rating_average'), 2);
                $typeAverages[$type] = (object)[
                    'label' => $label,
                    'count' => $tCount,
                    'average' => $tAvg,
                ];
            }
        }

        $comments = Evaluation::where('evaluatee_id', $user->id)
            ->where('semester_id', $semId)
            ->whereNotNull('comments')
            ->where('comments', '!=', '')
            ->pluck('comments')
            ->toArray();

        $deptName = $user->employee?->department?->name ?? $user->student?->program?->department?->name ?? 'Unassigned';
        $identifier = $user->employee?->employee_number ?? $user->student?->student_number ?? $user->email;
        $rawRole = $user->employee?->role ?? ($user->student ? 'student' : 'user');
        $roleLabel = match($rawRole) {
            'faculty' => 'Professor',
            'program head' => 'Program Head',
            'department head' => 'Department Head',
            'dean' => 'Dean',
            'staff' => 'Staff',
            'student' => 'Student',
            default => ucfirst($rawRole)
        };

        return (object)[
            'user' => $user,
            'full_name' => $user->employee?->formatted_name ?? $user->student?->formatted_name ?? $user->name,
            'role' => $roleLabel,
            'identifier' => $identifier,
            'department' => $deptName,
            'total_received' => $totalReceived,
            'submitted_count' => $submittedCount,
            'overall_average' => $overallAvg,
            'type_averages' => $typeAverages,
            'comments' => $comments,
        ];
    }

    public function with(): array
    {
        $semId = $this->selectedSemesterId;
        
        $query = User::query()
            ->where(function ($q) {
                $q->whereHas('employee')
                  ->orWhereHas('student');
            })
            ->with(['employee.department', 'student.program.department']);

        if ($this->selectedRole) {
            if ($this->selectedRole === 'student') {
                $query->whereHas('student');
            } elseif ($this->selectedRole === 'professor' || $this->selectedRole === 'faculty') {
                $query->whereHas('employee', fn($eq) => $eq->where('role', 'faculty'));
            } else {
                $role = $this->selectedRole;
                $query->whereHas('employee', fn($eq) => $eq->where('role', $role));
            }
        }

        if ($this->selectedDepartmentId) {
            $deptId = $this->selectedDepartmentId;
            $query->where(function ($q) use ($deptId) {
                $q->whereHas('employee', fn($eq) => $eq->where('department_id', $deptId))
                  ->orWhereHas('student.program', fn($pq) => $pq->where('department_id', $deptId));
            });
        }

        if ($this->search) {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhereHas('employee', fn($eq) => $eq->where('employee_number', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"))
                  ->orWhereHas('student', fn($sq) => $sq->where('student_number', 'like', "%{$s}%")->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"));
            });
        }

        $users = $query->orderBy('name')->paginate(10);

        return [
            'users' => $users,
        ];
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-left">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Results</flux:heading>
            <flux:subheading>Comprehensive institutional performance evaluations, submission tracking, and score breakdowns.</flux:subheading>
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
            <table class="w-full min-w-[800px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[28%] min-w-[180px] px-4 py-3.5 whitespace-nowrap">Full Name</th>
                        <th class="w-[14%] min-w-[110px] px-4 py-3.5 whitespace-nowrap">Role</th>
                        <th class="w-[24%] min-w-[160px] px-4 py-3.5 whitespace-nowrap">Department</th>
                        <th class="w-[14%] min-w-[110px] px-4 py-3.5 text-center whitespace-nowrap">Total Submissions</th>
                        <th class="w-[10%] min-w-[90px] px-4 py-3.5 text-center whitespace-nowrap">Status</th>
                        <th class="w-[10%] min-w-[80px] px-4 py-3.5 text-right whitespace-nowrap">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse($users as $user)
                        @php
                            $semId = $selectedSemesterId;
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
                            if ($isStudent) {
                                $submissionsCount = $semId ? Evaluation::where('evaluator_id', $user->id)->where('semester_id', $semId)->count() : 0;
                                $isComplete = $submissionsCount > 0;
                            } else {
                                $submissionsCount = $semId ? Evaluation::where('evaluatee_id', $user->id)->where('semester_id', $semId)->count() : 0;
                                $isComplete = $submissionsCount > 0;
                            }
                        @endphp
                        <tr wire:key="usr-{{ $user->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Full Name -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
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

                            <!-- Total Submissions -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <span class="font-black font-mono text-zinc-800 dark:text-zinc-200">
                                    {{ $submissionsCount }}
                                </span>
                                <span class="text-[11px] text-zinc-400 block font-medium">
                                    {{ $isStudent ? 'completed' : 'evaluations' }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                @if($isComplete)
                                    <flux:badge size="sm" variant="success" class="font-bold">Complete</flux:badge>
                                @else
                                    <flux:badge size="sm" variant="warning" class="font-bold">Incomplete</flux:badge>
                                @endif
                            </td>

                            <!-- Details Action -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" icon="eye" wire:click="viewDetails({{ $user->id }})">
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
        @php $details = $this->selectedUserDetails; @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex justify-center items-center p-4">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-y-auto flex flex-col border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-zinc-150 dark:border-zinc-800 flex justify-between items-center bg-zinc-50 dark:bg-zinc-800/40">
                    <div>
                        <h2 class="text-xl font-bold text-zinc-900 dark:text-zinc-50">{{ $details->full_name }}</h2>
                        <p class="text-xs text-zinc-500 mt-0.5">ID: {{ $details->identifier }} | Role: {{ $details->role }} | Dept: {{ $details->department }}</p>
                    </div>
                    <flux:button variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
                </div>

                <!-- Modal Body -->
                <div class="p-6 flex flex-col gap-6">
                    <!-- KPI summaries -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-[#9b0000]/10 dark:bg-[#9b0000]/20 border border-[#9b0000]/20 p-4 rounded-xl text-center">
                            <div class="text-xs font-semibold text-[#9b0000] dark:text-[#f89696] uppercase tracking-wider">Overall Mean Score</div>
                            <div class="text-2xl font-black text-[#9b0000] dark:text-[#f89696] mt-1">
                                {{ $details->total_received > 0 ? number_format($details->overall_average, 2) : '—' }} 
                                <span class="text-xs font-normal">/ 5.0</span>
                            </div>
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700 p-4 rounded-xl text-center">
                            <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Evaluations Received</div>
                            <div class="text-2xl font-bold text-zinc-800 dark:text-zinc-200 mt-1">
                                {{ $details->total_received }}
                            </div>
                        </div>

                        <div class="bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700 p-4 rounded-xl text-center">
                            <div class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Evaluations Submitted</div>
                            <div class="text-2xl font-bold text-zinc-800 dark:text-zinc-200 mt-1">
                                {{ $details->submitted_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Category Breakdown -->
                    @if(!empty($details->type_averages))
                        <div>
                            <h3 class="font-bold text-zinc-800 dark:text-zinc-200 mb-3 text-base">Evaluation Breakdown by Source</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($details->type_averages as $type => $info)
                                    <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-800/20 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        <div>
                                            <span class="text-xs font-bold text-zinc-900 dark:text-zinc-100 block">{{ $info->label }}</span>
                                            <span class="text-[11px] text-zinc-400">{{ $info->count }} review{{ $info->count == 1 ? '' : 's' }}</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-base font-black font-mono text-[#9b0000] dark:text-[#f89696]">
                                                {{ number_format($info->average, 2) }}
                                            </span>
                                            <span class="text-[11px] text-zinc-400 block">/ 5.00</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Qualitative Comments -->
                    <div>
                        <h3 class="font-bold text-zinc-800 dark:text-zinc-200 mb-3 text-base">Feedback Comments & Notes</h3>
                        @if(empty($details->comments))
                            <p class="text-sm text-zinc-400 italic">No text comments submitted for this user.</p>
                        @else
                            <div class="flex flex-col gap-2 max-h-48 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-xl p-3 bg-zinc-50/50 dark:bg-zinc-800/20">
                                @foreach($details->comments as $comment)
                                    <div class="text-sm text-zinc-700 dark:text-zinc-300 p-2.5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-2xs">
                                        "{{ $comment }}"
                                    </div>
                                @endforeach
                            </div>
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
