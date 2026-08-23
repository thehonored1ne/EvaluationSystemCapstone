<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\User;
use App\Models\Evaluation;

new #[Layout('components.layouts.app')] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.student-dashboard-skeleton');
    }

    public ?int $selectedClassId = null;
    public ?User $selectedTeacherUser = null;
    public bool $showForm = false;

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getIsEvaluationOpenProperty()
    {
        $sem = $this->activeSemester;
        return $sem ? $sem->isEvaluationWindowActive() : false;
    }

    public function getEnrolledClassesProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return collect();

        $student = auth()->user()?->student;
        if (!$student) return collect();

        return AcademicClass::where('semester_id', $sem->id)
            ->whereHas('students', function ($q) use ($student) {
                $q->where('students.id', $student->id);
            })
            ->with(['subject', 'teacher.user'])
            ->get();
    }

    public function getEvaluatedCountProperty(): int
    {
        $classes = $this->enrolledClasses;
        if ($classes->isEmpty()) {
            return 0;
        }

        return $classes->filter(function ($class) {
            $teacherUserId = $class->teacher?->user?->id;
            if (!$teacherUserId) {
                return false;
            }

            $status = $this->getClassEvaluationStatus($class->id, $teacherUserId);
            return in_array($status, ['completed', 'processing']);
        })->count();
    }

    public function getIsAllCompletedProperty(): bool
    {
        $classes = $this->enrolledClasses;
        return $classes->isNotEmpty() && $this->evaluatedCount === $classes->count();
    }

    public function getReferenceIdProperty(): ?string
    {
        if (!$this->isAllCompleted || !$this->activeSemester) {
            return null;
        }

        return \App\Services\EvaluationReferenceService::generate(auth()->id(), $this->activeSemester->id);
    }

    public function getFormattedReferenceIdProperty(): ?string
    {
        $ref = $this->referenceId;
        return $ref ? \App\Services\EvaluationReferenceService::format($ref) : null;
    }

    public function getClassEvaluationStatus($classId, $teacherUserId)
    {
        $sem = $this->activeSemester;
        if (!$sem) return 'closed';

        return Evaluation::getStatus(auth()->id(), $teacherUserId, $sem->id, $classId, 'upward_student');
    }

    public function selectClass($classId, $teacherUserId)
    {
        if (!$this->isEvaluationOpen) {
            session()->flash('error', 'Evaluations are currently closed.');
            return;
        }

        $status = $this->getClassEvaluationStatus($classId, $teacherUserId);
        if ($status !== 'pending') {
            session()->flash('error', 'This evaluation is already processing or completed.');
            return;
        }

        $this->selectedClassId = $classId;
        $this->selectedTeacherUser = User::findOrFail($teacherUserId);
        $this->showForm = true;
    }

    #[On('evaluation-submitted')]
    public function handleEvaluationSubmitted()
    {
        $this->selectedClassId = null;
        $this->selectedTeacherUser = null;
        $this->showForm = false;
    }
}; ?>

<div 
    x-data="{
        copied: false,
        copyRef(val) {
            navigator.clipboard.writeText(val).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            });
        },
        launchConfetti() {
            // Lightweight multi-color canvas celebration burst
            const colors = ['#9b0000', '#f59e0b', '#10b981', '#3b82f6', '#ec4899'];
            const canvas = document.createElement('canvas');
            canvas.style.position = 'fixed';
            canvas.style.inset = '0';
            canvas.style.width = '100vw';
            canvas.style.height = '100vh';
            canvas.style.zIndex = '999999';
            canvas.style.pointerEvents = 'none';
            document.body.appendChild(canvas);
            const ctx = canvas.getContext('2d');
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;

            const particles = [];
            for (let i = 0; i < 90; i++) {
                particles.push({
                    x: canvas.width / 2,
                    y: canvas.height * 0.45,
                    vx: (Math.random() - 0.5) * 16,
                    vy: (Math.random() - 0.75) * 16,
                    size: Math.random() * 8 + 4,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    rot: Math.random() * 360,
                    vRot: (Math.random() - 0.5) * 10,
                    alpha: 1
                });
            }

            let start = null;
            function anim(ts) {
                if (!start) start = ts;
                const progress = (ts - start) / 2000;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                particles.forEach(p => {
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += 0.35; // gravity
                    p.rot += p.vRot;
                    p.alpha = Math.max(0, 1 - progress);
                    ctx.save();
                    ctx.globalAlpha = p.alpha;
                    ctx.translate(p.x, p.y);
                    ctx.rotate((p.rot * Math.PI) / 180);
                    ctx.fillStyle = p.color;
                    ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6);
                    ctx.restore();
                });

                if (progress < 1) {
                    requestAnimationFrame(anim);
                } else {
                    canvas.remove();
                }
            }
            requestAnimationFrame(anim);
        }
    }"
    @evaluation-submitted.window="if ({{ $this->isAllCompleted ? 'true' : 'false' }}) { launchConfetti(); }"
    class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6"
