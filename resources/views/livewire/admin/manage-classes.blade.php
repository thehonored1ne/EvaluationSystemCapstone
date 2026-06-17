<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Department;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    use WithPagination;

    // Filter properties
    public string $search = '';
    public string $filterSemester = '';
    public string $filterDepartment = '';

    // Class CRUD properties
    public string $subject_id = '';
    public string $teacher_id = '';
    public string $semester_id = '';
    public string $section = '';
    public string $schedule = '';
    public string $room = '';
    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public ?AcademicClass $editingClass = null;
    public ?AcademicClass $deletingClass = null;

    // Enrollment properties
    public bool $showEnrollmentModal = false;
    public ?AcademicClass $managingClass = null;
    public string $studentSearch = '';
    public array $enrolledStudentIds = [];

    public function mount()
    {
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            $this->filterSemester = (string)$activeSemester->id;
        }
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedFilterSemester() { $this->resetPage(); }
    public function updatedFilterDepartment() { $this->resetPage(); }
    public function updatedStudentSearch() { }

    public function clearFilters()
    {
        $this->reset(['search', 'filterDepartment']);
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            $this->filterSemester = (string)$activeSemester->id;
        } else {
            $this->filterSemester = '';
        }
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset(['subject_id', 'teacher_id', 'semester_id', 'section', 'schedule', 'room', 'editingClass']);
        $activeSemester = Semester::where('is_active', true)->first();
        if ($activeSemester) {
            $this->semester_id = (string)$activeSemester->id;
        }
        $this->showModal = true;
    }

    public function createClass()
    {
        $this->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:employees,id',
            'semester_id' => 'required|exists:semesters,id',
            'section' => 'required|string|max:255',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:255',
        ]);

        AcademicClass::create([
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'semester_id' => $this->semester_id,
            'section' => strtoupper($this->section),
            'schedule' => $this->schedule ?: null,
            'room' => $this->room ?: null,
        ]);

        $this->showModal = false;
        \Flux::toast(
            heading: 'Class Created',
            text: 'The academic class has been successfully created.',
            variant: 'success'
        );
    }

    public function editClass(AcademicClass $class)
    {
        $this->editingClass = $class;
        $this->subject_id = $class->subject_id;
        $this->teacher_id = $class->teacher_id;
        $this->semester_id = $class->semester_id;
        $this->section = $class->section;
        $this->schedule = $class->schedule ?? '';
        $this->room = $class->room ?? '';
        $this->showModal = true;
    }

    public function updateClass()
    {
        $this->validate([
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:employees,id',
            'semester_id' => 'required|exists:semesters,id',
            'section' => 'required|string|max:255',
            'schedule' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:255',
        ]);

        $this->editingClass->update([
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'semester_id' => $this->semester_id,
            'section' => strtoupper($this->section),
            'schedule' => $this->schedule ?: null,
            'room' => $this->room ?: null,
        ]);

        $this->showModal = false;
        \Flux::toast(
            heading: 'Class Updated',
            text: 'The academic class has been successfully updated.',
            variant: 'success'
        );
    }

    public function confirmDelete(AcademicClass $class)
    {
        $this->deletingClass = $class;
        $this->showDeleteModal = true;
    }

    public function deleteClass()
    {
        if (!$this->deletingClass) return;

        // Check if class has evaluations
        if ($this->deletingClass->evaluations()->exists()) {
            \Flux::toast(
                heading: 'Cannot Delete Class',
                text: 'This class has evaluation submissions associated with it. Delete the evaluations first.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            return;
        }

        $this->deletingClass->delete();
        $this->showDeleteModal = false;
        $this->deletingClass = null;

        \Flux::toast(
            heading: 'Class Deleted',
            text: 'The academic class has been successfully deleted.',
            variant: 'success'
        );
    }

    // Enrollment actions
    public function manageStudents(AcademicClass $class)
    {
        $this->managingClass = $class;
        $this->studentSearch = '';
        $this->updateEnrolledList();
        $this->showEnrollmentModal = true;
    }

    public function updateEnrolledList()
    {
        if ($this->managingClass) {
            // Refresh model relations
            $this->managingClass->load('students.program.department');
            $this->enrolledStudentIds = $this->managingClass->students->pluck('id')->toArray();
        }
    }

    public function enrollStudent($studentId)
    {
        if (!$this->managingClass) return;

        try {
            $this->managingClass->students()->attach($studentId);
            $this->updateEnrolledList();
            \Flux::toast(
                heading: 'Student Enrolled',
                text: 'The student was added to this class.',
                variant: 'success'
            );
        } catch (\Exception $e) {
            // Ignore duplicate attachment exception
        }
    }

    public function unenrollStudent($studentId)
    {
        if (!$this->managingClass) return;

        $this->managingClass->students()->detach($studentId);
        $this->updateEnrolledList();

        \Flux::toast(
            heading: 'Student Removed',
            text: 'The student was removed from this class.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        $query = AcademicClass::query()
            ->with(['subject', 'teacher', 'semester.academicYear'])
            ->withCount('students');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('section', 'like', '%' . $this->search . '%')
                  ->orWhereHas('subject', function ($sub) {
                      $sub->where('code', 'like', '%' . $this->search . '%')
                          ->orWhere('name', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('teacher', function ($emp) {
                      $emp->where('first_name', 'like', '%' . $this->search . '%')
                          ->orWhere('last_name', 'like', '%' . $this->search . '%')
                          ->orWhere('employee_number', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if ($this->filterSemester) {
            $query->where('semester_id', $this->filterSemester);
        }

        if ($this->filterDepartment) {
            $query->whereHas('teacher', function ($q) {
                $q->where('department_id', $this->filterDepartment);
            });
        }

        // Fetch subjects, teachers, semesters, departments for dropdowns/filters
        $subjectsList = Subject::orderBy('code')->get();
        $teachersList = Employee::whereIn('role', ['faculty', 'program head'])->orderBy('last_name')->get();
        $semestersList = Semester::with('academicYear')->orderBy('id', 'desc')->get();
        $departmentsList = Department::orderBy('code')->get();

        // Search students for enrollment modal
        $studentSearchResults = [];
        if ($this->showEnrollmentModal && strlen(trim($this->studentSearch)) >= 2) {
            $searchStr = '%' . $this->studentSearch . '%';
            $studentSearchResults = Student::query()
                ->with('program.department')
                ->where(function ($q) use ($searchStr) {
                    $q->where('first_name', 'like', $searchStr)
                      ->orWhere('last_name', 'like', $searchStr)
                      ->orWhere('student_number', 'like', $searchStr);
                })
                ->whereNotIn('id', $this->enrolledStudentIds)
                ->limit(10)
                ->get();
        }

        return [
            'classes' => $query->orderBy('id', 'desc')->paginate(10),
            'subjectsList' => $subjectsList,
            'teachersList' => $teachersList,
            'semestersList' => $semestersList,
            'departmentsList' => $departmentsList,
            'studentSearchResults' => $studentSearchResults,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl" level="1">Manage Classes & Enrollment</flux:heading>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Class</flux:button>
    </div>
    
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by subject code/name, teacher name or section..." />
        </div>

        <div class="w-full md:w-56">
            <flux:select wire:model.live="filterSemester" placeholder="Filter Semester">
                <flux:select.option value="">All Semesters</flux:select.option>
                @foreach($semestersList as $sem)
                    <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-56">
            <flux:select wire:model.live="filterDepartment" placeholder="Filter Department">
                <flux:select.option value="">All Departments</flux:select.option>
                @foreach($departmentsList as $dept)
                    <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        
        <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
    </div>
    
    <div wire:loading wire:target="search, filterSemester, filterDepartment, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="7" />
    </div>

    <div wire:loading.remove wire:target="search, filterSemester, filterDepartment, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th class="w-[18%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Subject</th>
                        <th class="w-[18%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Professor</th>
                        <th class="w-[8%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Section</th>
                        <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Schedule & Room</th>
                        <th class="w-[13%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Semester</th>
                        <th class="w-[8%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-center">Students</th>
                        <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($classes as $class)
                        <tr wire:key="{{ $class->id }}">
                            <td class="px-4 py-3 dark:text-zinc-300">
                                <span class="font-mono text-xs font-semibold block text-zinc-500">{{ $class->subject->code }}</span>
                                <span class="font-medium text-sm block truncate" title="{{ $class->subject->name }}">{{ $class->subject->name }}</span>
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-300">
                                <span class="text-xs block text-zinc-500">{{ $class->teacher->employee_number }}</span>
                                <span class="font-medium text-sm block truncate" title="{{ $class->teacher->full_name }}">{{ $class->teacher->full_name }}</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-xs font-mono dark:text-zinc-300">{{ $class->section }}</td>
                            <td class="px-4 py-3 dark:text-zinc-300 text-xs">
                                <span class="block truncate" title="{{ $class->schedule }}">{{ $class->schedule ?: 'No Schedule' }}</span>
                                <span class="block text-zinc-500 font-mono" title="{{ $class->room }}">{{ $class->room ?: 'No Room' }}</span>
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-400 text-xs">
                                {{ $class->semester->academicYear->name }} - {{ $class->semester->name }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" color="{{ $class->students_count > 0 ? 'indigo' : 'zinc' }}">
                                    {{ $class->students_count }} Enrolled
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-left">
                                <div class="flex justify-start gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="manageStudents({{ $class->id }})" tooltip="Manage Enrollment">
                                        View
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="editClass({{ $class->id }})">
                                        Edit
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" class="text-red-500 hover:text-red-600 dark:hover:text-red-400" wire:click="confirmDelete({{ $class->id }})">
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
                                No academic classes found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $classes->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh]">
            <flux:heading size="lg" class="mb-4">{{ $editingClass ? 'Edit Class' : 'Create Class' }}</flux:heading>
            
            <form wire:submit="{{ $editingClass ? 'updateClass' : 'createClass' }}" class="flex flex-col gap-4">
                <x-searchable-select 
                    name="subject_id" 
                    label="Subject" 
                    placeholder="Select Subject" 
                    required 
                    :options="array_merge([['value' => '', 'label' => 'Select Subject']], $subjectsList->map(fn($subj) => ['value' => (string)$subj->id, 'label' => $subj->code . ' - ' . $subj->name])->toArray())" 
                />

                <x-searchable-select 
                    name="teacher_id" 
                    label="Professor" 
                    placeholder="Select Professor" 
                    required 
                    :options="array_merge([['value' => '', 'label' => 'Select Professor']], $teachersList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->employee_number . ' - ' . $t->full_name])->toArray())" 
                />

                <flux:select wire:model="semester_id" label="Semester" required>
                    <flux:select.option value="">Select Semester</flux:select.option>
                    @foreach($semestersList as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="section" label="Section" type="text" placeholder="e.g. BSCS-3A" required />
                    <flux:input wire:model="room" label="Room" type="text" placeholder="e.g. Lab 1, Rm 401" />
                </div>

                <flux:input wire:model="schedule" label="Schedule" type="text" placeholder="e.g. Mon/Wed 9:00 AM - 10:30 AM" />

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingClass)
    <x-confirmation-modal 
        title="Delete Class" 
        on-confirm="deleteClass" 
        on-cancel="$set('showDeleteModal', false)" 
        :disabled="$deletingClass->evaluations()->exists()"
    >
        Are you sure you want to delete this academic class? This action cannot be undone.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Subject</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->subject->code }} - {{ $deletingClass->subject->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Section</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->section }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Professor</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->teacher->full_name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Schedule & Room</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ $deletingClass->schedule ?: 'N/A' }} 
                        @if($deletingClass->room) ({{ $deletingClass->room }}) @endif
                    </span>
                </div>
            </div>
        </x-slot:details>

        @if($deletingClass->evaluations()->exists())
            <x-slot:warning>
                This class already has submitted evaluations. You cannot delete it until those evaluations are removed.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif

    <!-- Student Enrollment Modal -->
    @if($showEnrollmentModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-2xl border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh] flex flex-col gap-6">
            <div class="flex justify-between items-start">
                <div>
                    <flux:heading size="lg">Student Enrollment</flux:heading>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">
                        Class: <span class="font-semibold">{{ $managingClass?->subject->code }} - {{ $managingClass?->subject->name }}</span> | Section: <span class="font-semibold">{{ $managingClass?->section }}</span>
                    </p>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showEnrollmentModal', false)" />
            </div>

            <!-- Enrolled Students List -->
            <div class="flex flex-col gap-2">
                <flux:heading size="sm" class="font-semibold">Enrolled Students ({{ count($enrolledStudentIds) }})</flux:heading>
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg max-h-60 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($managingClass?->students ?? [] as $student)
                        <div class="flex justify-between items-center px-4 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-sm">
                            <div>
                                <span class="font-medium dark:text-zinc-300 block">{{ $student->full_name }}</span>
                                <span class="font-mono text-xs text-zinc-500">{{ $student->student_number }} | Year {{ $student->year_level }} | {{ $student->program?->code }}</span>
                            </div>
                            <flux:button size="xs" variant="ghost" class="text-red-500 hover:text-red-600" wire:click="unenrollStudent({{ $student->id }})">
                                Remove
                            </flux:button>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-xs text-gray-500 dark:text-zinc-400">
                            No students enrolled in this class yet.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Search and Add Students -->
            <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <flux:heading size="sm" class="font-semibold">Enroll New Students</flux:heading>
                <flux:input wire:model.live.debounce.300ms="studentSearch" placeholder="Search students by name or student number (min 2 chars)..." icon="magnifying-glass" />

                @if(strlen(trim($studentSearch)) >= 2)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg max-h-48 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700 bg-zinc-50/50 dark:bg-zinc-900/50">
                        @forelse($studentSearchResults as $student)
                            <div class="flex justify-between items-center px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-850 text-sm">
                                <div>
                                    <span class="font-medium dark:text-zinc-300 block">{{ $student->full_name }}</span>
                                    <span class="font-mono text-xs text-zinc-500">{{ $student->student_number }} | Year {{ $student->year_level }} | {{ $student->program?->code }}</span>
                                </div>
                                <flux:button size="xs" variant="primary" wire:click="enrollStudent({{ $student->id }})">
                                    Enroll
                                </flux:button>
                            </div>
                        @empty
                            <div class="px-4 py-6 text-center text-xs text-gray-500 dark:text-zinc-400">
                                No matching unenrolled students found.
                            </div>
                        @endforelse
                    </div>
                @else
                    <div class="text-center py-4 text-xs text-gray-400 dark:text-zinc-500">
                        Type at least 2 characters to search for students to enroll.
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 dark:border-zinc-700 pt-4">
                <flux:button wire:click="$set('showEnrollmentModal', false)">Close</flux:button>
            </div>
        </div>
    </div>
    @endif
</div>
