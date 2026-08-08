<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Subject;
use App\Models\AcademicClass;
use App\Models\Semester;
use App\Models\Employee;
use Illuminate\Support\Carbon;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Form properties for Subject CRUD
    public string $code = '';
    public string $name = '';
    public string $year_level = '';
    public string $semester_offered = '';
    
    // Modal states
    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public bool $showClassesModal = false;
    public bool $showQuickClassModal = false;

    // Selected models
    public ?Subject $editingSubject = null;
    public ?Subject $deletingSubject = null;
    public ?Subject $viewingSubject = null;
    public ?Subject $selectedSubjectForClass = null;

    // Filter & Search properties
    public string $search = '';
    public string $yearFilter = '';
    public string $semesterFilter = '';
    public string $usageFilter = '';
    public string $sortBy = 'code_asc';

    // Quick class creation properties
    public string $quick_teacher_id = '';
    public string $quick_semester_id = '';
    public string $quick_section = '';
    public string $quick_schedule = '';
    public string $quick_schedule_days = '';
    public string $quick_schedule_start_time = '';
    public string $quick_schedule_end_time = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedYearFilter() { $this->resetPage(); }
    public function updatedSemesterFilter() { $this->resetPage(); }
    public function updatedUsageFilter() { $this->resetPage(); }
    public function updatedSortBy() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'yearFilter', 'semesterFilter', 'usageFilter']);
        $this->sortBy = 'code_asc';
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset(['code', 'name', 'year_level', 'semester_offered', 'editingSubject']);
        $this->showModal = true;
    }

    public function createSubject()
    {
        $this->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'year_level' => 'nullable|integer|between:1,4',
            'semester_offered' => 'nullable|string|max:50',
        ]);

        Subject::create([
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'year_level' => $this->year_level !== '' ? (int)$this->year_level : null,
            'semester_offered' => $this->semester_offered ?: null,
            'units' => 3,
        ]);

        $this->showModal = false;
        \Flux::toast(
            heading: 'Subject Created',
            text: 'The subject has been successfully created.',
            variant: 'success'
        );
    }

    public function editSubject(Subject $subject)
    {
        $this->editingSubject = $subject;
        $this->code = $subject->code;
        $this->name = $subject->name;
        $this->year_level = $subject->year_level ? (string)$subject->year_level : '';
        $this->semester_offered = $subject->semester_offered ?? '';
        $this->showModal = true;
    }

    public function updateSubject()
    {
        $this->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'year_level' => 'nullable|integer|between:1,4',
            'semester_offered' => 'nullable|string|max:50',
        ]);

        $this->editingSubject->update([
            'code' => strtoupper(trim($this->code)),
            'name' => trim($this->name),
            'year_level' => $this->year_level !== '' ? (int)$this->year_level : null,
            'semester_offered' => $this->semester_offered ?: null,
        ]);

        $this->showModal = false;
        \Flux::toast(
            heading: 'Subject Updated',
            text: 'The subject has been successfully updated.',
            variant: 'success'
        );
    }

    public function confirmDelete(Subject $subject)
    {
        $this->deletingSubject = $subject;
        $this->showDeleteModal = true;
    }

    public function deleteSubject()
    {
        if (!$this->deletingSubject) return;

        $subjectToDelete = $this->deletingSubject;

        // Check if subject has classes
        if ($subjectToDelete->classes()->exists()) {
            \Flux::toast(
                heading: 'Cannot Delete Subject',
                text: 'This subject has classes associated with it. Delete the classes first.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            $this->deletingSubject = null;
            return;
        }

        // Reset properties before delete to prevent Livewire dehydration 404
        $this->deletingSubject = null;
        $this->editingSubject = null;
        $this->showDeleteModal = false;

        $subjectToDelete->delete();

        \Flux::toast(
            heading: 'Subject Deleted',
            text: 'The subject has been successfully deleted.',
            variant: 'success'
        );
    }

    public function viewClasses(Subject $subject)
    {
        $this->viewingSubject = $subject->load(['classes.semester.academicYear', 'classes.teacher', 'classes.students']);
        $this->showClassesModal = true;
    }

    public function closeClassesModal()
    {
        $this->showClassesModal = false;
        $this->viewingSubject = null;
    }

    // Shortcut: Create Class for Subject
    public function prepareCreateClassForSubject(Subject $subject)
    {
        $this->selectedSubjectForClass = $subject;
        $this->reset(['quick_teacher_id', 'quick_semester_id', 'quick_section', 'quick_schedule', 'quick_schedule_days', 'quick_schedule_start_time', 'quick_schedule_end_time']);
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            $this->quick_semester_id = (string)$activeSemester->id;
        }
        $this->showQuickClassModal = true;
    }

    public function quickCreateClass()
    {
        if (!$this->selectedSubjectForClass) return;

        $this->validate([
            'quick_teacher_id' => 'required|exists:employees,id',
            'quick_semester_id' => 'required|exists:semesters,id',
            'quick_section' => 'required|string|max:255',
            'quick_schedule' => 'nullable|string|max:255',
            'quick_schedule_days' => 'nullable|string',
            'quick_schedule_start_time' => 'nullable|string',
            'quick_schedule_end_time' => 'nullable|string',
        ]);

        if ($this->quick_schedule_days && $this->quick_schedule_start_time && $this->quick_schedule_end_time) {
            try {
                $startStr = Carbon::parse($this->quick_schedule_start_time)->format('h:i A');
                $endStr = Carbon::parse($this->quick_schedule_end_time)->format('h:i A');
                $this->quick_schedule = $this->quick_schedule_days . ' ' . $startStr . ' - ' . $endStr;
            } catch (\Exception $e) {}
        }

        AcademicClass::create([
            'subject_id' => $this->selectedSubjectForClass->id,
            'teacher_id' => $this->quick_teacher_id,
            'semester_id' => $this->quick_semester_id,
            'section' => strtoupper($this->quick_section),
            'schedule' => $this->quick_schedule ?: null,
        ]);

        $this->showQuickClassModal = false;
        \Flux::toast(
            heading: 'Class Created',
            text: 'A new section class for ' . $this->selectedSubjectForClass->code . ' has been created.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        // 1. Calculate Summary Statistics
        $totalSubjects = Subject::count();
        $unassignedSubjectsCount = Subject::doesntHave('classes')->count();

        $activeSem = Semester::where('is_active', true)->first();
        $activeClassesCount = $activeSem 
            ? AcademicClass::where('semester_id', $activeSem->id)->count() 
            : AcademicClass::count();

        // 2. Query Subjects with filters and sorting
        $query = Subject::query()->withCount('classes');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->yearFilter !== '') {
            $query->where('year_level', (int)$this->yearFilter);
        }

        if ($this->semesterFilter) {
            $query->where('semester_offered', $this->semesterFilter);
        }

        if ($this->usageFilter === 'assigned') {
            $query->has('classes');
        } elseif ($this->usageFilter === 'unassigned') {
            $query->doesntHave('classes');
        }

        match ($this->sortBy) {
            'code_desc' => $query->orderBy('code', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'year_asc' => $query->orderBy('year_level', 'asc')->orderBy('code', 'asc'),
            'classes_desc' => $query->orderBy('classes_count', 'desc')->orderBy('code', 'asc'),
            default => $query->orderBy('code', 'asc'),
        };

        // Option lists for modals
        $teachersList = Employee::whereIn('role', ['faculty', 'program head'])->orderBy('last_name')->get();
        $semestersList = Semester::with('academicYear')->orderBy('id', 'desc')->get();

        return [
            'subjects' => $query->paginate(10),
            'totalSubjects' => $totalSubjects,
            'unassignedSubjectsCount' => $unassignedSubjectsCount,
            'activeClassesCount' => $activeClassesCount,
            'activeSemester' => $activeSem,
            'teachersList' => $teachersList,
            'semestersList' => $semestersList,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-8">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Manage Subjects</flux:heading>
            <flux:subheading class="text-left">Curriculum catalog, year level curriculum placement, semester offerings, and section class assignments.</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Subject</flux:button>
    </div>

    <!-- Top Row Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Card 1: Total Subjects -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Subjects</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalSubjects" /></span>
                </div>
                <flux:icon name="book-open" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Active curriculum catalog items</span>
        </div>

        <!-- Card 2: Active Classes Assigned -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Active Classes Assigned</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$activeClassesCount" /></span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">
                {{ $activeSemester ? 'Classes in ' . $activeSemester->name : 'Total class assignments' }}
            </span>
        </div>

        <!-- Card 3: Unassigned Subjects -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Unassigned Subjects</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$unassignedSubjectsCount" /></span>
                </div>
                <flux:icon name="exclamation-triangle" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Subjects with 0 active section classes</span>
        </div>
    </div>
    
    <!-- Search & Advanced Filter Controls Bar -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 w-full">
            <!-- Search Input Bar -->
            <div class="flex-1 min-w-[220px]">
                <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by code or subject name..." />
            </div>

            <!-- Filter Dropdowns Grid (2x2 on mobile/tablet, 4-across on desktop) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 flex-1 items-center">
                <!-- Year Level Filter -->
                <div>
                    <flux:select wire:model.live="yearFilter" class="w-full" placeholder="All Year Levels">
                        <flux:select.option value="">All Year Levels</flux:select.option>
                        <flux:select.option value="1">1st Year</flux:select.option>
                        <flux:select.option value="2">2nd Year</flux:select.option>
                        <flux:select.option value="3">3rd Year</flux:select.option>
                        <flux:select.option value="4">4th Year</flux:select.option>
                    </flux:select>
                </div>

                <!-- Semester Offered Filter -->
                <div>
                    <flux:select wire:model.live="semesterFilter" class="w-full" placeholder="All Semesters">
                        <flux:select.option value="">All Semesters</flux:select.option>
                        <flux:select.option value="1st Semester">1st Semester</flux:select.option>
                        <flux:select.option value="2nd Semester">2nd Semester</flux:select.option>
                        <flux:select.option value="Summer">Summer</flux:select.option>
                        <flux:select.option value="Both">Both / Any Semester</flux:select.option>
                    </flux:select>
                </div>

                <!-- Usage Filter -->
                <div>
                    <flux:select wire:model.live="usageFilter" class="w-full" placeholder="Filter Usage">
                        <flux:select.option value="">All Usage Status</flux:select.option>
                        <flux:select.option value="assigned">Assigned to Classes</flux:select.option>
                        <flux:select.option value="unassigned">Unassigned Subjects</flux:select.option>
                    </flux:select>
                </div>

                <!-- Sort By -->
                <div>
                    <flux:select wire:model.live="sortBy" class="w-full">
                        <flux:select.option value="code_asc">Code (A to Z)</flux:select.option>
                        <flux:select.option value="code_desc">Code (Z to A)</flux:select.option>
                        <flux:select.option value="name_asc">Name (A to Z)</flux:select.option>
                        <flux:select.option value="year_asc">Year Level (1st to 4th)</flux:select.option>
                        <flux:select.option value="classes_desc">Most Classes</flux:select.option>
                    </flux:select>
                </div>
            </div>

            <!-- Reset Button -->
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset All Filters" class="shrink-0 self-end lg:self-center" />
        </div>
    </div>
    
    <!-- Skeleton Loading State -->
    <div wire:loading wire:target="search, yearFilter, semesterFilter, usageFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <!-- Main Subjects Table -->
    <div wire:loading.remove wire:target="search, yearFilter, semesterFilter, usageFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[15%] px-4 py-3.5">Code</th>
                        <th class="w-[38%] px-4 py-3.5">Subject Name</th>
                        <th class="w-[15%] px-4 py-3.5">Year Level</th>
                        <th class="w-[15%] px-4 py-3.5">Semester Offered</th>
                        <th class="w-[10%] px-4 py-3.5 text-center">Assigned Classes</th>
                        <th class="w-[7%] px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($subjects as $subject)
                        <tr wire:key="{{ $subject->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Code -->
                            <td class="px-4 py-3.5 font-mono text-xs font-bold text-[#800000] dark:text-red-400">
                                {{ $subject->code }}
                            </td>

                            <!-- Name -->
                            <td class="px-4 py-3.5 dark:text-zinc-200 font-semibold truncate" title="{{ $subject->name }}">
                                {{ $subject->name }}
                            </td>

                            <!-- Year Level -->
                            <td class="px-4 py-3.5 text-xs">
                                @if($subject->year_level)
                                    <flux:badge size="sm" color="amber" class="font-bold">
                                        {{ $subject->year_level }}{{ match($subject->year_level) { 1 => 'st', 2 => 'nd', 3 => 'rd', 4 => 'th', default => 'th' } }} Year
                                    </flux:badge>
                                @else
                                    <span class="text-zinc-400 italic">All Years</span>
                                @endif
                            </td>

                            <!-- Semester Offered -->
                            <td class="px-4 py-3.5 text-xs">
                                @if($subject->semester_offered)
                                    <flux:badge size="sm" color="blue" class="font-medium">
                                        {{ $subject->semester_offered }}
                                    </flux:badge>
                                @else
                                    <span class="text-zinc-400 italic">Any Term</span>
                                @endif
                            </td>

                            <!-- Assigned Classes Count -->
                            <td class="px-4 py-3.5 text-center">
                                <button type="button" wire:click="viewClasses({{ $subject->id }})" class="inline-flex items-center gap-1 hover:opacity-80 transition-opacity" title="Click to view assigned section classes">
                                    @if($subject->classes_count > 0)
                                        <flux:badge size="sm" color="indigo" class="cursor-pointer font-bold">
                                            {{ $subject->classes_count }} {{ Str::plural('class', $subject->classes_count) }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc" class="cursor-pointer">
                                            0 classes
                                        </flux:badge>
                                    @endif
                                </button>
                            </td>

                            <!-- Action Column -->
                            <td class="px-4 py-3.5 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="eye" wire:click="viewClasses({{ $subject->id }})">
                                            View Assigned Classes
                                        </flux:menu.item>

                                        <flux:menu.item icon="plus-circle" wire:click="prepareCreateClassForSubject({{ $subject->id }})">
                                            Create Class for Subject
                                        </flux:menu.item>

                                        <flux:menu.item icon="pencil-square" wire:click="editSubject({{ $subject->id }})">
                                            Edit Details
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $subject->id }})">
                                            Delete Subject
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon name="book-open" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-base font-semibold text-zinc-700 dark:text-zinc-300">No subjects found</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No subjects match your current search or filter criteria.</p>
                                    <flux:button size="sm" variant="outline" wire:click="clearFilters" class="mt-2">
                                        Reset Filters
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $subjects->links() }}
        </div>
    </div>

    <!-- Create/Edit Subject Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <flux:heading size="lg">{{ $editingSubject ? 'Edit Subject' : 'Create Subject' }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
            </div>
            
            <form wire:submit="{{ $editingSubject ? 'updateSubject' : 'createSubject' }}" class="flex flex-col gap-4">
                <flux:input wire:model="code" label="Subject Code" type="text" placeholder="e.g. CS101" required />
                <flux:input wire:model="name" label="Subject Name" type="text" placeholder="e.g. Introduction to Computer Science" required />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Year Level -->
                    <flux:select wire:model="year_level" label="Year Level Placement (Optional)">
                        <flux:select.option value="">All Year Levels</flux:select.option>
                        <flux:select.option value="1">1st Year</flux:select.option>
                        <flux:select.option value="2">2nd Year</flux:select.option>
                        <flux:select.option value="3">3rd Year</flux:select.option>
                        <flux:select.option value="4">4th Year</flux:select.option>
                    </flux:select>

                    <!-- Semester Offered -->
                    <flux:select wire:model="semester_offered" label="Semester Offered (Optional)">
                        <flux:select.option value="">Any Semester</flux:select.option>
                        <flux:select.option value="1st Semester">1st Semester</flux:select.option>
                        <flux:select.option value="2nd Semester">2nd Semester</flux:select.option>
                        <flux:select.option value="Summer">Summer</flux:select.option>
                        <flux:select.option value="Both">Both Semesters</flux:select.option>
                    </flux:select>
                </div>

                <div class="flex justify-end gap-2 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingSubject ? 'Save Changes' : 'Create Subject' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Quick Create Class Modal Shortcut -->
    @if($showQuickClassModal && $selectedSubjectForClass)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] space-y-4">
            <div class="flex justify-between items-start border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div>
                    <span class="text-xs font-mono font-bold text-[#800000] dark:text-red-400 uppercase tracking-wider block">
                        {{ $selectedSubjectForClass->code }}
                    </span>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        Create Section Class for "{{ $selectedSubjectForClass->name }}"
                    </h3>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showQuickClassModal', false)" />
            </div>
            
            <form wire:submit="quickCreateClass" class="flex flex-col gap-4">
                <x-searchable-select 
                    name="quick_teacher_id" 
                    label="Professor" 
                    placeholder="Select Professor" 
                    required 
                    :options="array_merge([['value' => '', 'label' => 'Select Professor']], $teachersList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->formatted_name ?? $t->full_name])->toArray())" 
                />

                <flux:select wire:model="quick_semester_id" label="Semester" required>
                    <flux:select.option value="">Select Semester</flux:select.option>
                    @foreach($semestersList as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="quick_section" label="Section Code" type="text" placeholder="e.g. BSCS-3A" required />

                <!-- Schedule Date & Time Picker Section -->
                <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex flex-col gap-3">
                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Class Schedule Picker</span>
                    
                    <flux:select wire:model="quick_schedule_days" label="Schedule Days">
                        <flux:select.option value="">Select Days</flux:select.option>
                        <flux:select.option value="MW">Monday & Wednesday (MW)</flux:select.option>
                        <flux:select.option value="TTH">Tuesday & Thursday (TTH)</flux:select.option>
                        <flux:select.option value="FS">Friday & Saturday (FS)</flux:select.option>
                        <flux:select.option value="MWF">Mon / Wed / Fri (MWF)</flux:select.option>
                        <flux:select.option value="MON">Monday Only</flux:select.option>
                        <flux:select.option value="TUE">Tuesday Only</flux:select.option>
                        <flux:select.option value="WED">Wednesday Only</flux:select.option>
                        <flux:select.option value="THU">Thursday Only</flux:select.option>
                        <flux:select.option value="FRI">Friday Only</flux:select.option>
                        <flux:select.option value="SAT">Saturday Only</flux:select.option>
                        <flux:select.option value="SUN">Sunday Only</flux:select.option>
                    </flux:select>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="quick_schedule_start_time" label="Start Time" type="time" />
                        <flux:input wire:model="quick_schedule_end_time" label="End Time" type="time" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                    <flux:button wire:click="$set('showQuickClassModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        Create Class
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Assigned Classes Details Modal -->
    @if($showClassesModal && $viewingSubject)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-2xl w-full max-w-3xl border border-zinc-200 dark:border-zinc-800 space-y-4 overflow-y-auto max-h-[90vh]">
            <div class="flex justify-between items-start border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <span class="text-xs font-mono font-bold text-[#800000] dark:text-red-400 uppercase tracking-wider block">
                        {{ $viewingSubject->code }}
                    </span>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 mt-0.5">
                        Assigned Section Classes for "{{ $viewingSubject->name }}"
                    </h3>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="closeClassesModal" />
            </div>

            @if($viewingSubject->classes->count() > 0)
                <div class="w-full overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Section</th>
                                <th class="px-4 py-3">Academic Period</th>
                                <th class="px-4 py-3">Assigned Faculty</th>
                                <th class="px-4 py-3">Schedule</th>
                                <th class="px-4 py-3 text-center">Enrolled Students</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @foreach($viewingSubject->classes as $cls)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20">
                                    <td class="px-4 py-3 font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ $cls->section }}
                                    </td>
                                    <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $cls->semester?->academicYear?->name ?? 'N/A' }} - {{ $cls->semester?->name ?? 'N/A' }}
                                    </td>
                                    <td class="px-4 py-3 text-xs font-medium text-zinc-800 dark:text-zinc-200">
                                        @if($cls->teacher)
                                            {{ $cls->teacher->formatted_name ?? $cls->teacher->full_name }}
                                        @else
                                            <span class="text-zinc-400 italic">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ $cls->schedule ?: 'No Schedule' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <flux:badge size="sm" color="zinc" class="font-bold">
                                            {{ $cls->students->count() }} {{ Str::plural('student', $cls->students->count()) }}
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="flex flex-col items-center justify-center text-center p-8 bg-zinc-50 dark:bg-zinc-800/30 rounded-xl border border-zinc-200/60 dark:border-zinc-700/60 gap-2">
                    <flux:icon name="academic-cap" class="size-10 text-zinc-300 dark:text-zinc-600" />
                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">No classes assigned yet</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 max-w-sm">This subject is currently unassigned to any section class in the system.</p>
                </div>
            @endif

            <div class="flex justify-between items-center pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="prepareCreateClassForSubject({{ $viewingSubject->id }})" size="sm" variant="outline" icon="plus">
                    Create New Class for this Subject
                </flux:button>
                <flux:button size="sm" variant="primary" wire:click="closeClassesModal">
                    Close
                </flux:button>
            </div>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingSubject)
    <x-confirmation-modal 
        title="Delete Subject" 
        on-confirm="deleteSubject" 
        on-cancel="$set('showDeleteModal', false)" 
        :disabled="$deletingSubject->classes()->exists()"
    >
        Are you sure you want to delete this subject? This action cannot be undone.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Subject Code</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingSubject->code }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Subject Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingSubject->name }}</span>
                </div>
            </div>
        </x-slot:details>

        @if($deletingSubject->classes()->exists())
            <x-slot:warning>
                This subject is currently referenced by {{ $deletingSubject->classes()->count() }} class(es). You cannot delete it until those classes are deleted or re-assigned.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>
