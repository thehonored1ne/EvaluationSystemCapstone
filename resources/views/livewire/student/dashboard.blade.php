<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\User;
use App\Models\Evaluation;

new #[Layout('components.layouts.app')] class extends Component {
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

        $student = auth()->user()->student;
        if (!$student) return collect();

        return AcademicClass::where('semester_id', $sem->id)
            ->whereHas('students', function ($q) use ($student) {
                $q->where('students.id', $student->id);
            })
            ->with(['subject', 'teacher.user'])
            ->get();
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

<div class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6">
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
                <flux:heading size="lg" class="mb-4">My Enrolled Classes & Professors</flux:heading>
                
                @if($this->enrolledClasses->isEmpty())
                    <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                        <flux:icon icon="academic-cap" class="size-12 mx-auto text-zinc-300 mb-2" />
                        <p class="font-medium text-sm">No classes found for this semester.</p>
                    </div>
                @else
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 dark:text-zinc-400 font-semibold border-b border-zinc-200 dark:border-zinc-800">
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
                                        $status = $this->getClassEvaluationStatus($class->id, $class->teacher->user->id);
                                    @endphp
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors duration-150">
                                        <td class="px-6 py-4 font-semibold text-zinc-800 dark:text-zinc-200">
                                            {{ $class->teacher->full_name }}
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