>
    @if(!$showForm)
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Student Evaluation Dashboard</h1>
                <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                    @if($this->activeSemester)
                        Active Semester: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->activeSemester->academicYear->name }} - {{ $this->activeSemester->name }}</span>
                    @else
                        No active semester configured.
                    @endif
                </p>
            </div>

            <div>
                @if($this->isEvaluationOpen)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Evaluations Open
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                        <span class="size-2 rounded-full bg-rose-500"></span>
                        Evaluations Closed
                    </span>
                @endif
            </div>
        </div>

        <!-- 100% Evaluation Completion & Proof of Completion Card -->
        @if($this->isAllCompleted && $this->referenceId)
            <div class="bg-gradient-to-br from-emerald-50 via-white to-emerald-50/40 dark:from-emerald-950/40 dark:via-zinc-900 dark:to-emerald-950/20 border-2 border-emerald-500/30 rounded-2xl p-6 sm:p-7 shadow-md relative overflow-hidden">
                <!-- Watermark Background Emblem -->
                <div class="absolute -right-8 -bottom-8 opacity-5 dark:opacity-10 pointer-events-none">
                    <flux:icon icon="check-badge" class="size-64 text-emerald-600" />
                </div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex flex-col gap-2 max-w-xl">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-500 text-white shadow-xs">
                                <flux:icon icon="check-circle" class="size-4" />
                                100% Completed
                            </span>
                            <span class="text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                Proof of Evaluation Completion
                            </span>
                        </div>

                        <h2 class="text-xl sm:text-2xl font-black text-zinc-900 dark:text-zinc-50 tracking-tight mt-1">
                            Thank you, {{ auth()->user()->name }}! 🎉
                        </h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                            You have evaluated all <strong class="text-zinc-900 dark:text-zinc-100 font-bold">{{ $this->enrolledClasses->count() }} enrolled classes</strong> for this semester. Your constructive feedback has been successfully processed and recorded.
                        </p>
                    </div>

                    <!-- Reference ID Badge Container -->
                    <div class="flex flex-col sm:items-end justify-center gap-2 shrink-0 bg-white dark:bg-zinc-800/80 p-4 sm:p-5 rounded-xl border border-emerald-200 dark:border-emerald-800/60 shadow-xs">
                        <div class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Evaluation Reference ID
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="font-mono text-lg sm:text-xl font-black text-[#9b0000] dark:text-[#f89696] tracking-wide select-all">
                                {{ $this->formattedReferenceId }}
                            </span>
                        </div>

                        <div class="flex items-center gap-2 mt-1">
                            <button
                                type="button"
                                @click="copyRef('{{ $this->referenceId }}')"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold transition-all duration-150 cursor-pointer border shadow-2xs"
                                :class="copied 
                                    ? 'bg-emerald-600 border-emerald-600 text-white' 
                                    : 'bg-zinc-100 dark:bg-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-600 border-zinc-300 dark:border-zinc-600 text-zinc-800 dark:text-zinc-200'"
                            >
                                <template x-if="!copied">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon icon="clipboard-document" class="size-3.5" />
                                        <span>Copy Number</span>
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon icon="check" class="size-3.5" />
                                        <span>Copied!</span>
                                    </span>
                                </template>
                            </button>

                            <button
                                type="button"
                                onclick="window.print()"
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 border border-zinc-300 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300 cursor-pointer print:hidden shadow-2xs"
                                title="Print Proof of Evaluation"
                            >
                                <flux:icon icon="printer" class="size-3.5" />
                                <span>Print</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-emerald-200/60 dark:border-emerald-900/60 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                    <span>Student No: <strong class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ auth()->user()->student?->student_number ?: 'N/A' }}</strong></span>
                    <span>Term: <strong class="text-zinc-700 dark:text-zinc-300 font-semibold">{{ $this->activeSemester->name }}</strong></span>
                    <span class="text-emerald-700 dark:text-emerald-400 font-semibold">● Official System Proof of Completion</span>
                </div>
            </div>
        @endif
    @endif

    @if(session()->has('error') && !$showForm)
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-3">
            <flux:icon icon="exclamation-circle" class="size-6 text-rose-600" />
            <div class="text-sm font-semibold">{{ session('error') }}</div>
        </div>
    @endif

    <!-- Content Area -->
    @if($showForm && $selectedTeacherUser)
        <div>
            <div class="mb-4">
                <flux:button variant="ghost" icon="arrow-left" wire:click="$set('showForm', false)">
                    Back to Dashboard
                </flux:button>
            </div>
            
            <livewire:evaluation-form 
                :evaluatee="$selectedTeacherUser" 
                :class="App\Models\AcademicClass::find($selectedClassId)" 
                :evaluationType="'upward_student'" 
                :key="'eval-upward_student-'.$selectedClassId" />
        </div>
    @else
        <div class="grid grid-cols-1 gap-6">
            <flux:card class="p-6">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <flux:heading size="lg">My Enrolled Classes & Professors</flux:heading>
                    @if($this->enrolledClasses->isNotEmpty())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 shadow-2xs">
                            <span class="text-[#9b0000] dark:text-[#f89696] font-extrabold mr-1">{{ $this->evaluatedCount }}/{{ $this->enrolledClasses->count() }}</span> evaluated
                        </span>
                    @endif
                </div>
                
                @if($this->enrolledClasses->isEmpty())
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-2" />
                        <p class="font-medium text-sm">No classes found for this semester.</p>
                    </div>
                @else
                    <div class="overflow-auto max-h-[500px] rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <table class="w-full text-left text-sm min-w-[540px]">
                            <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 dark:text-zinc-400 font-semibold border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-10 shadow-2xs">
                                <tr>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Subject</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                                @foreach($this->enrolledClasses as $class)
                                    @php
                                        $teacherUserId = $class->teacher?->user?->id;
                                        $status = $teacherUserId ? $this->getClassEvaluationStatus($class->id, $teacherUserId) : 'closed';
                                    @endphp
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors duration-150">
                                        <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $class->teacher?->full_name ?: 'N/A' }}
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $class->subject->code }} - {{ $class->subject->name }}
                                        </td>
                                        <td class="px-6 py-4">
                                            @if($status === 'completed')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">
                                                    <flux:icon icon="check-circle" class="size-4" />
                                                    Completed
                                                </span>
                                            @elseif($status === 'processing')
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400 animate-pulse">
                                                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                                    Your evaluation is being processed. Thank you!
                                                </span>
                                            @elseif(!$this->isEvaluationOpen)
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                                    <flux:icon icon="clock" class="size-4" />
                                                    Closed
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400">
                                                    <flux:icon icon="clock" class="size-4" />
                                                    Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            @if($status === 'completed')
                                                <span class="text-xs text-zinc-400 font-semibold">Done</span>
                                            @elseif($status === 'processing')
                                                <span class="text-xs text-zinc-400 font-semibold">Processing</span>
                                            @elseif(!$this->isEvaluationOpen)
                                                <span class="text-xs text-zinc-400">Unavailable</span>
                                            @else
                                                <flux:button 
                                                    size="sm" 
                                                    variant="primary" 
                                                    wire:click="selectClass({{ $class->id }}, {{ $class->teacher->user->id }})">
                                                    Evaluate
                                                </flux:button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </flux:card>
        </div>
    @endif
</div>
