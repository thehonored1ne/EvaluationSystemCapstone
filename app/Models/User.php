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
        'notifications_last_viewed_at',
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
            'notifications_last_viewed_at' => 'datetime',
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
    public function getNotifications(): array
    {
        $notifications = [];
        $sem = Semester::where('is_active', true)->first();
        if (! $sem) {
            $notifications[] = (object) [
                'type' => 'warning',
                'title' => 'No Active Semester',
                'description' => 'There is no active academic semester configured. Evaluations are disabled.',
                'created_at' => $this->created_at ?? now(),
            ];

            return $notifications;
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
                    'type' => 'reminder',
                    'title' => 'Self Evaluation Incomplete',
                    'description' => 'Please submit your staff self-evaluation report.',
                    'created_at' => $notificationTime,
                ];
            }

            // Program Heads pending
            if ($emp->department_id) {
                $heads = Employee::where('role', 'program head')
                    ->where('department_id', $emp->department_id)
                    ->with('user')
                    ->get();

                $headPending = 0;
                foreach ($heads as $head) {
                    if ($head->user && ! Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $this->id, 'evaluatee_id' => $head->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $headPending++;
                    }
                }

                if ($headPending > 0 && $isEvaluationOpen) {
                    $notifications[] = (object) [
                        'type' => 'reminder',
                        'title' => 'Pending Supervisor Evaluations',
                        'description' => "You have {$headPending} supervisor Program Head evaluation(s) remaining.",
                        'created_at' => $notificationTime,
                    ];
                }
            }
        }

        return $notifications;
    }
}
