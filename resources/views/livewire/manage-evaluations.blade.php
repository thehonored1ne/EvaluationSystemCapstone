<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\EvaluationReferenceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-evaluations-skeleton');
    }

    // 6 Evaluator Role Tabs: 'student', 'dean', 'program_head', 'department_head', 'professor', 'staff'
    public string $activeTab = 'student';

    public string $search = '';

    public string $selectedDepartmentId = '';

    public string $selectedStatus = 'all'; // 'all', 'completed', 'in_progress', 'pending'

    public int $perPage = 10;

    public function getActiveSemesterProperty()
    {
        return Semester::getActive();
    }

    public function getDepartmentsProperty()
    {
        $all = Department::getCachedList();

        if (in_array($this->activeTab, ['student', 'program_head', 'professor'], true)) {
            return $all->where('type', 'academic')->values();
        }

        if (in_array($this->activeTab, ['department_head', 'staff'], true)) {
            return $all->where('type', 'administrative')->values();
        }

        return collect();
    }

    public function selectTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->selectedStatus = 'all';
        $this->selectedDepartmentId = '';
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->search = '';
        $this->selectedStatus = 'all';
        $this->selectedDepartmentId = '';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedDepartmentId(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Helper to paginate in-memory collections safely with fixed 'page' parameter.
     *
     * @param  Collection<int, mixed>  $items
     */
    protected function paginateCollection($items, int $perPage = 10): LengthAwarePaginator
    {
        $page = (int) ($this->getPage() ?: 1);
        $total = $items->count();
        $results = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    // Tab Paginated Properties
    public function getStudentTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'student'
            ? $this->paginateCollection($this->studentTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function getDeanTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'dean'
            ? $this->paginateCollection($this->deanTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function getProgramHeadTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'program_head'
            ? $this->paginateCollection($this->programHeadTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function getDepartmentHeadTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'department_head'
            ? $this->paginateCollection($this->departmentHeadTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function getProfessorTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'professor'
            ? $this->paginateCollection($this->professorTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    public function getStaffTrackingPaginatedProperty(): LengthAwarePaginator
    {
        return $this->activeTab === 'staff'
            ? $this->paginateCollection($this->staffTracking, $this->perPage)
            : new LengthAwarePaginator([], 0, $this->perPage, 1, ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']);
    }

    // ==========================================
    // 1. STUDENT TAB (Evaluates Assigned Teachers)
    // ==========================================
    public function getStudentTrackingProperty()
    {
        if ($this->activeTab !== 'student') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $user = auth()->user();

        // 1. Bulk count distinct evaluations submitted by evaluator users for active semester
        $submittedCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereNotNull('class_id')
            ->whereIn('evaluation_type', ['student', 'upward_student'])
            ->selectRaw('evaluator_id, count(distinct class_id) as submitted_count')
            ->groupBy('evaluator_id')
            ->pluck('submitted_count', 'evaluator_id');

        // 2. Query enrolled students directly via DB table to avoid hydrating thousands of Eloquent models
        $query = DB::table('students')
            ->join('class_student', 'class_student.student_id', '=', 'students.id')
            ->join('classes', 'classes.id', '=', 'class_student.class_id')
            ->where('classes.semester_id', $semId)
            ->leftJoin('users', 'users.student_id', '=', 'students.id')
            ->leftJoin('programs', 'programs.id', '=', 'students.program_id')
            ->leftJoin('departments', 'departments.id', '=', 'programs.department_id')
            ->groupBy(
                'students.id',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.student_number',
                'students.section',
                'users.id',
                'users.email',
                'programs.code',
                'departments.code',
                'departments.name'
            )
            ->select(
                'students.id',
                'students.first_name',
                'students.middle_name',
                'students.last_name',
                'students.student_number',
                'students.section',
                'users.id as user_id',
                'users.email',
                'programs.code as program_code',
                'departments.code as department_code',
                'departments.name as department_name',
                DB::raw('count(distinct classes.id) as enrolled_subjects')
            );

        // Scope by user role & department
        if ($user?->hasRole('program head') && $user->employee_id) {
            $deptId = DB::table('employees')->where('id', $user->employee_id)->value('department_id');
            if ($deptId) {
                $query->where('programs.department_id', $deptId);
            }
        } elseif ($user?->hasRole('dean') && $user->employee_id) {
            $userDeptId = DB::table('employees')->where('id', $user->employee_id)->value('department_id');
            $deptId = $this->selectedDepartmentId ?: $userDeptId;
            if ($deptId) {
                $query->where('programs.department_id', $deptId);
            }
        } elseif ($user?->hasRole('admin')) {
            if ($this->selectedDepartmentId) {
                $query->where('programs.department_id', $this->selectedDepartmentId);
            }
        }

        $search = trim($this->search);

        return $query->get()->map(function ($stu) use ($sem, $semId, $submittedCountMap) {
            $userId = $stu->user_id;
            $enrolled = (int) $stu->enrolled_subjects;
            $completed = $userId ? (int) ($submittedCountMap[$userId] ?? 0) : 0;
            $percentage = $enrolled > 0 ? min(100, (int) round(($completed / $enrolled) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $rawRefId = ($percentage === 100 && $userId) ? EvaluationReferenceService::generate($userId, $semId, $sem) : null;
            $formattedRefId = $rawRefId ? EvaluationReferenceService::format($rawRefId) : null;

            $fullName = trim("{$stu->first_name} ".($stu->middle_name ? "{$stu->middle_name} " : '')."{$stu->last_name}");

            return (object) [
                'id' => $stu->id,
                'user_id' => $userId,
                'name' => $fullName,
                'student_number' => $stu->student_number,
                'email' => $stu->email ?? '—',
                'section' => $stu->section ?: '—',
                'department' => $stu->department_code ?: ($stu->department_name ?: '—'),
                'program' => $stu->program_code ?: '—',
                'enrolled_subjects' => $enrolled,
                'completed_evaluations' => $completed,
                'percentage' => $percentage,
                'status' => $status,
                'reference_id' => $formattedRefId,
            ];
        })->filter(function ($item) use ($search) {
            // Apply Status Filter
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }

            // Apply Search Filter
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->student_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->section), $term)
                    || str_contains(mb_strtolower($item->program), $term)
                    || ($item->reference_id && str_contains(mb_strtolower($item->reference_id), $term));
            }

            return true;
        })->values();
    }

    // ==========================================
    // 2. DEAN TAB (Evaluates Self + Program Heads)
    // ==========================================
    public function getDeanTrackingProperty()
    {
        if ($this->activeTab !== 'dean') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $search = trim($this->search);

        // Expected targets: 1 (Self) + Total Active Faculty + Total Active Program Heads
        $facultyCount = DB::table('employees')->where('role', 'faculty')->where('status', 'active')->count();
        $phCount = DB::table('employees')->where('role', 'program head')->where('status', 'active')->count();
        $targetCount = 1 + $facultyCount + $phCount;

        // Preload submitted evaluations per evaluator
        $evalCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereIn('evaluation_type', ['self', 'downward', 'dean', 'peer'])
            ->selectRaw('evaluator_id, count(distinct evaluatee_id) as eval_count')
            ->groupBy('evaluator_id')
            ->pluck('eval_count', 'evaluator_id');

        $query = DB::table('employees')
            ->where('employees.role', 'dean')
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.id as user_id',
                'users.email'
            );

        if ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($targetCount, $evalCountMap) {
            $userId = $emp->user_id;
            $completed = $userId ? (int) ($evalCountMap[$userId] ?? 0) : 0;
            $percentage = $targetCount > 0 ? min(100, (int) round(($completed / $targetCount) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $fullName = trim("{$emp->first_name} {$emp->last_name}");

            return (object) [
                'id' => $emp->id,
                'user_id' => $userId,
                'name' => $fullName,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email ?? '—',
                'department' => $emp->department_name ?: 'College Dean',
                'department_code' => $emp->department_code ?: 'DEAN',
                'target_count' => $targetCount,
                'completed_count' => $completed,
                'percentage' => $percentage,
                'status' => $status,
            ];
        })->filter(function ($item) use ($search) {
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->employee_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->department), $term);
            }

            return true;
        })->values();
    }

    // ==========================================
    // 3. PROGRAM HEAD TAB (Evaluates Self + Dept Faculty)
    // ==========================================
    public function getProgramHeadTrackingProperty()
    {
        if ($this->activeTab !== 'program_head') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $search = trim($this->search);

        // Preload department active faculty count map
        $deptFacultyCountMap = DB::table('employees')
            ->where('role', 'faculty')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        // Preload submitted evaluations per evaluator
        $evalCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereIn('evaluation_type', ['self', 'downward', 'program_head', 'peer', 'upward_employee'])
            ->selectRaw('evaluator_id, count(distinct evaluatee_id) as eval_count')
            ->groupBy('evaluator_id')
            ->pluck('eval_count', 'evaluator_id');

        $query = DB::table('employees')
            ->where('employees.role', 'program head')
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.id as user_id',
                'users.email'
            );

        if ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($deptFacultyCountMap, $evalCountMap) {
            $userId = $emp->user_id;
            $deptFacCount = (int) ($deptFacultyCountMap[$emp->department_id] ?? 0);
            $targetCount = 1 + $deptFacCount + 1; // 1 (Self) + Dept Faculty + 1 (Dean)

            $completed = $userId ? (int) ($evalCountMap[$userId] ?? 0) : 0;
            $percentage = $targetCount > 0 ? min(100, (int) round(($completed / $targetCount) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $fullName = trim("{$emp->first_name} {$emp->last_name}");

            return (object) [
                'id' => $emp->id,
                'user_id' => $userId,
                'name' => $fullName,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email ?? '—',
                'department' => $emp->department_name ?? '—',
                'department_code' => $emp->department_code ?? '—',
                'target_count' => $targetCount,
                'completed_count' => $completed,
                'percentage' => $percentage,
                'status' => $status,
            ];
        })->filter(function ($item) use ($search) {
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->employee_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->department), $term);
            }

            return true;
        })->values();
    }

    // ==========================================
    // 4. DEPARTMENT HEAD TAB (Evaluates Self + Dept Staff)
    // ==========================================
    public function getDepartmentHeadTrackingProperty()
    {
        if ($this->activeTab !== 'department_head') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $search = trim($this->search);

        // Preload department active staff count map
        $deptStaffCountMap = DB::table('employees')
            ->where('role', 'staff')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        // Preload submitted evaluations per evaluator
        $evalCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereIn('evaluation_type', ['self', 'downward', 'department_head', 'peer', 'upward_employee', 'dean', 'superior'])
            ->selectRaw('evaluator_id, count(distinct evaluatee_id) as eval_count')
            ->groupBy('evaluator_id')
            ->pluck('eval_count', 'evaluator_id');

        $query = DB::table('employees')
            ->where('employees.role', 'department head')
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.id as user_id',
                'users.email'
            );

        if ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($deptStaffCountMap, $evalCountMap) {
            $userId = $emp->user_id;
            $deptStaffCount = (int) ($deptStaffCountMap[$emp->department_id] ?? 0);
            $targetCount = 1 + $deptStaffCount + 1; // 1 (Self) + Dept Staff + 1 (Dean)

            $completed = $userId ? (int) ($evalCountMap[$userId] ?? 0) : 0;
            $percentage = $targetCount > 0 ? min(100, (int) round(($completed / $targetCount) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $fullName = trim("{$emp->first_name} {$emp->last_name}");

            return (object) [
                'id' => $emp->id,
                'user_id' => $userId,
                'name' => $fullName,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email ?? '—',
                'department' => $emp->department_name ?? '—',
                'department_code' => $emp->department_code ?? '—',
                'target_count' => $targetCount,
                'completed_count' => $completed,
                'percentage' => $percentage,
                'status' => $status,
            ];
        })->filter(function ($item) use ($search) {
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->employee_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->department), $term);
            }

            return true;
        })->values();
    }

    // ==========================================
    // 5. PROFESSOR TAB (Faculty: Evaluates Self + Faculty Peers)
    // ==========================================
    public function getProfessorTrackingProperty()
    {
        if ($this->activeTab !== 'professor') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $search = trim($this->search);

        // Preload department active faculty count map
        $deptFacultyCountMap = DB::table('employees')
            ->where('role', 'faculty')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        // Preload department active program head count map
        $deptPhCountMap = DB::table('employees')
            ->where('role', 'program head')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        // Preload submitted evaluations per evaluator
        $evalCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereIn('evaluation_type', ['self', 'peer', 'upward_employee', 'superior'])
            ->selectRaw('evaluator_id, count(distinct evaluatee_id) as eval_count')
            ->groupBy('evaluator_id')
            ->pluck('eval_count', 'evaluator_id');

        $query = DB::table('employees')
            ->where('employees.role', 'faculty')
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.id as user_id',
                'users.email'
            );

        if ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($deptFacultyCountMap, $deptPhCountMap, $evalCountMap) {
            $userId = $emp->user_id;
            $deptFacCount = (int) ($deptFacultyCountMap[$emp->department_id] ?? 0);
            $deptPhCount = (int) ($deptPhCountMap[$emp->department_id] ?? 0);
            $peerTarget = max(0, $deptFacCount - 1);
            $targetCount = 1 + $peerTarget + $deptPhCount; // 1 (Self) + Dept Faculty Peers + Dept Program Head(s)

            $completed = $userId ? (int) ($evalCountMap[$userId] ?? 0) : 0;
            $percentage = $targetCount > 0 ? min(100, (int) round(($completed / $targetCount) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $fullName = trim("{$emp->first_name} {$emp->last_name}");

            return (object) [
                'id' => $emp->id,
                'user_id' => $userId,
                'name' => $fullName,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email ?? '—',
                'department' => $emp->department_name ?? '—',
                'department_code' => $emp->department_code ?? '—',
                'target_count' => $targetCount,
                'completed_count' => $completed,
                'percentage' => $percentage,
                'status' => $status,
            ];
        })->filter(function ($item) use ($search) {
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->employee_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->department), $term);
            }

            return true;
        })->values();
    }

    // ==========================================
    // 6. STAFF TAB (Evaluates Self + Staff Peers + Dept Head)
    // ==========================================
    public function getStaffTrackingProperty()
    {
        if ($this->activeTab !== 'staff') {
            return collect();
        }

        $sem = $this->activeSemester;
        if (! $sem) {
            return collect();
        }

        $semId = $sem->id;
        $search = trim($this->search);

        // Preload department active staff count map
        $deptStaffCountMap = DB::table('employees')
            ->where('role', 'staff')
            ->where('status', 'active')
            ->selectRaw('department_id, count(*) as count')
            ->groupBy('department_id')
            ->pluck('count', 'department_id');

        // Preload submitted evaluations per evaluator
        $evalCountMap = DB::table('evaluations')
            ->where('semester_id', $semId)
            ->whereIn('evaluation_type', ['self', 'peer', 'upward_employee', 'superior'])
            ->selectRaw('evaluator_id, count(distinct evaluatee_id) as eval_count')
            ->groupBy('evaluator_id')
            ->pluck('eval_count', 'evaluator_id');

        $query = DB::table('employees')
            ->where('employees.role', 'staff')
            ->where('employees.status', 'active')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users', 'users.employee_id', '=', 'employees.id')
            ->select(
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.employee_number',
                'employees.department_id',
                'departments.name as department_name',
                'departments.code as department_code',
                'users.id as user_id',
                'users.email'
            );

        if ($this->selectedDepartmentId) {
            $query->where('employees.department_id', $this->selectedDepartmentId);
        }

        return $query->get()->map(function ($emp) use ($deptStaffCountMap, $evalCountMap) {
            $userId = $emp->user_id;
            $deptStaffCount = (int) ($deptStaffCountMap[$emp->department_id] ?? 0);
            $peerTarget = max(0, $deptStaffCount - 1);
            $targetCount = 1 + $peerTarget + 1; // 1 (Self) + Staff Peers + 1 (Department Head)

            $completed = $userId ? (int) ($evalCountMap[$userId] ?? 0) : 0;
            $percentage = $targetCount > 0 ? min(100, (int) round(($completed / $targetCount) * 100)) : 0;
            $status = ($percentage === 100) ? 'completed' : ($completed > 0 ? 'in_progress' : 'pending');

            $fullName = trim("{$emp->first_name} {$emp->last_name}");

            return (object) [
                'id' => $emp->id,
                'user_id' => $userId,
                'name' => $fullName,
                'employee_number' => $emp->employee_number,
                'email' => $emp->email ?? '—',
                'department' => $emp->department_name ?? '—',
                'department_code' => $emp->department_code ?? '—',
                'target_count' => $targetCount,
                'completed_count' => $completed,
                'percentage' => $percentage,
                'status' => $status,
            ];
        })->filter(function ($item) use ($search) {
            if ($this->selectedStatus !== 'all' && $item->status !== $this->selectedStatus) {
                return false;
            }
            if ($search !== '') {
                $term = mb_strtolower($search);

                return str_contains(mb_strtolower($item->name), $term)
                    || str_contains(mb_strtolower($item->employee_number), $term)
                    || str_contains(mb_strtolower($item->email), $term)
                    || str_contains(mb_strtolower($item->department), $term);
            }

            return true;
        })->values();
    }

    // ==========================================
    // SUMMARY STATS KPI CALCULATIONS
    // ==========================================
    public function getSummaryStatsProperty(): array
    {
        $semId = $this->activeSemester?->id;
        if (! $semId) {
            return [
                'totalSubmissions' => 0,
                'studentSubmissions' => 0,
                'employeeSubmissions' => 0,
                'studentDone' => 0,
                'studentTotal' => 0,
                'studentPct' => 0,
                'employeeDone' => 0,
                'employeeTotal' => 0,
                'employeePct' => 0,
                'pendingEvaluations' => 0,
            ];
        }

        return Cache::remember("manage_eval_summary_stats_{$semId}", 30, function () use ($semId) {
            // 1. Evaluations aggregate query
            $evalAgg = DB::table('evaluations')
                ->where('semester_id', $semId)
                ->selectRaw("
                    count(*) as total_submissions,
                    count(case when evaluation_type in ('student', 'upward_student') or class_id is not null then 1 end) as student_submissions,
                    count(case when (evaluation_type not in ('student', 'upward_student') and class_id is null) then 1 end) as employee_submissions
                ")
                ->first();

            $totalSubmissions = (int) ($evalAgg->total_submissions ?? 0);
            $studentSubmissions = (int) ($evalAgg->student_submissions ?? 0);
            $employeeSubmissions = (int) ($evalAgg->employee_submissions ?? 0);

            // 2. Student Target (Total enrolled subject slots)
            $studentEnrolledTarget = (int) DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->where('classes.semester_id', $semId)
                ->count();

            $studentPct = $studentEnrolledTarget > 0 ? min(100, (int) round(($studentSubmissions / $studentEnrolledTarget) * 100)) : 0;

            // 3. Employee Target (Total expected evaluations across all active employees)
            $deptFacultyCountMap = DB::table('employees')->where('role', 'faculty')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
            $deptPhCountMap = DB::table('employees')->where('role', 'program head')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
            $deptStaffCountMap = DB::table('employees')->where('role', 'staff')->where('status', 'active')->selectRaw('department_id, count(*) as count')->groupBy('department_id')->pluck('count', 'department_id');
            $facultyTotalCount = DB::table('employees')->where('role', 'faculty')->where('status', 'active')->count();
            $phTotalCount = DB::table('employees')->where('role', 'program head')->where('status', 'active')->count();

            $activeEmployees = DB::table('employees')
                ->where('status', 'active')
                ->where('role', '!=', 'admin')
                ->select('id', 'role', 'department_id')
                ->get();

            $totalEmployeeTarget = 0;
            foreach ($activeEmployees as $emp) {
                if ($emp->role === 'faculty') {
                    $facCount = (int) ($deptFacultyCountMap[$emp->department_id] ?? 0);
                    $phCount = (int) ($deptPhCountMap[$emp->department_id] ?? 0);
                    $totalEmployeeTarget += (1 + max(0, $facCount - 1) + $phCount);
                } elseif ($emp->role === 'program head') {
                    $facCount = (int) ($deptFacultyCountMap[$emp->department_id] ?? 0);
                    $totalEmployeeTarget += (1 + $facCount + 1);
                } elseif ($emp->role === 'department head') {
                    $staffCount = (int) ($deptStaffCountMap[$emp->department_id] ?? 0);
                    $totalEmployeeTarget += (1 + $staffCount + 1);
                } elseif ($emp->role === 'dean') {
                    $totalEmployeeTarget += (1 + $facultyTotalCount + $phTotalCount);
                } elseif ($emp->role === 'staff') {
                    $staffCount = (int) ($deptStaffCountMap[$emp->department_id] ?? 0);
                    $totalEmployeeTarget += (1 + max(0, $staffCount - 1) + 1);
                }
            }

            $employeePct = $totalEmployeeTarget > 0 ? min(100, (int) round(($employeeSubmissions / $totalEmployeeTarget) * 100)) : 0;

            // 4. Pending evaluations count
            $pendingStudent = max(0, $studentEnrolledTarget - $studentSubmissions);
            $pendingEmployee = max(0, $totalEmployeeTarget - $employeeSubmissions);
            $totalPending = $pendingStudent + $pendingEmployee;

            return [
                'totalSubmissions' => $totalSubmissions,
                'studentSubmissions' => $studentSubmissions,
                'employeeSubmissions' => $employeeSubmissions,
                'studentDone' => $studentSubmissions,
                'studentTotal' => $studentEnrolledTarget,
                'studentPct' => $studentPct,
                'employeeDone' => $employeeSubmissions,
                'employeeTotal' => $totalEmployeeTarget,
                'employeePct' => $employeePct,
                'pendingEvaluations' => $totalPending,
            ];
        });
    }

    // Role Counts for Tab Badges
    public function getTabCountsProperty(): array
    {
        $semId = $this->activeSemester?->id;
        if (! $semId) {
            return [
                'student' => 0,
                'dean' => 0,
                'program_head' => 0,
                'department_head' => 0,
                'professor' => 0,
                'staff' => 0,
            ];
        }

        return Cache::remember("manage_eval_tab_counts_{$semId}", 30, function () use ($semId) {
            // Consolidated single query for employee tab counts
            $empCounts = DB::table('employees')
                ->where('status', 'active')
                ->selectRaw("
                    count(case when role = 'dean' then 1 end) as dean_count,
                    count(case when role = 'program head' then 1 end) as program_head_count,
                    count(case when role = 'department head' then 1 end) as department_head_count,
                    count(case when role = 'faculty' then 1 end) as faculty_count,
                    count(case when role = 'staff' then 1 end) as staff_count
                ")
                ->first();

            $studentCount = DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->where('classes.semester_id', $semId)
                ->distinct()
                ->count('class_student.student_id');

            return [
                'student' => (int) $studentCount,
                'dean' => (int) ($empCounts->dean_count ?? 0),
                'program_head' => (int) ($empCounts->program_head_count ?? 0),
                'department_head' => (int) ($empCounts->department_head_count ?? 0),
                'professor' => (int) ($empCounts->faculty_count ?? 0),
                'staff' => (int) ($empCounts->staff_count ?? 0),
            ];
        });
    }

    public function sendReminderToast(): void
    {
        $user = auth()->user();

        Artisan::call('evaluations:send-reminders', ['--force' => true]);

        if ($user && function_exists('activity')) {
            activity('evaluations')
                ->causedBy($user)
                ->log('Broadcasted evaluation completion reminders across all pending evaluators via Completion Tracking.');
        }

        Flux::toast(
            heading: 'Reminders Broadcasted',
            text: 'Evaluation submission reminders have been processed and broadcasted to all pending evaluators.',
            variant: 'success'
        );
    }
}; ?>

<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Banner & Action Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Completion Tracking</flux:heading>
        </div>

        <div class="flex items-center gap-3">
            <flux:button 
                variant="primary" 
                icon="bell-alert" 
                wire:click="sendReminderToast" 
                class="bg-[#9b0000] hover:bg-[#800000] text-white dark:bg-[#9b0000] dark:hover:bg-[#800000] font-bold shadow-xs cursor-pointer"
            >
                Send Reminders
            </flux:button>
        </div>
    </div>

    <!-- Top 4 Summary Stat Cards (Consistent Hierarchy & Clean Borderless Style) -->
    @php
        $stats = $this->summaryStats;
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 w-full">
        <!-- Card 1: Total Evaluation Submissions Received -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5 flex flex-col">
            <div class="h-5 flex items-center justify-between">
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">
                    Total Submissions Received
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-3 flex-wrap">
                <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                    <x-odometer :value="$stats['totalSubmissions']" />
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                    All Roles
                </span>
            </div>
            <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs text-zinc-500 dark:text-zinc-400">
                <span class="font-semibold text-zinc-700 dark:text-zinc-300 tabular-nums">
                    {{ number_format($stats['studentSubmissions']) }}
                </span>
                <span>student</span>
                <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                <span class="font-semibold text-zinc-700 dark:text-zinc-300 tabular-nums">
                    {{ number_format($stats['employeeSubmissions']) }}
                </span>
                <span>employee forms</span>
            </div>
        </div>

        <!-- Card 2: Student Evaluation Progress -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5 flex flex-col">
            <div class="h-5 flex items-center justify-between">
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">
                    Student Evaluation Progress
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-3 flex-wrap">
                <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                    <x-odometer :value="$stats['studentPct']" suffix="%" />
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $stats['studentPct'] >= 80 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : ($stats['studentPct'] >= 50 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800') }}">
                    {{ $stats['studentPct'] >= 80 ? 'On Track' : 'In Progress' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs text-zinc-500 dark:text-zinc-400">
                <span class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                    {{ number_format($stats['studentDone']) }}
                </span>
                <span>submitted of</span>
                <span class="font-semibold text-zinc-700 dark:text-zinc-300 tabular-nums">
                    {{ number_format($stats['studentTotal']) }}
                </span>
                <span>expected forms</span>
            </div>
        </div>

        <!-- Card 3: Employee Evaluation Progress -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5 flex flex-col">
            <div class="h-5 flex items-center justify-between">
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">
                    Employee Evaluation Progress
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-3 flex-wrap">
                <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                    <x-odometer :value="$stats['employeePct']" suffix="%" />
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $stats['employeePct'] >= 80 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : ($stats['employeePct'] >= 50 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-800') }}">
                    {{ $stats['employeePct'] >= 80 ? 'On Track' : 'In Progress' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs text-zinc-500 dark:text-zinc-400">
                <span class="font-semibold text-emerald-700 dark:text-emerald-400 tabular-nums">
                    {{ number_format($stats['employeeDone']) }}
                </span>
                <span>submitted of</span>
                <span class="font-semibold text-zinc-700 dark:text-zinc-300 tabular-nums">
                    {{ number_format($stats['employeeTotal']) }}
                </span>
                <span>expected forms</span>
            </div>
        </div>

        <!-- Card 4: Pending Evaluation Forms -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5 flex flex-col">
            <div class="h-5 flex items-center justify-between">
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 font-bold uppercase tracking-wider block">
                    Pending Evaluation Forms
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-3 flex-wrap">
                <span class="text-3xl font-extrabold text-zinc-900 dark:text-zinc-100 tracking-tight tabular-nums">
                    <x-odometer :value="$stats['pendingEvaluations']" />
                </span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wider {{ $stats['pendingEvaluations'] > 0 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' }}">
                    {{ $stats['pendingEvaluations'] > 0 ? 'Awaiting Action' : 'All Complete' }}
                </span>
            </div>
            <div class="flex items-center gap-1.5 mt-2.5 flex-wrap text-xs text-zinc-500 dark:text-zinc-400">
                <span class="font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
                    {{ number_format(max(0, $stats['studentTotal'] - $stats['studentDone'])) }}
                </span>
                <span>student</span>
                <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                <span class="font-semibold text-amber-700 dark:text-amber-400 tabular-nums">
                    {{ number_format(max(0, $stats['employeeTotal'] - $stats['employeeDone'])) }}
                </span>
                <span>employee forms remaining</span>
            </div>
        </div>
    </div>

    <!-- 6 Evaluator Role Tabs Navigation -->
    @php
        $tabCounts = $this->tabCounts;
    @endphp
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-1.5 sm:p-2 shadow-xs grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 w-full">
        <!-- 1. Student -->
        <button 
            type="button"
            wire:click="selectTab('student')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'student' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="academic-cap" class="size-4" />
            <span>Student</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'student' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['student'] }}
            </span>
        </button>

        <!-- 2. Dean -->
        <button 
            type="button"
            wire:click="selectTab('dean')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'dean' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="briefcase" class="size-4" />
            <span>Dean</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'dean' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['dean'] }}
            </span>
        </button>

        <!-- 3. Program Head -->
        <button 
            type="button"
            wire:click="selectTab('program_head')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'program_head' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="building-office-2" class="size-4" />
            <span>Program Head</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'program_head' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['program_head'] }}
            </span>
        </button>

        <!-- 4. Department Head -->
        <button 
            type="button"
            wire:click="selectTab('department_head')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'department_head' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="building-office" class="size-4" />
            <span>Dept Head</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'department_head' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['department_head'] }}
            </span>
        </button>

        <!-- 5. Professor -->
        <button 
            type="button"
            wire:click="selectTab('professor')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'professor' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="user-group" class="size-4" />
            <span>Professor</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'professor' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['professor'] }}
            </span>
        </button>

        <!-- 6. Staff -->
        <button 
            type="button"
            wire:click="selectTab('staff')" 
            class="w-full px-3 py-2.5 rounded-lg text-xs font-bold transition-colors flex items-center justify-center gap-2 cursor-pointer text-center whitespace-nowrap {{ $activeTab === 'staff' ? 'bg-[#9b0000] text-white dark:bg-red-950/40 dark:text-[#e07a7a] dark:border dark:border-red-900/40 shadow-xs' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800/60' }}"
        >
            <flux:icon icon="identification" class="size-4" />
            <span>Staff</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === 'staff' ? 'bg-white/20 text-white dark:bg-red-900/50 dark:text-[#e07a7a]' : 'bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300' }}">
                {{ $tabCounts['staff'] }}
            </span>
        </button>
    </div>

    <!-- Filters Bar (Search, Department, Status, Per Page) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Search -->
            <div class="flex-1 min-w-[240px]">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search by name, ID number, email, section..." 
                    icon="magnifying-glass" 
                    clearable
                />
            </div>

            <!-- Department Filter (Hidden for Dean tab) -->
            @if($activeTab !== 'dean' && (auth()->user()?->hasRole('admin') || auth()->user()?->hasRole('dean')))
                <div class="w-full sm:w-56">
                    <flux:select wire:model.live="selectedDepartmentId">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($this->departments as $dept)
                            <flux:select.option value="{{ $dept->id }}">
                                {{ $dept->code }} — {{ $dept->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <!-- Status Filter -->
            <div class="w-full sm:w-44">
                <flux:select wire:model.live="selectedStatus">
                    <flux:select.option value="all">All Statuses</flux:select.option>
                    <flux:select.option value="completed">Completed (100%)</flux:select.option>
                    <flux:select.option value="in_progress">In Progress (1-99%)</flux:select.option>
                    <flux:select.option value="pending">Pending (0%)</flux:select.option>
                </flux:select>
            </div>
        </div>

        <!-- Per Page Control -->
        <div class="flex items-center gap-2 self-end md:self-auto">
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Rows:</span>
            <flux:select wire:model.live="perPage" class="w-20">
                <flux:select.option value="10">10</flux:select.option>
                <flux:select.option value="25">25</flux:select.option>
                <flux:select.option value="50">50</flux:select.option>
                <flux:select.option value="100">100</flux:select.option>
            </flux:select>
        </div>
    </div>

    <!-- Tab Content & Tables -->
    <div class="w-full">
        <!-- ========================================== -->
        <!-- 1. STUDENT TAB -->
        <!-- Columns: Full Name, Section, Subjects, Completion Rate, Status, Reference ID -->
        <!-- ========================================== -->
        @if($activeTab === 'student')
            @php
                $students = $this->studentTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Section</th>
                                <th class="px-6 py-3.5">Subjects</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                                <th class="px-6 py-3.5 text-right">Reference ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($students as $stu)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <!-- Full Name -->
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $stu->name }}</span>
                                    </td>

                                    <!-- Section -->
                                    <td class="px-6 py-4">
                                        <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $stu->section }}</span>
                                    </td>

                                    <!-- Subjects -->
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                            {{ $stu->enrolled_subjects }} {{ \Illuminate\Support\Str::plural('Subject', $stu->enrolled_subjects) }}
                                        </span>
                                    </td>

                                    <!-- Progress -->
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $stu->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($stu->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $stu->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $stu->completed_evaluations }}/{{ $stu->enrolled_subjects }})
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 text-center">
                                        @if($stu->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($stu->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>

                                    <!-- Reference ID -->
                                    <td class="px-6 py-4 text-right font-mono">
                                        @if($stu->reference_id)
                                            <flux:badge size="sm" color="emerald" class="font-mono tracking-wider font-bold select-all">
                                                {{ $stu->reference_id }}
                                            </flux:badge>
                                        @else
                                            <span class="text-xs text-zinc-400 dark:text-zinc-600">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="academic-cap" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No student records found</p>
                                            <p class="text-xs">Try adjusting your search criteria or active filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($students->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $students->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 2. DEAN TAB -->
        <!-- Columns: Full Name, Department, Completion Rate, Status -->
        <!-- ========================================== -->
        @if($activeTab === 'dean')
            @php
                $deans = $this->deanTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($deans as $dean)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $dean->name }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $dean->department }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $dean->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($dean->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $dean->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $dean->completed_count }}/{{ $dean->target_count }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($dean->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($dean->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="briefcase" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No dean records found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($deans->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $deans->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 3. PROGRAM HEAD TAB -->
        <!-- Columns: Full Name, Department, Completion Rate, Status -->
        <!-- ========================================== -->
        @if($activeTab === 'program_head')
            @php
                $phs = $this->programHeadTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($phs as $ph)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $ph->name }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $ph->department }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $ph->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($ph->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $ph->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $ph->completed_count }}/{{ $ph->target_count }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($ph->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($ph->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="building-office-2" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No program head records found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($phs->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $phs->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 4. DEPARTMENT HEAD TAB -->
        <!-- Columns: Full Name, Department, Completion Rate, Status -->
        <!-- ========================================== -->
        @if($activeTab === 'department_head')
            @php
                $dhs = $this->departmentHeadTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($dhs as $dh)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $dh->name }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $dh->department }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $dh->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($dh->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $dh->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $dh->completed_count }}/{{ $dh->target_count }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($dh->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($dh->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="building-office" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No department head records found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($dhs->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $dhs->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 5. PROFESSOR TAB (Faculty) -->
        <!-- Columns: Full Name, Department, Completion Rate, Status -->
        <!-- ========================================== -->
        @if($activeTab === 'professor')
            @php
                $profs = $this->professorTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($profs as $prof)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $prof->name }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $prof->department }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $prof->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($prof->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $prof->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $prof->completed_count }}/{{ $prof->target_count }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($prof->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($prof->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="user-group" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No professor records found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($profs->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $profs->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- ========================================== -->
        <!-- 6. STAFF TAB -->
        <!-- Columns: Full Name, Department, Completion Rate, Status -->
        <!-- ========================================== -->
        @if($activeTab === 'staff')
            @php
                $staffs = $this->staffTrackingPaginated;
            @endphp
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3.5">Full Name</th>
                                <th class="px-6 py-3.5">Department</th>
                                <th class="px-6 py-3.5">Progress</th>
                                <th class="px-6 py-3.5 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-900 dark:text-zinc-100">
                            @forelse($staffs as $staff)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 transition-colors">
                                    <td class="px-6 py-4 font-medium">
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $staff->name }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $staff->department }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="font-bold font-mono text-sm {{ $staff->percentage === 100 ? 'text-emerald-600 dark:text-emerald-400' : ($staff->percentage > 0 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500') }}">
                                                {{ $staff->percentage }}%
                                            </span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                                ({{ $staff->completed_count }}/{{ $staff->target_count }})
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        @if($staff->status === 'completed')
                                            <flux:badge size="sm" color="emerald" class="font-bold">Completed</flux:badge>
                                        @elseif($staff->status === 'in_progress')
                                            <flux:badge size="sm" color="blue" class="font-bold">In Progress</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" class="font-bold">Pending</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon icon="identification" class="size-8 text-zinc-400" />
                                            <p class="font-bold">No staff records found</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($staffs->hasPages())
                    <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                        {{ $staffs->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
