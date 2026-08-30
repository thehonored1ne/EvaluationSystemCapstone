<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable // implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['password', 'remember_token', 'notifications_last_viewed_at', 'dismissed_notifications', 'password_changed_at', 'updated_at'])
            ->dontLogIfAttributesChangedOnly(['password', 'remember_token', 'notifications_last_viewed_at', 'dismissed_notifications', 'password_changed_at', 'updated_at'])
            ->useLogName('user');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'student_id',
        'employee_id',
        'password',
        'password_changed_at',
        'is_active',
        'show_ai_pipeline',
        'notifications_last_viewed_at',
        'dismissed_notifications',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'show_ai_pipeline' => 'boolean',
            'notifications_last_viewed_at' => 'datetime',
            'dismissed_notifications' => 'array',
        ];
    }

    /**
     * Check if the user is currently using the system default password.
     */
    public function isUsingDefaultPassword(): bool
    {
        if ($this->password_changed_at !== null) {
            return false;
        }

        return Hash::check('password', $this->password);
    }

    /**
     * Get the student profile associated with the user.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the employee profile associated with the user.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Check if the user is a student.
     */
    public function isStudent(): bool
    {
        return ! is_null($this->student_id);
    }

    /**
     * Check if the user is an employee.
     */
    public function isEmployee(): bool
    {
        return ! is_null($this->employee_id);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Get dynamic notifications for the user.
     */
    public function getNotifications(bool $ignoreDismissed = false): array
    {
        $notifications = [];
        $sem = Semester::with('academicYear')->where('is_active', true)->first();
        if (! $sem) {
            $notifications[] = (object) [
                'id' => 'no_active_semester',
                'type' => 'warning',
                'title' => 'No Active Semester',
                'description' => 'There is no active academic semester configured. Evaluations are disabled.',
                'created_at' => $this->created_at ?? now(),
            ];

            return $this->filterDismissedNotifications($notifications, $ignoreDismissed);
        }

        $ayName = $sem->academicYear?->name ?? 'Academic Year';

        // Capped timestamps to ensure notifications are never dated in the future
        $now = now();

        $notificationTime = $sem->evaluation_starts_at ?? $sem->updated_at;
        if ($notificationTime && $notificationTime->gt($now)) {
            $notificationTime = $sem->updated_at->gt($now) ? $now : $sem->updated_at;
        }

        $closedNotificationTime = $sem->evaluation_ends_at ?? $sem->updated_at;
        if ($closedNotificationTime && $closedNotificationTime->gt($now)) {
            $closedNotificationTime = $sem->updated_at->gt($now) ? $now : $sem->updated_at;
        }

        // Check if evaluations are open
        $isEvaluationOpen = $sem->isEvaluationWindowActive();

        // 1. General announcement about the semester evaluations
        if ($isEvaluationOpen) {
            $deadlineInfo = '';
            if ($sem->evaluation_ends_at) {
                $deadlineInfo = ' until '.$sem->evaluation_ends_at->format('F d, Y h:i A');
            }
            $notifications[] = (object) [
                'id' => 'sem_'.$sem->id.'_open',
                'type' => 'info',
                'title' => 'Evaluations are Open',
                'description' => "The evaluation period for {$ayName} - {$sem->name} is now open{$deadlineInfo}. Please submit your feedback.",
                'created_at' => $notificationTime,
            ];

            // 1.1 Deadline approaching urgency notification (if within 72 hours)
            if ($sem->evaluation_ends_at) {
                $hoursLeft = (int) $now->diffInHours($sem->evaluation_ends_at, false);
                if ($hoursLeft >= 0 && $hoursLeft <= 72) {
                    $urgencyTier = match (true) {
                        $hoursLeft <= 6 => '6h',
                        $hoursLeft <= 24 => '24h',
                        default => '3d',
                    };
                    $urgencyLabel = match ($urgencyTier) {
                        '6h' => 'Urgent: Closing in '.max(1, $hoursLeft).' hour(s)',
                        '24h' => 'Important: Closing in '.max(1, $hoursLeft).' hours',
                        default => 'Closing Soon in '.ceil($hoursLeft / 24).' days',
                    };
                    $notifications[] = (object) [
                        'id' => 'sem_'.$sem->id.'_deadline_'.$urgencyTier,
                        'type' => 'warning',
                        'title' => 'Evaluation Deadline Approaching',
                        'description' => "Evaluations for {$ayName} - {$sem->name} will close on {$sem->evaluation_ends_at->format('F d, Y h:i A')} ({$urgencyLabel}).",
                        'created_at' => $notificationTime,
                    ];
                }
            }
        } else {
            $timeInfo = '';
            if ($sem->evaluation_starts_at) {
                $timeInfo = ' starts at '.$sem->evaluation_starts_at->format('F d, Y h:i A');
            }
            $notifications[] = (object) [
                'id' => 'sem_'.$sem->id.'_closed',
                'type' => 'warning',
                'title' => 'Evaluations are Closed',
                'description' => "Evaluations for {$ayName} - {$sem->name} are currently locked/closed{$timeInfo}.",
                'created_at' => $closedNotificationTime,
            ];
        }

        // 2. Role-specific reminders
        if ($this->hasRole('student') && $this->student) {
            // Find student pending class evaluations
            $classes = AcademicClass::where('semester_id', $sem->id)
                ->whereHas('students', function ($q) {
                    $q->where('students.id', $this->student_id);
                })
                ->with(['subject', 'teacher.user'])
                ->get();

            $evaluatedClassSet = array_flip(
                Evaluation::where('semester_id', $sem->id)
                    ->where('evaluator_id', $this->id)
                    ->pluck('class_id')
                    ->filter()
                    ->all()
            );

            $pendingCount = 0;
            foreach ($classes as $class) {
                if ($class->teacher && $class->teacher->user && ! isset($evaluatedClassSet[$class->id])) {
                    $pendingCount++;
                }
            }

            if ($pendingCount > 0 && $isEvaluationOpen) {
                $notifications[] = (object) [
                    'id' => 'student_pending_'.$sem->id.'_'.$pendingCount,
                    'type' => 'reminder',
                    'title' => 'Pending Professor Evaluations',
                    'description' => "You have {$pendingCount} pending professor evaluation(s) to fill out. Please go to Manage Evaluations.",
                    'created_at' => $notificationTime,
                ];
            }
        } elseif ($this->employee) {
            $emp = $this->employee;
            $completedEvals = Evaluation::where('semester_id', $sem->id)
                ->where('evaluator_id', $this->id)
                ->select(['evaluatee_id', 'evaluation_type'])
                ->get();

            $completedMap = [];
            foreach ($completedEvals as $ev) {
                $completedMap[$ev->evaluatee_id.'_'.$ev->evaluation_type] = true;
            }

            if ($this->hasRole('faculty')) {
                // Check self evaluation
                $selfEvaluated = isset($completedMap[$this->id.'_self']);

                if (! $selfEvaluated && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'faculty_self_'.$sem->id,
                        'type' => 'reminder',
                        'title' => 'Self Evaluation Incomplete',
                        'description' => 'You have not submitted your self-evaluation report for this semester yet.',
                        'created_at' => $notificationTime,
                    ];
                }

                // Peers pending
                if ($emp->department_id) {
                    $peers = Employee::where('role', 'faculty')
                        ->where('department_id', $emp->department_id)
                        ->where('id', '!=', $emp->id)
                        ->with('user')
                        ->get();

                    $peerPending = 0;
                    foreach ($peers as $peer) {
                        if ($peer->user && ! isset($completedMap[$peer->user->id.'_peer'])) {
                            $peerPending++;
                        }
                    }

                    if ($peerPending > 0 && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'faculty_peer_'.$sem->id.'_'.$peerPending,
                            'type' => 'reminder',
                            'title' => 'Pending Peer Evaluations',
                            'description' => "You have {$peerPending} peer evaluation(s) remaining for faculty members in your department.",
                            'created_at' => $notificationTime,
                        ];
                    }

                    // Program Head supervisor pending
                    $phList = Employee::where('role', 'program head')
                        ->where('department_id', $emp->department_id)
                        ->with('user')
                        ->get();

                    $phPending = 0;
                    foreach ($phList as $ph) {
                        if ($ph->user
                            && ! isset($completedMap[$ph->user->id.'_upward_employee'])
                            && ! isset($completedMap[$ph->user->id.'_superior'])) {
                            $phPending++;
                        }
                    }

                    if ($phPending > 0 && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'faculty_ph_'.$sem->id.'_'.$phPending,
                            'type' => 'reminder',
                            'title' => 'Pending Program Head Evaluation',
                            'description' => "You have {$phPending} supervisor evaluation(s) remaining for your department Program Head.",
                            'created_at' => $notificationTime,
                        ];
                    }
                }
            } elseif ($this->hasRole('program head')) {
                // Check self evaluation
                $selfEvaluated = isset($completedMap[$this->id.'_self']);

                if (! $selfEvaluated && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'ph_self_'.$sem->id,
                        'type' => 'reminder',
                        'title' => 'Self Evaluation Incomplete',
                        'description' => 'Please fill out your self-evaluation form for this semester.',
                        'created_at' => $notificationTime,
                    ];
                }

                // Faculty subordinates pending
                if ($emp->department_id) {
                    $faculty = Employee::where('role', 'faculty')
                        ->where('department_id', $emp->department_id)
                        ->with('user')
                        ->get();

                    $facPending = 0;
                    foreach ($faculty as $member) {
                        if ($member->user
                            && ! isset($completedMap[$member->user->id.'_program_head'])
                            && ! isset($completedMap[$member->user->id.'_downward'])
                            && ! isset($completedMap[$member->user->id.'_peer'])) {
                            $facPending++;
                        }
                    }

                    if ($facPending > 0 && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'ph_fac_'.$sem->id.'_'.$facPending,
                            'type' => 'reminder',
                            'title' => 'Pending Subordinate Evaluations',
                            'description' => "You have {$facPending} subordinate faculty evaluation(s) remaining in your department.",
                            'created_at' => $notificationTime,
                        ];
                    }
                }

                // Dean supervisor evaluation pending
                $deanUser = null;
                if ($emp->department_id) {
                    $dept = $emp->department;
                    if ($dept && $dept->dean_id) {
                        $deanEmp = Employee::with('user')->find($dept->dean_id);
                        $deanUser = $deanEmp?->user;
                    }
                }
                if (! $deanUser) {
                    $deanEmp = Employee::where('role', 'dean')->where('status', 'active')->with('user')->first();
                    $deanUser = $deanEmp?->user;
                }

                if ($deanUser && $deanUser->id !== $this->id) {
                    $deanDone = isset($completedMap[$deanUser->id.'_upward_employee']) || isset($completedMap[$deanUser->id.'_superior']);

                    if (! $deanDone && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'ph_dean_'.$sem->id,
                            'type' => 'reminder',
                            'title' => 'Pending Dean Evaluation',
                            'description' => 'You have 1 College Dean supervisor evaluation remaining to submit.',
                            'created_at' => $notificationTime,
                        ];
                    }
                }
            } elseif ($this->hasRole('dean')) {
                // Check self evaluation
                $selfEvaluated = isset($completedMap[$this->id.'_self']);

                if (! $selfEvaluated && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'dean_self_'.$sem->id,
                        'type' => 'reminder',
                        'title' => 'Self Evaluation Incomplete',
                        'description' => 'Please submit your dean self-evaluation form.',
                        'created_at' => $notificationTime,
                    ];
                }

                // PH subordinates pending
                $heads = Employee::where('role', 'program head')->with('user')->get();
                $phPending = 0;
                foreach ($heads as $head) {
                    if ($head->user
                        && ! isset($completedMap[$head->user->id.'_dean'])
                        && ! isset($completedMap[$head->user->id.'_downward'])
                        && ! isset($completedMap[$head->user->id.'_peer'])) {
                        $phPending++;
                    }
                }

                if ($phPending > 0 && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'dean_ph_'.$sem->id.'_'.$phPending,
                        'type' => 'reminder',
                        'title' => 'Pending Program Head Evaluations',
                        'description' => "You have {$phPending} Program Head evaluation(s) remaining to fill out.",
                        'created_at' => $notificationTime,
                    ];
                }
            } elseif ($this->hasRole('staff')) {
                // Check self evaluation
                $selfEvaluated = isset($completedMap[$this->id.'_self']);

                if (! $selfEvaluated && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'staff_self_'.$sem->id,
                        'type' => 'reminder',
                        'title' => 'Self Evaluation Incomplete',
                        'description' => 'Please submit your staff self-evaluation report.',
                        'created_at' => $notificationTime,
                    ];
                }

                // Department Head supervisor evaluation pending
                if ($emp->department_id) {
                    $dept = $emp->department;
                    $headUser = null;
                    if ($dept && $dept->department_head_id) {
                        $headEmp = Employee::with('user')->find($dept->department_head_id);
                        $headUser = $headEmp?->user;
                    } else {
                        $headEmp = Employee::where('role', 'department head')->where('department_id', $emp->department_id)->first();
                        $headUser = $headEmp?->user;
                    }

                    if ($headUser && $headUser->id !== $this->id) {
                        $headDone = isset($completedMap[$headUser->id.'_upward_employee']) || isset($completedMap[$headUser->id.'_superior']);

                        if (! $headDone && $isEvaluationOpen) {
                            $notifications[] = (object) [
                                'id' => 'staff_dh_'.$sem->id,
                                'type' => 'reminder',
                                'title' => 'Pending Supervisor Evaluation',
                                'description' => 'You have 1 Department Head supervisor evaluation remaining to submit.',
                                'created_at' => $notificationTime,
                            ];
                        }
                    }

                    // Peer staff evaluations pending
                    $peerStaff = Employee::where('role', 'staff')
                        ->where('department_id', $emp->department_id)
                        ->where('id', '!=', $emp->id)
                        ->with('user')
                        ->get();

                    $peerPending = 0;
                    foreach ($peerStaff as $peer) {
                        if ($peer->user && ! isset($completedMap[$peer->user->id.'_peer'])) {
                            $peerPending++;
                        }
                    }

                    if ($peerPending > 0 && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'staff_peer_'.$sem->id.'_'.$peerPending,
                            'type' => 'reminder',
                            'title' => 'Pending Peer Evaluations',
                            'description' => "You have {$peerPending} peer staff evaluation(s) remaining to submit.",
                            'created_at' => $notificationTime,
                        ];
                    }
                }
            } elseif ($this->hasRole('department head')) {
                // Check self evaluation
                $selfEvaluated = isset($completedMap[$this->id.'_self']);

                if (! $selfEvaluated && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'dh_self_'.$sem->id,
                        'type' => 'reminder',
                        'title' => 'Self Evaluation Incomplete',
                        'description' => 'Please submit your department head self-evaluation report.',
                        'created_at' => $notificationTime,
                    ];
                }

                // Staff members in administrative department pending
                if ($emp->department_id) {
                    $staffMembers = Employee::where('role', 'staff')
                        ->where('department_id', $emp->department_id)
                        ->with('user')
                        ->get();

                    $staffPending = 0;
                    foreach ($staffMembers as $staff) {
                        if ($staff->user
                            && ! isset($completedMap[$staff->user->id.'_department_head'])
                            && ! isset($completedMap[$staff->user->id.'_downward'])) {
                            $staffPending++;
                        }
                    }

                    if ($staffPending > 0 && $isEvaluationOpen) {
                        $notifications[] = (object) [
                            'id' => 'dh_staff_'.$sem->id.'_'.$staffPending,
                            'type' => 'reminder',
                            'title' => 'Pending Staff Evaluations',
                            'description' => "You have {$staffPending} staff evaluation(s) remaining in your administrative department.",
                            'created_at' => $notificationTime,
                        ];
                    }
                }

                // Dean pending
                $deans = Employee::where('role', 'dean')->with('user')->get();
                $deanPending = 0;
                foreach ($deans as $dean) {
                    if ($dean->user
                        && ! isset($completedMap[$dean->user->id.'_upward_employee'])
                        && ! isset($completedMap[$dean->user->id.'_superior'])) {
                        $deanPending++;
                    }
                }

                if ($deanPending > 0 && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'id' => 'dh_dean_'.$sem->id.'_'.$deanPending,
                        'type' => 'reminder',
                        'title' => 'Pending Dean Evaluation',
                        'description' => "You have {$deanPending} Dean evaluation(s) remaining to complete.",
                        'created_at' => $notificationTime,
                    ];
                }
            }
        }

        return $this->filterDismissedNotifications($notifications, $ignoreDismissed);
    }

    /**
     * Filter out dismissed notifications if not ignored.
     */
    protected function filterDismissedNotifications(array $notifications, bool $ignoreDismissed): array
    {
        if ($ignoreDismissed) {
            return $notifications;
        }

        $dismissed = $this->dismissed_notifications ?? [];
        if (empty($dismissed)) {
            return $notifications;
        }

        return array_values(array_filter($notifications, function ($notif) use ($dismissed) {
            return ! in_array($notif->id ?? '', $dismissed);
        }));
    }

    /**
     * Dismiss a specific notification by ID.
     */
    public function dismissNotification(string $key): void
    {
        $dismissed = $this->dismissed_notifications ?? [];
        if (! in_array($key, $dismissed)) {
            $dismissed[] = $key;
            $this->update(['dismissed_notifications' => $dismissed]);
        }
    }

    /**
     * Dismiss all current notifications.
     */
    public function clearAllNotifications(): void
    {
        $allNotifs = $this->getNotifications(ignoreDismissed: true);
        $keys = array_map(fn ($n) => $n->id, $allNotifs);
        $dismissed = array_values(array_unique(array_merge($this->dismissed_notifications ?? [], $keys)));
        $this->update([
            'dismissed_notifications' => $dismissed,
            'notifications_last_viewed_at' => now(),
        ]);
    }

    /**
     * Count pending evaluations for this user in the specified (or active) semester.
     */
    public function countPendingEvaluations(?Semester $sem = null): int
    {
        $sem = $sem ?? Semester::where('is_active', true)->first();
        if (! $sem) {
            return 0;
        }

        $pending = 0;

        if ($this->hasRole('student') && $this->student_id) {
            $classes = AcademicClass::where('semester_id', $sem->id)
                ->whereHas('students', fn ($q) => $q->where('students.id', $this->student_id))
                ->with('teacher.user')
                ->get();

            $evaluatedClassSet = array_flip(
                Evaluation::where('semester_id', $sem->id)
                    ->where('evaluator_id', $this->id)
                    ->pluck('class_id')
                    ->filter()
                    ->all()
            );

            foreach ($classes as $class) {
                if ($class->teacher && $class->teacher->user && ! isset($evaluatedClassSet[$class->id])) {
                    $pending++;
                }
            }
        } elseif ($this->employee) {
            $emp = $this->employee;
            $completedEvals = Evaluation::where('semester_id', $sem->id)
                ->where('evaluator_id', $this->id)
                ->select(['evaluatee_id', 'evaluation_type'])
                ->get();

            $completedMap = [];
            foreach ($completedEvals as $ev) {
                $completedMap[$ev->evaluatee_id.'_'.$ev->evaluation_type] = true;
            }

            if ($this->hasRole('faculty')) {
                // Self evaluation
                if (! isset($completedMap[$this->id.'_self'])) {
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
                        if ($peer->user && ! isset($completedMap[$peer->user->id.'_peer'])) {
                            $pending++;
                        }
                    }

                    // Program Head supervisor in same department
                    $phList = Employee::where('role', 'program head')
                        ->where('department_id', $emp->department_id)
                        ->where('status', 'active')
                        ->with('user')
                        ->get();

                    foreach ($phList as $ph) {
                        if ($ph->user
                            && ! isset($completedMap[$ph->user->id.'_upward_employee'])
                            && ! isset($completedMap[$ph->user->id.'_superior'])) {
                            $pending++;
                        }
                    }
                }
            } elseif ($this->hasRole('program head')) {
                // Self
                if (! isset($completedMap[$this->id.'_self'])) {
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
                        if ($fac->user
                            && ! isset($completedMap[$fac->user->id.'_program_head'])
                            && ! isset($completedMap[$fac->user->id.'_downward'])
                            && ! isset($completedMap[$fac->user->id.'_peer'])) {
                            $pending++;
                        }
                    }
                }

                // Supervisor Dean
                $deanUser = null;
                if ($emp->department_id) {
                    $dept = $emp->department;
                    if ($dept && $dept->dean_id) {
                        $deanEmp = Employee::with('user')->find($dept->dean_id);
                        $deanUser = $deanEmp?->user;
                    }
                }
                if (! $deanUser) {
                    $deanEmp = Employee::where('role', 'dean')->where('status', 'active')->with('user')->first();
                    $deanUser = $deanEmp?->user;
                }

                if ($deanUser && $deanUser->id !== $this->id
                    && ! isset($completedMap[$deanUser->id.'_upward_employee'])
                    && ! isset($completedMap[$deanUser->id.'_superior'])) {
                    $pending++;
                }
            } elseif ($this->hasRole('dean')) {
                // Self
                if (! isset($completedMap[$this->id.'_self'])) {
                    $pending++;
                }

                // Program heads
                $heads = Employee::where('role', 'program head')->where('status', 'active')->with('user')->get();
                foreach ($heads as $head) {
                    if ($head->user
                        && ! isset($completedMap[$head->user->id.'_dean'])
                        && ! isset($completedMap[$head->user->id.'_downward'])
                        && ! isset($completedMap[$head->user->id.'_peer'])) {
                        $pending++;
                    }
                }
            } elseif ($this->hasRole('staff')) {
                // Self
                if (! isset($completedMap[$this->id.'_self'])) {
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
                        if ($peer->user && ! isset($completedMap[$peer->user->id.'_peer'])) {
                            $pending++;
                        }
                    }
                }
            } elseif ($this->hasRole('department head')) {
                // Self
                if (! isset($completedMap[$this->id.'_self'])) {
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
                        if ($st->user
                            && ! isset($completedMap[$st->user->id.'_department_head'])
                            && ! isset($completedMap[$st->user->id.'_downward'])) {
                            $pending++;
                        }
                    }
                }
            }
        }

        return $pending;
    }
}
