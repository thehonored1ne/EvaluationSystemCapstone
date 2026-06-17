<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Semester;
use App\Models\AcademicClass;
use App\Models\Department;
use App\Models\Evaluation;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }
    public ?int $selectedDepartmentId = null;

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getDepartmentsProperty()
    {
        return Department::orderBy('name')->get();
    }

    public function getClassesProperty()
    {
        $sem = $this->activeSemester;
        if (!$sem) return collect();

        $user = auth()->user();
        $query = AcademicClass::where('semester_id', $sem->id)
            ->with(['subject', 'teacher', 'students']);

        if ($user->hasRole('program head')) {
            $deptId = $user->employee->department_id;
            $query->whereHas('teacher', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            });
        } elseif ($user->hasRole('dean')) {
            // Default to Dean's department, or allow filtering
            $deptId = $this->selectedDepartmentId ?: $user->employee->department_id;
            if ($deptId) {
                $query->whereHas('teacher', function ($q) use ($deptId) {
                    $q->where('department_id', $deptId);
                });
            }
        } elseif ($user->hasRole('admin')) {
            if ($this->selectedDepartmentId) {
                $query->whereHas('teacher', function ($q) {
                    $q->where('department_id', $this->selectedDepartmentId);
                });
            }
        }

        return $query->get()->map(function ($class) {
            $enrolled = $class->students->count();
            $evaluated = Evaluation::where('class_id', $class->id)->count();
            $percentage = $enrolled > 0 ? round(($evaluated / $enrolled) * 100) : 0;

            return (object) [
                'id' => $class->id,
                'subject' => $class->subject,
                'teacher' => $class->teacher,
                'section' => $class->section,
                'enrolled' => $enrolled,
                'evaluated' => $evaluated,
                'percentage' => $percentage,
            ];
        });
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Manage Evaluations</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">
                @if($this->activeSemester)
                    Active Semester: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $this->activeSemester->academicYear->name }} - {{ $this->activeSemester->name }}</span>
                @endif
            </p>
        </div>

        @if(auth()->user()->hasAnyRole(['admin', 'dean']))
            <div class="w-full md:w-64">
                <flux:select wire:model.live="selectedDepartmentId" placeholder="All Departments">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($this->departments as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </div>

    <!-- Stats summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
            $classes = $this->classes;
            $totalClasses = $classes->count();
            $avgProgress = $totalClasses > 0 ? round($classes->sum('percentage') / $totalClasses) : 0;
            $fullyEvaluated = $classes->filter(fn($c) => $c->percentage === 100)->count();
        @endphp
        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Total Classes Monitored</flux:heading>
            <span class="text-3xl font-bold">{{ $totalClasses }}</span>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">Average Completion Rate</flux:heading>
            <div class="flex items-center gap-3">
                <span class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">{{ $avgProgress }}%</span>
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2.5 rounded-full overflow-hidden">
                    <div class="bg-indigo-600 h-2.5 rounded-full" style="width: {{ $avgProgress }}%"></div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6 flex flex-col gap-2">
            <flux:heading size="sm" class="text-zinc-500">100% Completed Classes</flux:heading>
            <span class="text-3xl font-bold text-emerald-600 dark:text-emerald-400">{{ $fullyEvaluated }}</span>
        </flux:card>
    </div>

    <!-- Classes Completion Rates table -->
    <flux:card class="p-6">
        <flux:heading size="lg" class="mb-4">Class Evaluation Progress</flux:heading>

        @if($classes->isEmpty())
            <div class="text-center py-8 text-zinc-500">
                <p class="text-sm">No classes found matching the filters in this semester.</p>
            </div>
        @else
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 font-semibold border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="px-6 py-4">Subject</th>
                            <th class="px-6 py-4">Section</th>
                            <th class="px-6 py-4">Professor</th>
                            <th class="px-6 py-4">Evaluated / Enrolled</th>
                            <th class="px-6 py-4">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @foreach($classes as $c)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/20 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-zinc-800 dark:text-zinc-200">{{ $c->subject->code }}</div>
                                    <div class="text-xs text-zinc-500">{{ $c->subject->name }}</div>
                                </td>
                                <td class="px-6 py-4 font-semibold text-zinc-700 dark:text-zinc-300">
                                    {{ $c->section }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-zinc-850 dark:text-zinc-150">{{ $c->teacher->full_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $c->teacher->employee_number }}</div>
                                </td>
                                <td class="px-6 py-4 font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $c->evaluated }} / {{ $c->enrolled }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-24 bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full {{ $c->percentage === 100 ? 'bg-emerald-500' : 'bg-indigo-600' }}" style="width: {{ $c->percentage }}%"></div>
                                        </div>
                                        <span class="text-xs font-bold text-zinc-600 dark:text-zinc-400">{{ $c->percentage }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
