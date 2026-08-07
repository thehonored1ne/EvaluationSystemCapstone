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
use Illuminate\Support\Carbon;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Filter properties
    public string $search = '';
    public string $filterSemester = '';
    public string $filterDepartment = '';
    public string $filterSubject = '';
    public string $filterTeacher = '';

    // Class CRUD properties
    public string $subject_id = '';
    public string $teacher_id = '';
    public string $semester_id = '';
    public string $section = '';
    public string $schedule = '';
    public string $schedule_days = '';
    public string $schedule_start_time = '';
    public string $schedule_end_time = '';
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
    public function updatedFilterSubject() { $this->resetPage(); }
    public function updatedFilterTeacher() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'filterDepartment', 'filterSubject', 'filterTeacher']);
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
        $this->reset(['subject_id', 'teacher_id', 'semester_id', 'section', 'schedule', 'schedule_days', 'schedule_start_time', 'schedule_end_time', 'room', 'editingClass']);
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
            'schedule_days' => 'nullable|string',
            'schedule_start_time' => 'nullable|string',
            'schedule_end_time' => 'nullable|string',
        ]);

        // Construct schedule from day and time pickers if provided
        if ($this->schedule_days && $this->schedule_start_time && $this->schedule_end_time) {
            try {
                $startStr = Carbon::parse($this->schedule_start_time)->format('h:i A');
                $endStr = Carbon::parse($this->schedule_end_time)->format('h:i A');
                $this->schedule = $this->schedule_days . ' ' . $startStr . ' - ' . $endStr;
            } catch (\Exception $e) {
                // Fallback to existing schedule string
            }
        }

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

        // Pre-parse schedule if formatted (e.g. "MW 09:00 AM - 10:30 AM")
        $this->schedule_days = '';
        $this->schedule_start_time = '';
        $this->schedule_end_time = '';

        if ($this->schedule && preg_match('/^([A-Z]+)\s+([0-9]{1,2}:[0-9]{2}\s*(?:AM|PM))\s*-\s*([0-9]{1,2}:[0-9]{2}\s*(?:AM|PM))/i', $this->schedule, $matches)) {
            $this->schedule_days = strtoupper($matches[1]);
            try {
                $this->schedule_start_time = Carbon::parse($matches[2])->format('H:i');
                $this->schedule_end_time = Carbon::parse($matches[3])->format('H:i');
            } catch (\Exception $e) {}
        }

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
            'schedule_days' => 'nullable|string',
            'schedule_start_time' => 'nullable|string',
            'schedule_end_time' => 'nullable|string',
        ]);

        // Construct schedule from day and time pickers if provided
        if ($this->schedule_days && $this->schedule_start_time && $this->schedule_end_time) {
            try {
                $startStr = Carbon::parse($this->schedule_start_time)->format('h:i A');
                $endStr = Carbon::parse($this->schedule_end_time)->format('h:i A');
                $this->schedule = $this->schedule_days . ' ' . $startStr . ' - ' . $endStr;
            } catch (\Exception $e) {
                // Fallback
            }
        }

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

        if ($this->filterSubject) {
            $query->where('subject_id', $this->filterSubject);
        }

        if ($this->filterTeacher) {
            $query->where('teacher_id', $this->filterTeacher);
        }

        // Fetch dropdown options
        $subjectsList = Subject::orderBy('name')->get();
        $teachersList = Employee::whereIn('role', ['faculty', 'program head'])->orderBy('last_name')->get();
        $semestersList = Semester::with('academicYear')->orderBy('id', 'desc')->get();
        $departmentsList = Department::orderBy('name')->get();

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
        <div>
            <flux:heading size="xl" level="1">Manage Classes & Enrollment</flux:heading>
            <flux:subheading class="text-left mt-1">Class section allocations, professor assignments, student enrollment, and scheduling.</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Class</flux:button>
    </div>
    
    <!-- Advanced Search & Filter Controls Bar -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <!-- Search Input Bar -->
        <div class="flex items-center gap-3 w-full">
            <div class="flex-1">
                <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by section, subject or professor..." />
            </div>
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" class="shrink-0" />
        </div>

        <!-- Filter Dropdowns Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 w-full">
            <!-- Filter Semester -->
            <div>
                <flux:select wire:model.live="filterSemester" class="w-full" placeholder="Filter Semester">
                    <flux:select.option value="">All Semesters</flux:select.option>
                    @foreach($semestersList as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Filter Department -->
            <div>
                <flux:select wire:model.live="filterDepartment" class="w-full" placeholder="Filter Department">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($departmentsList as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Filter Subject -->
            <div>
                <flux:select wire:model.live="filterSubject" class="w-full" placeholder="Filter Subject">
                    <flux:select.option value="">All Subjects</flux:select.option>
                    @foreach($subjectsList as $subj)
                        <flux:select.option value="{{ $subj->id }}">{{ $subj->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Filter Professor -->
            <div>
                <flux:select wire:model.live="filterTeacher" class="w-full" placeholder="Filter Professor">
                    <flux:select.option value="">All Professors</flux:select.option>
                    @foreach($teachersList as $t)
                        <flux:select.option value="{{ $t->id }}">{{ $t->formatted_name ?? $t->full_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>
    
    <!-- Table Skeleton Loader -->
    <div wire:loading wire:target="search, filterSemester, filterDepartment, filterSubject, filterTeacher, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <!-- Main Classes Table -->
    <div wire:loading.remove wire:target="search, filterSemester, filterDepartment, filterSubject, filterTeacher, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[26%] px-4 py-3.5">Subject</th>
                        <th class="w-[24%] px-4 py-3.5">Professor</th>
                        <th class="w-[10%] px-4 py-3.5">Section</th>
                        <th class="w-[18%] px-4 py-3.5">Schedule</th>
                        <th class="w-[14%] px-4 py-3.5">Semester</th>
                        <th class="w-[8%] px-4 py-3.5 text-center">Students</th>
                        <th class="w-[8%] px-4 py-3.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($classes as $class)
                        <tr wire:key="{{ $class->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Subject Column (Name only, code label removed) -->
                            <td class="px-4 py-3.5 dark:text-zinc-200 font-semibold truncate" title="{{ $class->subject->name }}">
                                {{ $class->subject->name }}
                            </td>
                            
                            <!-- Professor Column (Name only, employee number label removed) -->
                            <td class="px-4 py-3.5 dark:text-zinc-200 font-semibold truncate" title="{{ $class->teacher->formatted_name ?? $class->teacher->full_name }}">
                                {{ $class->teacher->formatted_name ?? $class->teacher->full_name }}
                            </td>

                            <!-- Section Column -->
                            <td class="px-4 py-3.5 font-bold font-mono text-xs text-zinc-900 dark:text-zinc-100">
                                {{ $class->section }}
                            </td>

                            <!-- Schedule Column (Schedule only, room removed) -->
                            <td class="px-4 py-3.5 dark:text-zinc-300 text-xs font-medium truncate" title="{{ $class->schedule }}">
                                {{ $class->schedule ?: 'No Schedule' }}
                            </td>

                            <!-- Semester Column -->
                            <td class="px-4 py-3.5 dark:text-zinc-400 text-xs">
                                {{ $class->semester->academicYear->name }} - {{ $class->semester->name }}
                            </td>

                            <!-- Students Column -->
                            <td class="px-4 py-3.5 text-center">
                                <flux:badge size="sm" color="{{ $class->students_count > 0 ? 'indigo' : 'zinc' }}" class="font-bold">
                                    {{ $class->students_count }} Enrolled
                                </flux:badge>
                            </td>

                            <!-- Action Column (Grouped Action Dropdown) -->
                            <td class="px-4 py-3.5 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="academic-cap" wire:click="manageStudents({{ $class->id }})">
                                            Manage Enrollment
                                        </flux:menu.item>

                                        <flux:menu.item icon="pencil-square" wire:click="editClass({{ $class->id }})">
                                            Edit Class
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $class->id }})">
                                            Delete Class
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon name="academic-cap" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-base font-semibold text-zinc-700 dark:text-zinc-300">No academic classes found</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No classes match your current search or filter criteria.</p>
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
            {{ $classes->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <flux:heading size="lg">{{ $editingClass ? 'Edit Class' : 'Create Class' }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
            </div>
            
            <form wire:submit="{{ $editingClass ? 'updateClass' : 'createClass' }}" class="flex flex-col gap-4">
                <x-searchable-select 
                    name="subject_id" 
                    label="Subject" 
                    placeholder="Select Subject" 
                    required 
                    :options="array_merge([['value' => '', 'label' => 'Select Subject']], $subjectsList->map(fn($subj) => ['value' => (string)$subj->id, 'label' => $subj->name])->toArray())" 
                />

                <x-searchable-select 
                    name="teacher_id" 
                    label="Professor" 
                    placeholder="Select Professor" 
                    required 
                    :options="array_merge([['value' => '', 'label' => 'Select Professor']], $teachersList->map(fn($t) => ['value' => (string)$t->id, 'label' => $t->formatted_name ?? $t->full_name])->toArray())" 
                />

                <flux:select wire:model="semester_id" label="Semester" required>
                    <flux:select.option value="">Select Semester</flux:select.option>
                    @foreach($semestersList as $sem)
                        <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="section" label="Section" type="text" placeholder="e.g. BSCS-3A" required />

                <!-- Schedule Date & Time Picker Section -->
                <div class="bg-zinc-50 dark:bg-zinc-800/40 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex flex-col gap-3">
                    <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">Class Schedule Picker</span>
                    
                    <flux:select wire:model="schedule_days" label="Schedule Days">
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
                        <flux:input wire:model="schedule_start_time" label="Start Time" type="time" />
                        <flux:input wire:model="schedule_end_time" label="End Time" type="time" />
                    </div>

                    <flux:input wire:model="schedule" label="Schedule Summary Preview" type="text" placeholder="e.g. MW 09:00 AM - 10:30 AM" />
                </div>

                <div class="flex justify-end gap-2 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingClass ? 'Save Changes' : 'Create Class' }}
                    </flux:button>
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
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->subject->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Section</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->section }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Professor</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingClass->teacher->formatted_name ?? $deletingClass->teacher->full_name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Schedule</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ $deletingClass->schedule ?: 'N/A' }} 
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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-2xl border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] flex flex-col gap-6">
            <div class="flex justify-between items-start border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div>
                    <flux:heading size="lg">Student Enrollment</flux:heading>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">
                        Class: <span class="font-semibold">{{ $managingClass?->subject->name }}</span> | Section: <span class="font-semibold">{{ $managingClass?->section }}</span>
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
                                <span class="font-medium dark:text-zinc-300 block">{{ $student->formatted_name ?? $student->full_name }}</span>
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
                                    <span class="font-medium dark:text-zinc-300 block">{{ $student->formatted_name ?? $student->full_name }}</span>
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
