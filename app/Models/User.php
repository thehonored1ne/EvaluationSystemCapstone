<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
            ->logExcept(['password', 'remember_token'])
            ->dontLogIfAttributesChangedOnly(['password', 'remember_token'])
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
            'is_active' => 'boolean',
            'show_ai_pipeline' => 'boolean',
            'notifications_last_viewed_at' => 'datetime',
            'dismissed_notifications' => 'array',
        ];
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
        $sem = Semester::where('is_active', true)->first();
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
                'description' => "The evaluation period for {$sem->academicYear->name} - {$sem->name} is now open{$deadlineInfo}. Please submit your feedback.",
                'created_at' => $notificationTime,
            ];
        } else {
            $timeInfo = '';
            if ($sem->evaluation_starts_at) {
                $timeInfo = ' starts at '.$sem->evaluation_starts_at->format('F d, Y h:i A');
            }
            $notifications[] = (object) [
                'id' => 'sem_'.$sem->id.'_closed',
                'type' => 'warning',
                'title' => 'Evaluations are Closed',
                'description' => "Evaluations for {$sem->academicYear->name} - {$sem->name} are currently locked/closed{$timeInfo}.",
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

            $pendingCount = 0;
            foreach ($classes as $class) {
                if ($class->teacher && $class->teacher->user) {
                    $evaluated = Evaluation::where([
                        'semester_id' => $sem->id,
                        'evaluator_id' => $this->id,
                        'evaluatee_id' => $class->teacher->user->id,
                        'class_id' => $class->id,
                    ])->exists();

                    if (! $evaluated) {
                        $pendingCount++;
                    }
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
        } elseif ($this->hasRole('faculty') && $this->employee) {
            $emp = $this->employee;

            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $this->id,
                'evaluatee_id' => $this->id,
                'evaluation_type' => 'self',
            ])->exists();

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
                    if ($peer->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $peer->user->id, 'evaluation_type' => 'peer'])->exists()) {
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
            }
        } elseif ($this->hasRole('program head') && $this->employee) {
            $emp = $this->employee;

            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $this->id,
                'evaluatee_id' => $this->id,
                'evaluation_type' => 'self',
            ])->exists();

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
                    if ($member->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $member->user->id, 'evaluation_type' => 'peer'])->exists()) {
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
        } elseif ($this->hasRole('dean') && $this->employee) {
            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $this->id,
                'evaluatee_id' => $this->id,
                'evaluation_type' => 'self',
            ])->exists();

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
                if ($head->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $head->user->id, 'evaluation_type' => 'peer'])->exists()) {
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
        } elseif ($this->hasRole('staff') && $this->employee) {
            $emp = $this->employee;

            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $this->id,
                'evaluatee_id' => $this->id,
                'evaluation_type' => 'self',
            ])->exists();

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
                    $headDone = Evaluation::where([
                        'semester_id' => $sem->id,
                        'evaluator_id' => $this->id,
                        'evaluatee_id' => $headUser->id,
                        'evaluation_type' => 'upward_employee',
                    ])->exists();

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
                    if ($peer->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $peer->user->id, 'evaluation_type' => 'peer'])->exists()) {
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
        } elseif ($this->hasRole('department head') && $this->employee) {
            $emp = $this->employee;

            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $this->id,
                'evaluatee_id' => $this->id,
                'evaluation_type' => 'self',
            ])->exists();

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
                    if ($staff->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $staff->user->id, 'evaluation_type' => 'downward'])->exists()) {
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
                if ($dean->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $dean->user->id, 'evaluation_type' => 'upward_employee'])->exists()) {
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
}
