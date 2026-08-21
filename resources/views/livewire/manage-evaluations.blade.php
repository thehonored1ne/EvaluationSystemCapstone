<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Evaluation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-evaluations-skeleton');
    }

    // Standardized Category Tabs: 'student', 'dean', 'program_head', 'department_head', 'peer', 'supervisor', 'self'
    public string $activeTab = 'student';
    public string $search = '';
    public string $selectedDepartmentId = '';
    public string $selectedRole = 'all';
    public string $selectedStatus = 'all';
    public int $perPage = 10;

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->with('academicYear')->first();
    }

    public function getDepartmentsProperty()
    {
        return Department::orderBy('type')->orderBy('name')->get();
    }

    public function selectTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->selectedStatus = 'all';
        $this->selectedRole = 'all';
        $this->resetPage();
    }

    public function updatedActiveTab()
    {
        $this->search = '';
        $this->selectedStatus = 'all';
        $this->selectedRole = 'all';
        $this->resetPage();
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedSelectedDepartmentId() { $this->resetPage(); }
    public function updatedSelectedRole() { $this->resetPage(); }
    public function updatedSelectedStatus() { $this->resetPage(); }

    /**
     * @param \Illuminate\Support\Collection<int, mixed> $items
     */
    protected function paginateCollection($items, int $perPage = 10): LengthAwarePaginator
    {
        $page = $this->getPage();
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $this->paginators['page'] ?? 'page',
            ]
        );
    }

    public function getClassesPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'student' ? $this->paginateCollection($this->classes, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getDeanTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'dean' ? $this->paginateCollection($this->deanTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getProgramHeadTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'program_head' ? $this->paginateCollection($this->programHeadTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getDepartmentHeadTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'department_head' ? $this->paginateCollection($this->departmentHeadTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getPeerTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'peer' ? $this->paginateCollection($this->peerTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getSupervisorTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'supervisor' ? $this->paginateCollection($this->supervisorTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    public function getSelfTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'self' ? $this->paginateCollection($this->selfTracking, $this->perPage) : new LengthAwarePaginator([], 0, $this->perPage);
    }

    // 1. Student Category (Student -> Faculty)
    public function getClassesProperty()
    {
        if ($this->activeTab !== 'student') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $user = auth()->user();
        $query = AcademicClass::where('semester_id', $sem->id)
            ->with(['subject', 'teacher.department']);

        if ($user?->hasRole('program head') && $user->employee) {
            $deptId = $user->employee->department_id;
            $query->whereHas('teacher', fn ($q) => $q->where('department_id', $deptId));
        } elseif ($user?->hasRole('dean') && $user->employee) {
            $deptId = $this->selectedDepartmentId ?: $user->employee->department_id;
            if ($deptId) {
                $query->whereHas('teacher', fn ($q) => $q->where('department_id', $deptId));
            }
        } elseif ($user?->hasRole('admin')) {
            if ($this->selectedDepartmentId) {
                $query->whereHas('teacher', fn ($q) => $q->where('department_id', $this->selectedDepartmentId));
            }
        }

        $enrolledMap = DB::table('class_student')
            ->selectRaw('class_id, count(*) as count')
            ->groupBy('class_id')
            ->pluck('count', 'class_id');

        $evaluatedMap = DB::table('evaluations')
            ->where('semester_id', $sem->id)
            ->whereNotNull('class_id')
            ->selectRaw('class_id, count(*) as count')
            ->groupBy('class_id')
            ->pluck('count', 'class_id');

        $allClasses = $query->get()->map(function ($class) use ($enrolledMap, $evaluatedMap) {
            $enrolled = (int) ($enrolledMap[$class->id] ?? 0);
            $evaluated = (int) ($evaluatedMap[$class->id] ?? 0);
            $percentage = $enrolled > 0 ? min(100, round(($evaluated / $enrolled) * 100)) : 0;

            $status = 'pending';
            if ($percentage === 100) {
                $status = 'completed';
            } elseif ($percentage > 0) {
                $status = 'in_progress';
            }

            return (object) [
                'id' => $class->id,
                'subject' => $class->subject,
                'teacher' => $class->teacher,
                'section' => $class->section,
                'department' => $class->teacher?->department,
                'enrolled' => $enrolled,
                'evaluated' => $evaluated,
                'percentage' => $percentage,
                'status' => $status,
            ];
        });

        return $allClasses->filter(function ($c) {
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $codeMatch = str_contains(strtolower($c->subject?->code ?? ''), $searchLower);
                $titleMatch = str_contains(strtolower($c->subject?->name ?? ''), $searchLower);
                $teacherMatch = str_contains(strtolower($c->teacher?->full_name ?? ''), $searchLower);
                $sectionMatch = str_contains(strtolower($c->section ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($c->department?->name ?? ''), $searchLower) || str_contains(strtolower($c->department?->code ?? ''), $searchLower);

                if (! $codeMatch && ! $titleMatch && ! $teacherMatch && ! $sectionMatch && ! $deptMatch) {
                    return false;
                }
            }

            if ($this->selectedStatus !== 'all' && $c->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        });
    }

    // 2. Dean Category (Dean -> Program Head)
    public function getDeanTrackingProperty()
    {
        if ($this->activeTab !== 'dean') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::where('role', 'dean')
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        $targetCount = Employee::where('role', 'program head')->where('status', 'active')->count();
        $submittedMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'downward')
            ->selectRaw('evaluator_id, count(*) as count')
            ->groupBy('evaluator_id')
            ->pluck('count', 'evaluator_id');

        return $query->get()->map(function ($emp) use ($targetCount, $submittedMap) {
            $user = $emp->user;
            if (! $user) {
                return null;
            }

            $submittedCount = (int) ($submittedMap[$user->id] ?? 0);
            $pct = $targetCount > 0 ? min(100, round(($submittedCount / $targetCount) * 100)) : ($submittedCount > 0 ? 100 : 0);
            $status = ($targetCount > 0 && $submittedCount >= $targetCount) ? 'completed' : ($submittedCount > 0 ? 'in_progress' : 'pending');

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => 'Dean',
                'department' => $emp->department,
                'target_label' => 'Program Heads Assigned',
                'target_count' => $targetCount,
                'submitted_count' => $submittedCount,
                'percentage' => $pct,
                'status' => $status,
            ];
        })->filter(function ($s) {
            if (! $s) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($s->name), $searchLower);
                $numMatch = str_contains(strtolower($s->employee_number ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($s->department?->name ?? ''), $searchLower) || str_contains(strtolower($s->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $s->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    // 3. Program Head Category (Program Head -> Faculty)
    public function getProgramHeadTrackingProperty()
    {
        if ($this->activeTab !== 'program_head') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::where('role', 'program head')
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        $deptFacultyCountMap = Employee::where('role', 'faculty')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        $submittedMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'downward')
            ->selectRaw('evaluator_id, count(*) as count')
            ->groupBy('evaluator_id')
            ->pluck('count', 'evaluator_id');

        return $query->get()->map(function ($emp) use ($deptFacultyCountMap, $submittedMap) {
            $user = $emp->user;
            if (! $user) {
                return null;
            }

            $targetCount = (int) ($deptFacultyCountMap[$emp->department_id] ?? 0);
            $submittedCount = (int) ($submittedMap[$user->id] ?? 0);

            $pct = $targetCount > 0 ? min(100, round(($submittedCount / $targetCount) * 100)) : ($submittedCount > 0 ? 100 : 0);
            $status = ($targetCount > 0 && $submittedCount >= $targetCount) ? 'completed' : ($submittedCount > 0 ? 'in_progress' : 'pending');

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => 'Program Head',
                'department' => $emp->department,
                'target_label' => 'Department Faculty Members',
                'target_count' => $targetCount,
                'submitted_count' => $submittedCount,
                'percentage' => $pct,
                'status' => $status,
            ];
        })->filter(function ($s) {
            if (! $s) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($s->name), $searchLower);
                $numMatch = str_contains(strtolower($s->employee_number ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($s->department?->name ?? ''), $searchLower) || str_contains(strtolower($s->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $s->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    // 4. Department Head Category (Department Head -> Staff)
    public function getDepartmentHeadTrackingProperty()
    {
        if ($this->activeTab !== 'department_head') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::where('role', 'department head')
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        $deptStaffCountMap = Employee::where('role', 'staff')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        $submittedMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'downward')
            ->selectRaw('evaluator_id, count(*) as count')
            ->groupBy('evaluator_id')
            ->pluck('count', 'evaluator_id');

        return $query->get()->map(function ($emp) use ($deptStaffCountMap, $submittedMap) {
            $user = $emp->user;
            if (! $user) {
                return null;
            }

            $targetCount = (int) ($deptStaffCountMap[$emp->department_id] ?? 0);
            $submittedCount = (int) ($submittedMap[$user->id] ?? 0);

            $pct = $targetCount > 0 ? min(100, round(($submittedCount / $targetCount) * 100)) : ($submittedCount > 0 ? 100 : 0);
            $status = ($targetCount > 0 && $submittedCount >= $targetCount) ? 'completed' : ($submittedCount > 0 ? 'in_progress' : 'pending');

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => 'Department Head',
                'department' => $emp->department,
                'target_label' => 'Administrative Staff Members',
                'target_count' => $targetCount,
                'submitted_count' => $submittedCount,
                'percentage' => $pct,
                'status' => $status,
            ];
        })->filter(function ($s) {
            if (! $s) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($s->name), $searchLower);
                $numMatch = str_contains(strtolower($s->employee_number ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($s->department?->name ?? ''), $searchLower) || str_contains(strtolower($s->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $s->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    // 5. Peer Category (Faculty -> Faculty Peer, Staff -> Staff Peer)
    public function getPeerTrackingProperty()
    {
        if ($this->activeTab !== 'peer') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::whereIn('role', ['faculty', 'staff'])
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        if ($this->selectedRole !== 'all') {
            $query->where('role', $this->selectedRole);
        }

        $deptRoleCountMap = Employee::whereIn('role', ['faculty', 'staff'])
            ->where('status', 'active')
            ->select(['department_id', 'role'])
            ->get()
            ->groupBy(fn ($e) => $e->department_id.'_'.$e->role)
            ->map
            ->count();

        $submittedMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'peer')
            ->selectRaw('evaluator_id, count(*) as count')
            ->groupBy('evaluator_id')
            ->pluck('count', 'evaluator_id');

        return $query->get()->map(function ($emp) use ($deptRoleCountMap, $submittedMap) {
            $user = $emp->user;
            if (! $user) {
                return null;
            }

            $totalInGroup = (int) ($deptRoleCountMap[$emp->department_id.'_'.$emp->role] ?? 0);
            $peerCount = max(0, $totalInGroup - 1);
            $submittedCount = (int) ($submittedMap[$user->id] ?? 0);

            $pct = $peerCount > 0 ? min(100, round(($submittedCount / $peerCount) * 100)) : ($submittedCount > 0 ? 100 : 0);
            $status = ($peerCount > 0 && $submittedCount >= $peerCount) ? 'completed' : ($submittedCount > 0 ? 'in_progress' : 'pending');

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => ucfirst($emp->role),
                'department' => $emp->department,
                'target_count' => $peerCount,
                'submitted_count' => $submittedCount,
                'percentage' => $pct,
                'status' => $status,
            ];
        })->filter(function ($p) {
            if (! $p) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($p->name), $searchLower);
                $numMatch = str_contains(strtolower($p->employee_number ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($p->department?->name ?? ''), $searchLower) || str_contains(strtolower($p->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $p->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    // 6. Supervisor Category (Upward: Faculty -> PH, Staff -> DH, PH & DH -> Dean)
    public function getSupervisorTrackingProperty()
    {
        if ($this->activeTab !== 'supervisor') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::whereIn('role', ['faculty', 'staff', 'program head', 'department head'])
            ->where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        if ($this->selectedRole !== 'all') {
            $query->where('role', $this->selectedRole);
        }

        $dean = Employee::where('role', 'dean')->where('status', 'active')->first();
        $phMap = Employee::where('role', 'program head')->where('status', 'active')->get()->keyBy('department_id');
        $dhMap = Employee::where('role', 'department head')->where('status', 'active')->get()->keyBy('department_id');
        $evalMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'upward_employee')
            ->get()
            ->keyBy('evaluator_id');

        return $query->get()->map(function ($emp) use ($dean, $phMap, $dhMap, $evalMap) {
            $user = $emp->user;
            if (! $user) {
                return null;
            }

            $supervisorName = 'Unassigned';
            if ($emp->role === 'faculty') {
                $ph = $phMap[$emp->department_id] ?? null;
                $supervisorName = $ph ? $ph->full_name.' (Program Head)' : 'Program Head';
            } elseif ($emp->role === 'staff') {
                $dh = $dhMap[$emp->department_id] ?? null;
                $supervisorName = $dh ? $dh->full_name.' (Department Head)' : 'Department Head';
            } elseif (in_array($emp->role, ['program head', 'department head'])) {
                $supervisorName = $dean ? $dean->full_name.' (Dean)' : 'Dean of Academic Affairs';
            }

            $eval = $evalMap[$user->id] ?? null;
            $submitted = $eval !== null;

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => ucwords($emp->role),
                'department' => $emp->department,
                'supervisor_name' => $supervisorName,
                'submitted' => $submitted,
                'submitted_at' => $eval?->created_at,
                'status' => $submitted ? 'completed' : 'pending',
            ];
        })->filter(function ($sub) {
            if (! $sub) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($sub->name), $searchLower);
                $numMatch = str_contains(strtolower($sub->employee_number ?? ''), $searchLower);
                $supMatch = str_contains(strtolower($sub->supervisor_name), $searchLower);
                $deptMatch = str_contains(strtolower($sub->department?->name ?? ''), $searchLower) || str_contains(strtolower($sub->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $supMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $sub->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    // 7. Self Category (Self -> Self)
    public function getSelfTrackingProperty()
    {
        if ($this->activeTab !== 'self') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $query = Employee::where('status', 'active')
            ->with(['department', 'user']);

        if ($this->selectedDepartmentId) {
            $query->where('department_id', $this->selectedDepartmentId);
        }

        if ($this->selectedRole !== 'all') {
            $query->where('role', $this->selectedRole);
        }

        $selfEvalMap = Evaluation::where('semester_id', $sem->id)
            ->where('evaluation_type', 'self')
            ->get()
            ->keyBy('evaluator_id');

        return $query->get()->map(function ($emp) use ($selfEvalMap) {
            $user = $emp->user;
            $eval = $user ? ($selfEvalMap[$user->id] ?? null) : null;
            $submitted = $eval !== null;

            return (object) [
                'id' => $emp->id,
                'name' => $emp->full_name,
                'employee_number' => $emp->employee_number,
                'role' => $emp->role,
                'role_label' => ucwords($emp->role),
                'department' => $emp->department,
                'submitted' => $submitted,
                'submitted_at' => $eval?->created_at,
                'status' => $submitted ? 'completed' : 'pending',
            ];
        })->filter(function ($e) {
            if (! $e) {
                return false;
            }
            if ($this->search) {
                $searchLower = strtolower($this->search);
                $nameMatch = str_contains(strtolower($e->name), $searchLower);
                $numMatch = str_contains(strtolower($e->employee_number ?? ''), $searchLower);
                $deptMatch = str_contains(strtolower($e->department?->name ?? ''), $searchLower) || str_contains(strtolower($e->department?->code ?? ''), $searchLower);
                if (! $nameMatch && ! $numMatch && ! $deptMatch) {
                    return false;
                }
            }
            if ($this->selectedStatus !== 'all' && $e->status !== $this->selectedStatus) {
                return false;
            }

            return true;
        })->values();
    }

    public function sendReminderToast()
    {
        $user = auth()->user();

        \Illuminate\Support\Facades\Artisan::call('evaluations:send-reminders', ['--force' => true]);

        if ($user && function_exists('activity')) {
            activity('evaluations')
                ->causedBy($user)
                ->log('Broadcasted evaluation completion reminders across all pending evaluators via Completion Tracking.');
        }

        \Flux::toast(
            heading: 'Reminders Broadcasted',
            text: 'Evaluation submission reminders have been processed and broadcasted to all pending evaluators.',
            variant: 'success'
        );
    }

    public function getSummaryStatsProperty(): array
    {
        $semId = $this->activeSemester?->id;
        if (! $semId) {
            return [
                'totalSubmissions' => 0,
                'avgStudentProgress' => 0,
                'avgPeerProgress' => 0,
                'selfDone' => 0,
                'selfTotal' => 0,
                'selfPct' => 0,
            ];
        }

        $totalSubmissions = Evaluation::where('semester_id', $semId)->count();

        $enrolledSum = \Illuminate\Support\Facades\DB::table('class_student')
            ->join('classes', 'classes.id', '=', 'class_student.class_id')
            ->where('classes.semester_id', $semId)
            ->count();

        $evaluatedSum = Evaluation::where('semester_id', $semId)
            ->whereNotNull('class_id')
            ->count();

        $avgStudentProgress = $enrolledSum > 0 ? min(100, round(($evaluatedSum / $enrolledSum) * 100)) : 0;

        $totalPeerTarget = max(1, Employee::whereIn('role', ['faculty', 'staff'])->where('status', 'active')->count() * 2);
        $submittedPeerCount = Evaluation::where('semester_id', $semId)->where('evaluation_type', 'peer')->count();
        $avgPeerProgress = $totalPeerTarget > 0 ? min(100, round(($submittedPeerCount / $totalPeerTarget) * 100)) : 0;

        $totalEmployees = Employee::where('status', 'active')->count();
        $submittedSelfCount = Evaluation::where('semester_id', $semId)->where('evaluation_type', 'self')->count();
        $selfPct = $totalEmployees > 0 ? min(100, round(($submittedSelfCount / $totalEmployees) * 100)) : 0;

        return [
            'totalSubmissions' => $totalSubmissions,
            'avgStudentProgress' => $avgStudentProgress,
            'avgPeerProgress' => $avgPeerProgress,
            'selfDone' => $submittedSelfCount,
            'selfTotal' => $totalEmployees,
            'selfPct' => $selfPct,
        ];
    }

    public function getCategoryCountsProperty(): array
    {
        $semId = $this->activeSemester?->id;
        if (! $semId) {
            return [
                'student' => 0,
                'dean' => 0,
                'program_head' => 0,
                'department_head' => 0,
                'peer' => 0,
                'supervisor' => 0,
                'self' => 0,
            ];
        }

        $classQuery = AcademicClass::where('semester_id', $semId);
        if ($this->selectedDepartmentId) {
            $classQuery->whereHas('teacher', fn ($q) => $q->where('department_id', $this->selectedDepartmentId));
        }

        $roleCounts = Employee::where('status', 'active')
            ->when($this->selectedDepartmentId, fn ($q) => $q->where('department_id', $this->selectedDepartmentId))
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return [
            'student' => $classQuery->count(),
            'dean' => (int) ($roleCounts['dean'] ?? 0),
            'program_head' => (int) ($roleCounts['program head'] ?? 0),
            'department_head' => (int) ($roleCounts['department head'] ?? 0),
            'peer' => (int) (($roleCounts['faculty'] ?? 0) + ($roleCounts['staff'] ?? 0)),
            'supervisor' => (int) (($roleCounts['faculty'] ?? 0) + ($roleCounts['staff'] ?? 0) + ($roleCounts['program head'] ?? 0) + ($roleCounts['department head'] ?? 0)),
            'self' => (int) $roleCounts->sum(),
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Completion Tracking</flux:heading>
            <flux:subheading class="text-left text-zinc-500 dark:text-zinc-400">
                Real-time evaluation submission progress & completion tracking across all standardized evaluation categories.
            </flux:subheading>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
            @if(auth()->user()?->hasAnyRole(['admin', 'dean']))
                <div class="w-full sm:w-56">
                    <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments" aria-label="Filter evaluations by department" class="w-full">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <flux:button variant="primary" icon="paper-airplane" wire:click="sendReminderToast" size="sm" class="!bg-[#9b0000] hover:!bg-[#7a0000] text-white w-full sm:w-auto">
                Send Reminders
            </flux:button>
        </div>
    </div>

    <!-- Active Semester Indicator -->
    @if($this->activeSemester)
        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="size-2.5 rounded-full {{ $this->activeSemester->is_evaluation_open ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase tracking-wider">Active Academic Period</span>
                <span class="text-sm font-bold text-zinc-900 dark:text-zinc-100">
                    A.Y. {{ $this->activeSemester->academicYear->name }} — {{ $this->activeSemester->name }}
                </span>
            </div>
            <flux:badge variant="{{ $this->activeSemester->is_evaluation_open ? 'success' : 'danger' }}" size="sm" class="font-bold">
                {{ $this->activeSemester->is_evaluation_open ? 'Evaluations Open' : 'Evaluations Closed' }}
            </flux:badge>
        </div>
    @endif

    <!-- Top 4 Summary Stat Cards -->
    @php
        $stats = $this->summaryStats;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- Card 1: Total Submissions Recorded -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Total Submissions</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$stats['totalSubmissions']" />
                </span>
                <flux:icon icon="clipboard-document-check" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-[11px] text-zinc-400">Across all 7 standardized categories</span>
        </div>

        <!-- Card 2: Student Progress -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student Category Progress</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$stats['avgStudentProgress']" suffix="%" />
                </span>
                <flux:icon icon="academic-cap" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-[#9b0000] dark:bg-[#f89696] h-1.5 rounded-full transition-all duration-300" style="width: {{ $stats['avgStudentProgress'] }}%"></div>
            </div>
        </div>

        <!-- Card 3: Peer Category Progress -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Peer Category Progress</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$stats['avgPeerProgress']" suffix="%" />
                </span>
                <flux:icon icon="user-group" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-[#9b0000] dark:bg-[#f89696] h-1.5 rounded-full transition-all duration-300" style="width: {{ $stats['avgPeerProgress'] }}%"></div>
            </div>
        </div>

        <!-- Card 4: Self Evaluations Done -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-2 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Self Category Done</span>
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-black text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$stats['selfDone']" /> / <x-odometer :value="$stats['selfTotal']" />
                </span>
                <flux:icon icon="user" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-1.5 rounded-full overflow-hidden">
                <div class="bg-[#9b0000] dark:bg-[#f89696] h-1.5 rounded-full transition-all duration-300" style="width: {{ $stats['selfPct'] }}%"></div>
            </div>
        </div>
    </div>

    <!-- 7 Standardized Category Navigation Tabs (Exact Match to Question Setup) -->
    @php
        $catCounts = $this->categoryCounts;
    @endphp
    <div class="border-b border-zinc-200 dark:border-zinc-800 flex gap-2 md:gap-3 overflow-x-auto pb-0">
        <button 
            type="button"
            wire:click="selectTab('student')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'student' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="academic-cap" class="size-4" />
            Student ({{ $catCounts['student'] }} Classes)
        </button>

        <button 
            type="button"
            wire:click="selectTab('dean')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'dean' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="building-library" class="size-4" />
            Dean ({{ $catCounts['dean'] }})
        </button>

        <button 
            type="button"
            wire:click="selectTab('program_head')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'program_head' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="briefcase" class="size-4" />
            Program Head ({{ $catCounts['program_head'] }})
        </button>

        <button 
            type="button"
            wire:click="selectTab('department_head')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'department_head' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="building-office" class="size-4" />
            Department Head ({{ $catCounts['department_head'] }})
        </button>

        <button 
            type="button"
            wire:click="selectTab('peer')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'peer' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="user-group" class="size-4" />
            Peer ({{ $catCounts['peer'] }})
        </button>

        <button 
            type="button"
            wire:click="selectTab('supervisor')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'supervisor' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="arrow-trending-up" class="size-4" />
            Supervisor ({{ $catCounts['supervisor'] }})
        </button>

        <button 
            type="button"
            wire:click="selectTab('self')"
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === 'self' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            <flux:icon icon="user" class="size-4" />
            Self ({{ $catCounts['self'] }})
        </button>
    </div>

    <!-- Category Context Subheader Bar -->
    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
        Evaluation Context: 
        <span class="font-bold text-zinc-800 dark:text-zinc-200">
            {{ match($activeTab) {
                'student' => 'Student evaluates Assigned Faculty Member',
                'dean' => 'Dean evaluates Program Head',
                'program_head' => 'Program Head evaluates Department Faculty Member',
                'department_head' => 'Department Head evaluates Department Staff Member',
                'peer' => 'Faculty evaluates Faculty Peer / Staff evaluates Staff Peer',
                'supervisor' => 'Faculty evaluates PH / Staff evaluates DH / PH & DH evaluate Dean',
                'self' => 'Individual Employee Self Evaluation',
                default => ucfirst($activeTab)
            } }}
        </span>
    </div>

    <!-- TAB 1: Student Category Progress -->
    @if($activeTab === 'student')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search subject code, title, section, or faculty..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="w-full sm:w-48">
                    <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                        <flux:select.option value="all">All Statuses</flux:select.option>
                        <flux:select.option value="completed">100% Completed</flux:select.option>
                        <flux:select.option value="in_progress">In Progress</flux:select.option>
                        <flux:select.option value="pending">Pending (0%)</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @php $classesPaginated = $this->classesPaginated; @endphp
            @if($classesPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="clipboard-document-list" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No classes match your search or filter criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Subject & Class</th>
                                <th class="px-6 py-3.5">Section</th>
                                <th class="px-6 py-3.5">Faculty Member</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Submissions</th>
                                <th class="px-6 py-3.5">Completion Rate</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($classesPaginated as $c)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $c->subject?->code }}</div>
                                        <div class="text-xs text-zinc-500">{{ $c->subject?->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                        {{ $c->section }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $c->teacher?->full_name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono">{{ $c->teacher?->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $c->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $c->evaluated }} / {{ $c->enrolled }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $c->percentage === 100 ? 'bg-emerald-500' : 'bg-[#9b0000] dark:bg-[#f89696]' }}" style="width: {{ $c->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $c->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($c->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">100% Completed</flux:badge>
                                        @elseif($c->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $classesPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: Dean Category Progress -->
    @if($activeTab === 'dean')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search dean name, ID, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="w-full sm:w-44">
                    <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                        <flux:select.option value="all">All Statuses</flux:select.option>
                        <flux:select.option value="completed">Completed</flux:select.option>
                        <flux:select.option value="in_progress">In Progress</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @php $deanPaginated = $this->deanTrackingPaginated; @endphp
            @if($deanPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="building-library" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No dean evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Dean Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Target Evaluatees</th>
                                <th class="px-6 py-3.5">Program Heads Evaluated</th>
                                <th class="px-6 py-3.5">Completion Progress</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($deanPaginated as $dean)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $dean->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $dean->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $dean->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $dean->target_label }}
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $dean->submitted_count }} / {{ $dean->target_count }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $dean->percentage === 100 ? 'bg-emerald-500' : 'bg-[#9b0000] dark:bg-[#f89696]' }}" style="width: {{ $dean->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $dean->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($dean->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">Completed</flux:badge>
                                        @elseif($dean->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $deanPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 3: Program Head Category Progress -->
    @if($activeTab === 'program_head')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search program head name, ID, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="w-full sm:w-44">
                    <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                        <flux:select.option value="all">All Statuses</flux:select.option>
                        <flux:select.option value="completed">Completed</flux:select.option>
                        <flux:select.option value="in_progress">In Progress</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @php $phPaginated = $this->programHeadTrackingPaginated; @endphp
            @if($phPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="briefcase" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No program head evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Program Head Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Target Evaluatees</th>
                                <th class="px-6 py-3.5">Faculty Evaluated</th>
                                <th class="px-6 py-3.5">Completion Progress</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($phPaginated as $ph)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $ph->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $ph->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $ph->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $ph->target_label }}
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $ph->submitted_count }} / {{ $ph->target_count }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $ph->percentage === 100 ? 'bg-emerald-500' : 'bg-[#9b0000] dark:bg-[#f89696]' }}" style="width: {{ $ph->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $ph->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($ph->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">Completed</flux:badge>
                                        @elseif($ph->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $phPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 4: Department Head Category Progress -->
    @if($activeTab === 'department_head')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search department head name, ID, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="w-full sm:w-44">
                    <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                        <flux:select.option value="all">All Statuses</flux:select.option>
                        <flux:select.option value="completed">Completed</flux:select.option>
                        <flux:select.option value="in_progress">In Progress</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Table -->
            @php $dhPaginated = $this->departmentHeadTrackingPaginated; @endphp
            @if($dhPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="building-office" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No department head evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Department Head Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Target Evaluatees</th>
                                <th class="px-6 py-3.5">Staff Evaluated</th>
                                <th class="px-6 py-3.5">Completion Progress</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($dhPaginated as $dh)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $dh->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $dh->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $dh->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $dh->target_label }}
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $dh->submitted_count }} / {{ $dh->target_count }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $dh->percentage === 100 ? 'bg-emerald-500' : 'bg-[#9b0000] dark:bg-[#f89696]' }}" style="width: {{ $dh->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $dh->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($dh->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">Completed</flux:badge>
                                        @elseif($dh->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $dhPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 5: Peer Category Progress -->
    @if($activeTab === 'peer')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search evaluator name, ID, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <div class="w-full sm:w-36">
                        <flux:select wire:model.live="selectedRole" aria-label="Filter by employee role" class="w-full">
                            <flux:select.option value="all">All Roles</flux:select.option>
                            <flux:select.option value="faculty">Faculty</flux:select.option>
                            <flux:select.option value="staff">Staff</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-full sm:w-44">
                        <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                            <flux:select.option value="all">All Statuses</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                            <flux:select.option value="in_progress">In Progress</flux:select.option>
                            <flux:select.option value="pending">Pending</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>

            <!-- Table -->
            @php $peerPaginated = $this->peerTrackingPaginated; @endphp
            @if($peerPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="user-group" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No peer evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Evaluator Name</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Department Peers Evaluated</th>
                                <th class="px-6 py-3.5">Completion Progress</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($peerPaginated as $peer)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $peer->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $peer->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge variant="neutral" size="sm" class="font-bold">{{ $peer->role_label }}</flux:badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $peer->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-mono font-bold text-zinc-800 dark:text-zinc-200">
                                        {{ $peer->submitted_count }} / {{ $peer->target_count }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                                <div class="h-2 rounded-full {{ $peer->percentage === 100 ? 'bg-emerald-500' : 'bg-[#9b0000] dark:bg-[#f89696]' }}" style="width: {{ $peer->percentage }}%"></div>
                                            </div>
                                            <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 font-mono">{{ $peer->percentage }}%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($peer->status === 'completed')
                                            <flux:badge variant="success" size="sm" class="font-bold">Completed</flux:badge>
                                        @elseif($peer->status === 'in_progress')
                                            <flux:badge variant="info" size="sm" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $peerPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 6: Supervisor Category Progress -->
    @if($activeTab === 'supervisor')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search employee, ID, supervisor, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="selectedRole" aria-label="Filter by employee role" class="w-full">
                            <flux:select.option value="all">All Roles</flux:select.option>
                            <flux:select.option value="faculty">Faculty</flux:select.option>
                            <flux:select.option value="staff">Staff</flux:select.option>
                            <flux:select.option value="program head">Program Head</flux:select.option>
                            <flux:select.option value="department head">Department Head</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-full sm:w-44">
                        <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                            <flux:select.option value="all">All Statuses</flux:select.option>
                            <flux:select.option value="completed">Submitted</flux:select.option>
                            <flux:select.option value="pending">Pending</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>

            <!-- Table -->
            @php $supPaginated = $this->supervisorTrackingPaginated; @endphp
            @if($supPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="arrow-trending-up" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No supervisor evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Employee Name</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Designated Supervisor</th>
                                <th class="px-6 py-3.5">Submission Date</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($supPaginated as $sub)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $sub->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $sub->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge variant="neutral" size="sm" class="font-bold">{{ $sub->role_label }}</flux:badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $sub->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-zinc-800 dark:text-zinc-200">
                                        {{ $sub->supervisor_name }}
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $sub->submitted_at ? $sub->submitted_at->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($sub->submitted)
                                            <flux:badge variant="success" size="sm" class="font-bold">Submitted</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $supPaginated->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 7: Self Category Progress -->
    @if($activeTab === 'self')
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs flex flex-col gap-6">
            <!-- Filter Bar -->
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 items-stretch sm:items-center justify-between">
                <div class="flex-1 w-full sm:max-w-md">
                    <flux:input 
                        icon="magnifying-glass" 
                        wire:model.live.debounce.300ms="search" 
                        placeholder="Search employee, ID, or department..." 
                        clearable
                        class="w-full"
                    />
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <div class="w-full sm:w-48">
                        <flux:select wire:model.live="selectedRole" aria-label="Filter by employee role" class="w-full">
                            <flux:select.option value="all">All Roles</flux:select.option>
                            <flux:select.option value="faculty">Faculty</flux:select.option>
                            <flux:select.option value="staff">Staff</flux:select.option>
                            <flux:select.option value="program head">Program Head</flux:select.option>
                            <flux:select.option value="department head">Department Head</flux:select.option>
                            <flux:select.option value="dean">Dean</flux:select.option>
                        </flux:select>
                    </div>

                    <div class="w-full sm:w-44">
                        <flux:select wire:model.live="selectedStatus" aria-label="Filter by evaluation status" class="w-full">
                            <flux:select.option value="all">All Statuses</flux:select.option>
                            <flux:select.option value="completed">Submitted</flux:select.option>
                            <flux:select.option value="pending">Pending</flux:select.option>
                        </flux:select>
                    </div>
                </div>
            </div>

            @php $selfPaginated = $this->selfTrackingPaginated; @endphp
            @if($selfPaginated->isEmpty())
                <div class="text-center py-10 text-zinc-400">
                    <flux:icon icon="user" class="size-10 mx-auto mb-2 text-zinc-300" />
                    <p class="text-sm font-semibold">No employee self evaluation records found.</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 text-zinc-600 dark:text-zinc-400 font-bold uppercase tracking-wider text-[11px] border-b border-zinc-200 dark:border-zinc-800">
                            <tr>
                                <th class="px-6 py-3.5">Employee Name</th>
                                <th class="px-6 py-3.5">Role</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Submission Date</th>
                                <th class="px-6 py-3.5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($selfPaginated as $self)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-6 py-4 font-bold text-zinc-900 dark:text-zinc-100">
                                        <div>{{ $self->name }}</div>
                                        <div class="text-xs text-zinc-500 font-mono font-normal">{{ $self->employee_number }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:badge variant="neutral" size="sm" class="font-bold">{{ $self->role_label }}</flux:badge>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-xs uppercase bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 px-2 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">
                                            {{ $self->department?->code ?: 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        {{ $self->submitted_at ? $self->submitted_at->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        @if($self->submitted)
                                            <flux:badge variant="success" size="sm" class="font-bold">Submitted</flux:badge>
                                        @else
                                            <flux:badge variant="neutral" size="sm" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>
                    {{ $selfPaginated->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
