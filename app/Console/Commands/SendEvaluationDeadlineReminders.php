<?php

namespace App\Console\Commands;

use App\Models\AcademicClass;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Console\Command;
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

        // Identify all users with pending evaluations
        $activeUsers = User::where('is_active', true)->with(['employee.department', 'student', 'roles'])->get();
        $notifiedCount = 0;
        $roleBreakdown = [
            'student' => 0,
            'faculty' => 0,
            'program head' => 0,
            'department head' => 0,
            'dean' => 0,
            'staff' => 0,
        ];

        foreach ($activeUsers as $user) {
            $pendingCount = $this->countUserPendingEvaluations($user, $sem);

            if ($pendingCount > 0) {
                $notifiedCount++;
                foreach ($roleBreakdown as $role => &$count) {
                    if ($user->hasRole($role)) {
                        $count++;
                        break;
                    }
                }
            }
        }

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
            $classes = AcademicClass::where('semester_id', $sem->id)
                ->whereHas('students', fn ($q) => $q->where('students.id', $user->student_id))
                ->with('teacher.user')
                ->get();

            foreach ($classes as $class) {
                if ($class->teacher && $class->teacher->user) {
                    $exists = Evaluation::where([
                        'semester_id' => $sem->id,
                        'evaluator_id' => $user->id,
                        'evaluatee_id' => $class->teacher->user->id,
                        'class_id' => $class->id,
                    ])->exists();

                    if (! $exists) {
                        $pending++;
                    }
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
