<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\Employee;
use App\Models\User;
use App\Models\Evaluation;

new #[Layout('components.layouts.app')] class extends Component {
    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getIsEvaluationOpenProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return false;
        if ($sem->is_evaluation_open) return true;
        if ($sem->evaluation_starts_at && $sem->evaluation_ends_at) {
            return now()->between($sem->evaluation_starts_at, $sem->evaluation_ends_at);
        }
        return false;
    }

    public function getNotificationsProperty()
    {
        $notifications = [];
        $sem = $this->activeSemester;
        if (!$sem) {
            $notifications[] = (object) [
                'type' => 'warning',
                'title' => 'No Active Semester',
                'description' => 'There is no active academic semester configured. Evaluations are disabled.',
                'created_at' => now(),
            ];
            return $notifications;
        }

        // 1. General announcement about the semester evaluations
        if ($this->isEvaluationOpen) {
            $deadlineInfo = '';
            if ($sem->evaluation_ends_at) {
                $deadlineInfo = ' until ' . $sem->evaluation_ends_at->format('F d, Y h:i A');
            }
            $notifications[] = (object) [
                'type' => 'info',
                'title' => 'Evaluations are Open',
                'description' => "The evaluation period for {$sem->academicYear->name} - {$sem->name} is now open{$deadlineInfo}. Please submit your feedback.",
                'created_at' => $sem->updated_at,
            ];
        } else {
            $timeInfo = '';
            if ($sem->evaluation_starts_at) {
                $timeInfo = ' starts at ' . $sem->evaluation_starts_at->format('F d, Y h:i A');
            }
            $notifications[] = (object) [
                'type' => 'warning',
                'title' => 'Evaluations are Closed',
                'description' => "Evaluations for {$sem->academicYear->name} - {$sem->name} are currently locked/closed{$timeInfo}.",
                'created_at' => $sem->updated_at,
            ];
        }

        // 2. Role-specific reminders
        $user = auth()->user();
        if ($user->hasRole('student') && $user->student) {
            // Find student pending class evaluations
            $classes = AcademicClass::where('semester_id', $sem->id)
                ->whereHas('students', function ($q) use ($user) {
                    $q->where('students.id', $user->student_id);
                })
                ->with(['subject', 'teacher.user'])
                ->get();

            $pendingCount = 0;
            foreach ($classes as $class) {
                $evaluated = Evaluation::where([
                    'semester_id' => $sem->id,
                    'evaluator_id' => $user->id,
                    'evaluatee_id' => $class->teacher->user->id,
                    'class_id' => $class->id,
                ])->exists();

                if (!$evaluated) $pendingCount++;
            }

            if ($pendingCount > 0 && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Pending Professor Evaluations',
                    'description' => "You have {$pendingCount} pending professor evaluation(s) to fill out. Please go to Manage Evaluations.",
                    'created_at' => now(),
                ];
            }
        } elseif ($user->hasRole('faculty') && $user->employee) {
            $emp = $user->employee;
            
            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $user->id,
                'evaluatee_id' => $user->id,
                'evaluation_type' => 'self',
            ])->exists();

            if (!$selfEvaluated && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Self Evaluation Incomplete',
                    'description' => 'You have not submitted your self-evaluation report for this semester yet.',
                    'created_at' => now(),
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
                    if ($peer->user && !Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $peer->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $peerPending++;
                    }
                }

                if ($peerPending > 0 && $this->isEvaluationOpen) {
                    $notifications[] = (object) [
                        'type' => 'reminder',
                        'title' => 'Pending Peer Evaluations',
                        'description' => "You have {$peerPending} peer evaluation(s) remaining for faculty members in your department.",
                        'created_at' => now(),
                    ];
                }
            }
        } elseif ($user->hasRole('program head') && $user->employee) {
            $emp = $user->employee;
            
            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $user->id,
                'evaluatee_id' => $user->id,
                'evaluation_type' => 'self',
            ])->exists();

            if (!$selfEvaluated && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Self Evaluation Incomplete',
                    'description' => 'Please fill out your self-evaluation form for this semester.',
                    'created_at' => now(),
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
                    if ($member->user && !Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $member->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $facPending++;
                    }
                }

                if ($facPending > 0 && $this->isEvaluationOpen) {
                    $notifications[] = (object) [
                        'type' => 'reminder',
                        'title' => 'Pending Subordinate Evaluations',
                        'description' => "You have {$facPending} subordinate faculty evaluation(s) remaining in your department.",
                        'created_at' => now(),
                    ];
                }
            }
        } elseif ($user->hasRole('dean') && $user->employee) {
            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $user->id,
                'evaluatee_id' => $user->id,
                'evaluation_type' => 'self',
            ])->exists();

            if (!$selfEvaluated && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Self Evaluation Incomplete',
                    'description' => 'Please submit your dean self-evaluation form.',
                    'created_at' => now(),
                ];
            }

            // PH subordinates pending
            $heads = Employee::where('role', 'program head')->with('user')->get();
            $phPending = 0;
            foreach ($heads as $head) {
                if ($head->user && !Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $head->user->id, 'evaluation_type' => 'peer'])->exists()) {
                    $phPending++;
                }
            }

            if ($phPending > 0 && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Pending Program Head Evaluations',
                    'description' => "You have {$phPending} Program Head evaluation(s) remaining to fill out.",
                    'created_at' => now(),
                ];
            }
        } elseif ($user->hasRole('staff') && $user->employee) {
            $emp = $user->employee;

            // Check self evaluation
            $selfEvaluated = Evaluation::where([
                'semester_id' => $sem->id,
                'evaluator_id' => $user->id,
                'evaluatee_id' => $user->id,
                'evaluation_type' => 'self',
            ])->exists();

            if (!$selfEvaluated && $this->isEvaluationOpen) {
                $notifications[] = (object) [
                    'type' => 'reminder',
                    'title' => 'Self Evaluation Incomplete',
                    'description' => 'Please submit your staff self-evaluation report.',
                    'created_at' => now(),
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
                    if ($head->user && !Evaluation::where(['semester_id' => $sem->id, 'evaluator_id' => $user->id, 'evaluatee_id' => $head->user->id, 'evaluation_type' => 'peer'])->exists()) {
                        $headPending++;
                    }
                }

                if ($headPending > 0 && $this->isEvaluationOpen) {
                    $notifications[] = (object) [
                        'type' => 'reminder',
                        'title' => 'Pending Supervisor Evaluations',
                        'description' => "You have {$headPending} supervisor Program Head evaluation(s) remaining.",
                        'created_at' => now(),
                    ];
                }
            }
        }

        return $notifications;
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-4xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Notifications & Alerts</h1>
        <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Stay up to date with active evaluation periods and pending tasks.</p>
    </div>

    <!-- Notifications List -->
    <div class="flex flex-col gap-4">
        @php $notifs = $this->notifications; @endphp
        @if(empty($notifs))
            <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                <flux:icon icon="bell-slash" class="size-16 mx-auto text-zinc-300 mb-3" />
                <p class="font-medium text-zinc-500">No active notifications or alerts.</p>
            </div>
        @else
            @foreach($notifs as $notif)
                <div class="flex items-start gap-4 p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm transition-all duration-200 hover:shadow-md
                    @if($notif->type === 'reminder') border-l-4 border-l-amber-500 @elseif($notif->type === 'info') border-l-4 border-l-indigo-500 @else border-l-4 border-l-rose-500 @endif">
                    
                    <div class="shrink-0 mt-0.5">
                        @if($notif->type === 'reminder')
                            <flux:icon icon="clock" class="size-6 text-amber-500" />
                        @elseif($notif->type === 'info')
                            <flux:icon icon="information-circle" class="size-6 text-indigo-500" />
                        @else
                            <flux:icon icon="exclamation-circle" class="size-6 text-rose-500" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="text-base font-bold text-zinc-900 dark:text-zinc-50">{{ $notif->title }}</div>
                        <p class="text-sm text-zinc-650 dark:text-zinc-355 mt-1 leading-relaxed">{{ $notif->description }}</p>
                        <span class="text-xs text-zinc-400 mt-2 block font-medium">
                            {{ $notif->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
