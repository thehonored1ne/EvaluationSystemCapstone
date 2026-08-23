<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendEvaluationDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evaluations:send-reminders {--force : Force send reminders regardless of schedule milestone}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks active semester evaluation deadlines and dispatches reminders to users with pending evaluations.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sem = Semester::where('is_active', true)->first();

        if (! $sem) {
            $this->warn('No active semester found.');

            return self::SUCCESS;
        }

        if (! $sem->isEvaluationWindowActive()) {
            $this->info('Evaluations are currently closed for the active semester.');

            return self::SUCCESS;
        }

        $now = now();
        $endsAt = $sem->evaluation_ends_at;
        $isForced = (bool) $this->option('force');

        $tier = 'manual';
        $hoursLeft = null;

        if ($endsAt) {
            $hoursLeft = (int) $now->diffInHours($endsAt, false);

            if ($hoursLeft < 0) {
                $this->warn('The evaluation period deadline has already passed.');

                return self::SUCCESS;
            }

            $tier = match (true) {
                $hoursLeft <= 6 => '6h',
                $hoursLeft <= 24 => '24h',
                $hoursLeft <= 72 => '3d',
                $hoursLeft <= 168 => '7d',
                default => null,
            };
        }

        if (! $tier && ! $isForced) {
            $this->info("Current remaining window ({$hoursLeft}h) does not match a scheduled reminder milestone (7d, 3d, 24h, 6h). Use --force to broadcast anyway.");

            return self::SUCCESS;
        }

        $tier = $tier ?? 'manual';
        $this->info("Processing evaluation deadline reminders (Tier: {$tier}, Semester: {$sem->academicYear?->name} - {$sem->name})...");

        // 1. Preload all evaluations for this semester into a fast lookup set
        $evalRows = DB::table('evaluations')
            ->where('semester_id', $sem->id)
            ->select('evaluator_id', 'evaluatee_id', 'class_id', 'evaluation_type')
            ->get();

        $evalSet = [];
        foreach ($evalRows as $er) {
            $evalSet[$er->evaluator_id.'_'.$er->evaluatee_id.'_'.($er->class_id ?? '').'_'.$er->evaluation_type] = true;
        }

        // 2. Preload student classes with teacher user mapping via lightweight DB queries (avoids AsPivot memory explosion)
        $teacherUserMap = DB::table('classes')
            ->join('users', 'users.employee_id', '=', 'classes.teacher_id')
            ->where('classes.semester_id', $sem->id)
            ->whereNotNull('classes.teacher_id')
            ->pluck('users.id', 'classes.id'); // class_id => teacher_user_id

        $studentEnrollments = DB::table('class_student')
            ->join('classes', 'classes.id', '=', 'class_student.class_id')
            ->where('classes.semester_id', $sem->id)
            ->select('class_student.student_id', 'class_student.class_id')
            ->get();

        $studentClassesMap = [];
        foreach ($studentEnrollments as $enr) {
            if (isset($teacherUserMap[$enr->class_id])) {
                $studentClassesMap[$enr->student_id][] = [
                    'class_id' => $enr->class_id,
                    'teacher_user_id' => $teacherUserMap[$enr->class_id],
                ];
            }
        }

        // 3. Preload active employees with user mapping
        $activeEmployees = DB::table('employees')
            ->join('users', 'users.employee_id', '=', 'employees.id')
            ->where('employees.status', 'active')
            ->select('employees.id', 'employees.department_id', 'employees.role', 'users.id as user_id')
            ->get();

        $deptFacultyMap = [];
        $deptStaffMap = [];
        $programHeadsList = [];

        foreach ($activeEmployees as $emp) {
            if ($emp->role === 'faculty' && $emp->department_id) {
                $deptFacultyMap[$emp->department_id][] = $emp;
            } elseif ($emp->role === 'staff' && $emp->department_id) {
                $deptStaffMap[$emp->department_id][] = $emp;
            } elseif ($emp->role === 'program head') {
                $programHeadsList[] = $emp;
            }
        }

        // 4. Identify all users with pending evaluations using memory-friendly cursor/chunks
        $notifiedCount = 0;
        $roleBreakdown = [
            'student' => 0,
            'faculty' => 0,
            'program head' => 0,
            'department head' => 0,
            'dean' => 0,
            'staff' => 0,
        ];

        User::where('is_active', true)
            ->with(['employee:id,department_id,role', 'roles:id,name'])
            ->select(['id', 'student_id', 'employee_id', 'is_active'])
            ->chunk(500, function ($activeUsers) use (
                $studentClassesMap,
                $deptFacultyMap,
                $deptStaffMap,
                $programHeadsList,
                $evalSet,
                &$notifiedCount,
                &$roleBreakdown
            ) {
                foreach ($activeUsers as $user) {
                    $pending = 0;

                    if ($user->hasRole('student') && $user->student_id) {
                        $clsList = $studentClassesMap[$user->student_id] ?? [];
                        foreach ($clsList as $cItem) {
                            $key = $user->id.'_'.$cItem['teacher_user_id'].'_'.$cItem['class_id'].'_upward_student';
                            if (! isset($evalSet[$key])) {
                                $found = false;
                                foreach (['upward_student', 'student', ''] as $t) {
                                    if (isset($evalSet[$user->id.'_'.$cItem['teacher_user_id'].'_'.$cItem['class_id'].'_'.$t])) {
                                        $found = true;
                                        break;
                                    }
                                }
                                if (! $found) {
                                    $pending++;
                                }
                            }
                        }
                    }

                    if ($user->hasRole('faculty') && $user->employee) {
                        $emp = $user->employee;
                        if (! isset($evalSet[$user->id.'_'.$user->id.'__self'])) {
                            $pending++;
                        }
                        if ($emp->department_id) {
                            $peers = $deptFacultyMap[$emp->department_id] ?? [];
                            foreach ($peers as $p) {
                                if ($p->id !== $emp->id && ! isset($evalSet[$user->id.'_'.$p->user_id.'__peer'])) {
                                    $pending++;
                                }
                            }
                        }
                    }

                    if ($user->hasRole('program head') && $user->employee) {
                        $emp = $user->employee;
                        if (! isset($evalSet[$user->id.'_'.$user->id.'__self'])) {
                            $pending++;
                        }
                        if ($emp->department_id) {
                            $facList = $deptFacultyMap[$emp->department_id] ?? [];
                            foreach ($facList as $fac) {
                                if (! isset($evalSet[$user->id.'_'.$fac->user_id.'__peer']) && ! isset($evalSet[$user->id.'_'.$fac->user_id.'__downward'])) {
                                    $pending++;
                                }
                            }
                        }
                    }

                    if ($user->hasRole('dean') && $user->employee) {
                        if (! isset($evalSet[$user->id.'_'.$user->id.'__self'])) {
                            $pending++;
                        }
                        foreach ($programHeadsList as $ph) {
                            if (! isset($evalSet[$user->id.'_'.$ph->user_id.'__peer']) && ! isset($evalSet[$user->id.'_'.$ph->user_id.'__downward'])) {
                                $pending++;
                            }
                        }
                    }

                    if ($user->hasRole('staff') && $user->employee) {
                        $emp = $user->employee;
                        if (! isset($evalSet[$user->id.'_'.$user->id.'__self'])) {
                            $pending++;
                        }
                        if ($emp->department_id) {
                            $peers = $deptStaffMap[$emp->department_id] ?? [];
                            foreach ($peers as $p) {
                                if ($p->id !== $emp->id && ! isset($evalSet[$user->id.'_'.$p->user_id.'__peer'])) {
                                    $pending++;
                                }
                            }
                        }
                    }

                    if ($user->hasRole('department head') && $user->employee) {
                        $emp = $user->employee;
                        if (! isset($evalSet[$user->id.'_'.$user->id.'__self'])) {
                            $pending++;
                        }
                        if ($emp->department_id) {
                            $stList = $deptStaffMap[$emp->department_id] ?? [];
                            foreach ($stList as $st) {
                                if (! isset($evalSet[$user->id.'_'.$st->user_id.'__downward'])) {
                                    $pending++;
                                }
                            }
                        }
                    }

                    if ($pending > 0) {
                        $notifiedCount++;
                        foreach ($roleBreakdown as $role => &$count) {
                            if ($user->hasRole($role)) {
                                $count++;
                                break;
                            }
                        }
                    }
                }
            });

        if (function_exists('activity')) {
            activity('evaluations')
                ->log("Evaluation deadline reminder ({$tier}) broadcasted to {$notifiedCount} pending evaluators.");
        }

        Log::info("Evaluation deadline reminder ({$tier}) broadcasted", [
            'semester_id' => $sem->id,
            'tier' => $tier,
            'hours_left' => $hoursLeft,
            'notified_count' => $notifiedCount,
            'breakdown' => $roleBreakdown,
        ]);

        $this->info("Broadcast completed successfully. Notified {$notifiedCount} pending evaluators.");
        $this->table(
            ['Role', 'Pending Evaluators'],
            collect($roleBreakdown)->map(fn ($val, $key) => [ucwords($key), $val])->values()->toArray()
        );

        return self::SUCCESS;
    }

    /**
     * Count pending evaluations for a given user in the active semester.
     */
    public function countUserPendingEvaluations(User $user, Semester $sem): int
    {
        $pending = 0;

        if ($user->hasRole('student') && $user->student_id) {
            $studentClassEnrollments = DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->join('users', 'users.employee_id', '=', 'classes.teacher_id')
                ->where('classes.semester_id', $sem->id)
                ->where('class_student.student_id', $user->student_id)
                ->select('classes.id as class_id', 'users.id as teacher_user_id')
                ->get();

            foreach ($studentClassEnrollments as $cls) {
                $exists = Evaluation::where([
                    'semester_id' => $sem->id,
                    'evaluator_id' => $user->id,
                    'evaluatee_id' => $cls->teacher_user_id,
                    'class_id' => $cls->class_id,
                ])->exists();

                if (! $exists) {
                    $pending++;
                }
            }
        }

        if ($user->hasRole('faculty') && $user->employee) {
            $emp = $user->employee;

            // Self evaluation
            $selfDone = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $user->id,
                'evaluatee_id' => $user->id,
                'evaluation_type' => 'self',
            ])->exists();

            if (! $selfDone) {
                $pending++;
            }

            // Peer evaluations in same department
            if ($emp->department_id) {
                $peers = Employee::where('role', 'faculty')
                    ->where('department_id', $emp->department_id)
                    ->where('id', '!=', $emp->id)
                    ->where('status', 'active')
                    ->with('user')
                    ->get();

                foreach ($peers as $peer) {
                    if ($peer->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $peer->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $pending++;
                    }
                }
            }
        }

        if ($user->hasRole('program head') && $user->employee) {
            $emp = $user->employee;

            // Self
            if (! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $user->id, 'evaluation_type' => 'self'])->exists()) {
                $pending++;
            }

            // Subordinate faculty
            if ($emp->department_id) {
                $faculty = Employee::where('role', 'faculty')
                    ->where('department_id', $emp->department_id)
                    ->where('status', 'active')
                    ->with('user')
                    ->get();

                foreach ($faculty as $fac) {
                    if ($fac->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $fac->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $pending++;
                    }
                }
            }
        }

        if ($user->hasRole('dean') && $user->employee) {
            // Self
            if (! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $user->id, 'evaluation_type' => 'self'])->exists()) {
                $pending++;
            }

            // Program heads
            $heads = Employee::where('role', 'program head')->where('status', 'active')->with('user')->get();
            foreach ($heads as $head) {
                if ($head->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $head->user->id, 'evaluation_type' => 'peer'])->exists()) {
                    $pending++;
                }
            }
        }

        if ($user->hasRole('staff') && $user->employee) {
            $emp = $user->employee;

            // Self
            if (! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $user->id, 'evaluation_type' => 'self'])->exists()) {
                $pending++;
            }

            // Peer staff in department
            if ($emp->department_id) {
                $peerStaff = Employee::where('role', 'staff')
                    ->where('department_id', $emp->department_id)
                    ->where('id', '!=', $emp->id)
                    ->where('status', 'active')
                    ->with('user')
                    ->get();

                foreach ($peerStaff as $peer) {
                    if ($peer->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $peer->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $pending++;
                    }
                }
            }
        }

        if ($user->hasRole('department head') && $user->employee) {
            $emp = $user->employee;

            // Self
            if (! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $user->id, 'evaluation_type' => 'self'])->exists()) {
                $pending++;
            }

            // Staff in dept
            if ($emp->department_id) {
                $staff = Employee::where('role', 'staff')
                    ->where('department_id', $emp->department_id)
                    ->where('status', 'active')
                    ->with('user')
                    ->get();

                foreach ($staff as $st) {
                    if ($st->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $st->user->id, 'evaluation_type' => 'downward'])->exists()) {
                        $pending++;
                    }
                }
            }
        }

        return $pending;
    }
}
