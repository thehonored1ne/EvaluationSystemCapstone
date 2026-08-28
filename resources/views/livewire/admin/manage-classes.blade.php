<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Models\Employee;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Department;
use App\Models\Program;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-classes-skeleton');
    }

    // Import properties
    public $importFile = null;
    public bool $showImportModal = false;

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

    // Fast Batch Enrollment & Multi-Select properties
    public array $selectedStudentIds = [];
    public string $enrollProgramFilter = '';
    public string $enrollYearFilter = '';
    public bool $selectAllUnenrolled = false;

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

    public function updatedEnrollProgramFilter()
    {
        $this->selectedStudentIds = [];
        $this->selectAllUnenrolled = false;
    }

    public function updatedEnrollYearFilter()
    {
        $this->selectedStudentIds = [];
        $this->selectAllUnenrolled = false;
    }

    public function updatedStudentSearch()
    {
        $this->selectedStudentIds = [];
        $this->selectAllUnenrolled = false;
    }

    public function updatedSelectAllUnenrolled($value)
    {
        if ($value && $this->managingClass) {
            $candidates = $this->getUnenrolledCandidates();
            $this->selectedStudentIds = $candidates->pluck('id')->map(fn($id) => (string)$id)->toArray();
        } else {
            $this->selectedStudentIds = [];
        }
    }

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

        if ($this->schedule_days && $this->schedule_start_time && $this->schedule_end_time) {
            try {
                $startStr = Carbon::parse($this->schedule_start_time)->format('h:i A');
                $endStr = Carbon::parse($this->schedule_end_time)->format('h:i A');
                $this->schedule = $this->schedule_days . ' ' . $startStr . ' - ' . $endStr;
            } catch (\Exception $e) {}
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

        if ($this->schedule_days && $this->schedule_start_time && $this->schedule_end_time) {
            try {
                $startStr = Carbon::parse($this->schedule_start_time)->format('h:i A');
                $endStr = Carbon::parse($this->schedule_end_time)->format('h:i A');
                $this->schedule = $this->schedule_days . ' ' . $startStr . ' - ' . $endStr;
            } catch (\Exception $e) {}
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

        $classToDelete = $this->deletingClass;

        if ($classToDelete->evaluations()->exists()) {
            \Flux::toast(
                heading: 'Cannot Delete Class',
                text: 'This class has evaluation submissions associated with it. Delete the evaluations first.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            $this->deletingClass = null;
            return;
        }

        $this->deletingClass = null;
        $this->editingClass = null;
        $this->managingClass = null;
        $this->showDeleteModal = false;

        $classToDelete->delete();

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
        $this->enrollProgramFilter = '';
        $this->enrollYearFilter = '';
        $this->selectedStudentIds = [];
        $this->selectAllUnenrolled = false;
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
            $this->selectedStudentIds = array_diff($this->selectedStudentIds, [(string)$studentId]);
            \Flux::toast(
                heading: 'Student Enrolled',
                text: 'The student was added to this class.',
                variant: 'success'
            );
        } catch (\Exception $e) {}
    }

    public function enrollSelectedStudents()
    {
        if (!$this->managingClass || empty($this->selectedStudentIds)) return;

        $this->managingClass->students()->syncWithoutDetaching($this->selectedStudentIds);
        $count = count($this->selectedStudentIds);
        $this->selectedStudentIds = [];
        $this->selectAllUnenrolled = false;
        $this->updateEnrolledList();

        \Flux::toast(
            heading: 'Batch Students Enrolled',
            text: "Successfully enrolled $count student(s) into this class.",
            variant: 'success'
        );
    }

    public function enrollMatchingSectionStudents()
    {
        if (!$this->managingClass) return;

        $sectionCode = $this->managingClass->section;
        $matchingStudentIds = Student::where('section', $sectionCode)
            ->whereNotIn('id', $this->enrolledStudentIds)
            ->pluck('id')
            ->toArray();

        if (empty($matchingStudentIds)) {
            \Flux::toast(
                heading: 'No Matching Unenrolled Students',
                text: "All students in section '$sectionCode' are already enrolled in this class.",
                variant: 'warning'
            );
            return;
        }

        $this->managingClass->students()->syncWithoutDetaching($matchingStudentIds);
        $count = count($matchingStudentIds);
        $this->updateEnrolledList();

        \Flux::toast(
            heading: 'Batch Section Enrollment Complete',
            text: "Successfully enrolled all $count student(s) matching section '$sectionCode'.",
            variant: 'success'
        );
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

    public function unenrollAllStudents()
    {
        if (!$this->managingClass) return;

        $count = count($this->enrolledStudentIds);
        $this->managingClass->students()->detach();
        $this->updateEnrolledList();

        \Flux::toast(
            heading: 'All Students Removed',
            text: "Successfully unenrolled all $count student(s) from this class.",
            variant: 'success'
        );
    }

    private function getUnenrolledCandidates()
    {
        if (!$this->managingClass) return collect();

        $query = Student::query()
            ->with('program.department')
            ->whereNotIn('id', $this->enrolledStudentIds);

        if ($this->studentSearch) {
            $searchStr = '%' . trim($this->studentSearch) . '%';
            $query->where(function ($q) use ($searchStr) {
                $q->where('first_name', 'like', $searchStr)
                  ->orWhere('last_name', 'like', $searchStr)
                  ->orWhere('student_number', 'like', $searchStr)
                  ->orWhere('section', 'like', $searchStr);
            });
        }

        if ($this->enrollProgramFilter !== '') {
            $query->where('program_id', $this->enrollProgramFilter);
        }

        if ($this->enrollYearFilter !== '') {
            $query->where('year_level', (int)$this->enrollYearFilter);
        }

        return $query->orderBy('last_name')->limit(50)->get();
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="classes_and_roster_template.csv"',
        ];

        $columns = ['subject_code', 'teacher_employee_number', 'section', 'schedule', 'room', 'student_numbers_comma_separated'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            // Sample rows
            fputcsv($file, ['CC101', 'FAC-001', 'BSIT-1A', 'MWF 08:00 AM - 09:30 AM', 'CL-1', '2026-01-0001, 2026-01-0002, 2026-01-0003']);
            fputcsv($file, ['ACT101', 'FAC-002', 'BSA-1A', 'TTH 10:00 AM - 11:30 AM', 'Room 302', '2026-02-0001, 2026-02-0002']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportClasses()
    {
        $query = AcademicClass::query()
            ->with(['subject', 'teacher.department', 'semester.academicYear', 'students'])
            ->withCount('students');

        if ($this->filterSemester) {
            $query->where('semester_id', $this->filterSemester);
        }
        if ($this->filterDepartment) {
            $query->whereHas('teacher', fn ($q) => $q->where('department_id', $this->filterDepartment));
        }
        if ($this->filterSubject) {
            $query->where('subject_id', $this->filterSubject);
        }
        if ($this->filterTeacher) {
            $query->where('teacher_id', $this->filterTeacher);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('section', 'like', '%'.$this->search.'%')
                    ->orWhereHas('subject', fn ($sub) => $sub->where('code', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('teacher', fn ($emp) => $emp->where('first_name', 'like', '%'.$this->search.'%')->orWhere('last_name', 'like', '%'.$this->search.'%'));
            });
        }

        $classes = $query->orderBy('id', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="classes_export_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($classes) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Section', 'Subject Code', 'Subject Name', 'Professor ID', 'Professor Name', 'Department', 'Schedule', 'Room', 'Academic Period', 'Enrolled Count', 'Enrolled Student IDs']);

            foreach ($classes as $c) {
                $studentIds = $c->students->pluck('student_number')->filter()->implode(', ');
                fputcsv($file, [
                    $c->section,
                    $c->subject->code ?? '',
                    $c->subject->name ?? '',
                    $c->teacher->employee_number ?? '',
                    $c->teacher->formatted_name ?? $c->teacher->full_name ?? '',
                    $c->teacher->department->code ?? '',
                    $c->schedule ?? '',
                    $c->room ?? '',
                    ($c->semester->academicYear->name ?? '').' - '.($c->semester->name ?? ''),
                    $c->students_count,
                    $studentIds,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function importClasses()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $activeSemester = Semester::where('is_active', true)->first();
        if (! $activeSemester) {
            $this->addError('importFile', 'No active semester found. Please set an active semester first in Settings.');

            return;
        }

        $path = $this->importFile->getRealPath();
        $file = fopen($path, 'r');
        $header = fgetcsv($file);
        if (! $header) {
            $this->addError('importFile', 'The CSV file is empty or corrupted.');

            return;
        }

        $rows = [];
        while (($row = fgetcsv($file)) !== false) {
            if (array_filter($row)) {
                $rows[] = $row;
            }
        }
        fclose($file);

        if (empty($rows)) {
            $this->addError('importFile', 'No data rows found in the uploaded file.');

            return;
        }

        $subjectsByCode = Subject::all()->keyBy(fn ($s) => strtoupper(trim($s->code)));
        $teachersByNum = Employee::whereIn('role', ['faculty', 'program head'])->get()->keyBy(fn ($e) => strtoupper(trim($e->employee_number)));
        $studentsByNum = Student::all()->keyBy(fn ($s) => strtoupper(trim($s->student_number)));

        $classesAdded = 0;
        $classesUpdated = 0;
        $enrollmentsAdded = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $subjCode = strtoupper(trim($row[0] ?? ''));
                $teacherNum = strtoupper(trim($row[1] ?? ''));
                $section = strtoupper(trim($row[2] ?? ''));
                $schedule = trim($row[3] ?? '') ?: null;
                $room = trim($row[4] ?? '') ?: null;
                $studentNumsStr = trim(implode(',', array_slice($row, 5)));

                if (! $subjCode || ! $teacherNum || ! $section) {
                    continue; // Skip invalid row
                }

                $subject = $subjectsByCode->get($subjCode);
                $teacher = $teachersByNum->get($teacherNum);

                if (! $subject || ! $teacher) {
                    continue; // Skip if subject or teacher not found
                }

                $class = AcademicClass::where([
                    'semester_id' => $activeSemester->id,
                    'subject_id' => $subject->id,
                    'section' => $section,
                ])->first();

                if ($class) {
                    $class->update([
                        'teacher_id' => $teacher->id,
                        'schedule' => $schedule ?? $class->schedule,
                        'room' => $room ?? $class->room,
                    ]);
                    $classesUpdated++;
                } else {
                    $class = AcademicClass::create([
                        'semester_id' => $activeSemester->id,
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'section' => $section,
                        'schedule' => $schedule,
                        'room' => $room,
                    ]);
                    $classesAdded++;
                }

                // If student numbers are provided, sync enrollment
                if ($studentNumsStr) {
                    $studentNums = array_filter(array_map('trim', explode(',', $studentNumsStr)));
                    $studentIdsToSync = [];
                    foreach ($studentNums as $sNum) {
                        $st = $studentsByNum->get(strtoupper($sNum));
                        if ($st) {
                            $studentIdsToSync[] = $st->id;
                        }
                    }
                    if (! empty($studentIdsToSync)) {
                        $class->students()->syncWithoutDetaching($studentIdsToSync);
                        $enrollmentsAdded += count($studentIdsToSync);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->addError('importFile', 'Import error on line '.($index + 2).': '.$e->getMessage());

            return;
        }

        $this->reset(['importFile']);
        $this->showImportModal = false;

        activity('admin')
            ->causedBy(auth()->user())
            ->log("Bulk imported {$classesAdded} class sections and enrolled {$enrollmentsAdded} student allocations via CSV");

        \Flux::toast(
            heading: 'Classes & Rosters Imported',
            text: "Processed classes: {$classesAdded} created, {$classesUpdated} updated. Enrolled {$enrollmentsAdded} student allocations.",
            variant: 'success'
        );
    }

    public function with(): array
    {
        $query = AcademicClass::query()
            ->with(['subject', 'teacher.department', 'semester.academicYear'])
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

        $subjectsList = Subject::orderBy('name')->get();
        $teachersList = Employee::whereIn('role', ['faculty', 'program head'])->orderBy('last_name')->get();
        $semestersList = Semester::with('academicYear')->orderBy('id', 'desc')->get();
        $departmentsList = Department::orderBy('name')->get();
        $programsList = Program::orderBy('code')->get();

        // Candidates for student enrollment modal
        $unenrolledCandidates = collect();
        $matchingSectionCount = 0;

        if ($this->showEnrollmentModal && $this->managingClass) {
            $unenrolledCandidates = $this->getUnenrolledCandidates();

            $matchingSectionCount = Student::where('section', $this->managingClass->section)
                ->whereNotIn('id', $this->enrolledStudentIds)
                ->count();
        }

        return [
            'classes' => $query->orderBy('id', 'desc')->paginate(10),
            'subjectsList' => $subjectsList,
            'teachersList' => $teachersList,
            'semestersList' => $semestersList,
            'departmentsList' => $departmentsList,
            'programsList' => $programsList,
            'unenrolledCandidates' => $unenrolledCandidates,
            'studentSearchResults' => $unenrolledCandidates,
            'matchingSectionCount' => $matchingSectionCount,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">Manage Classes & Enrollment</flux:heading>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <flux:button variant="outline" icon="arrow-down-tray" wire:click="exportClasses">
                Export CSV
            </flux:button>
            <flux:button variant="outline" icon="arrow-up-tray" wire:click="$set('showImportModal', true)">
                Import Classes
            </flux:button>
            <flux:button variant="primary" wire:click="prepareCreate" icon="plus">
                Add Class
            </flux:button>
        </div>
    </div>
    
    <!-- Search & Advanced Filter Controls Bar (Inline Flex + 2x2/4-across Grid) -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 w-full">
            <!-- Search Input Bar -->
            <div class="flex-1 min-w-[220px]">
                <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by section, subject or professor..." />
            </div>

            <!-- Filter Dropdowns Grid (2x2 on mobile/tablet, 4-across on desktop) -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 flex-1 items-center">
                <!-- Semester Filter -->
                <div>
                    <flux:select wire:model.live="filterSemester" class="w-full" placeholder="All Semesters">
                        <flux:select.option value="">All Semesters</flux:select.option>
                        @foreach($semestersList as $sem)
                            <flux:select.option value="{{ $sem->id }}">{{ $sem->academicYear->name }} - {{ $sem->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Department Filter -->
                <div>
                    <flux:select wire:model.live="filterDepartment" class="w-full" placeholder="All Departments">
                        <flux:select.option value="">All Departments</flux:select.option>
                        @foreach($departmentsList as $dept)
                            <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Subject Filter -->
                <div>
                    <flux:select wire:model.live="filterSubject" class="w-full" placeholder="All Subjects">
                        <flux:select.option value="">All Subjects</flux:select.option>
                        @foreach($subjectsList as $subj)
                            <flux:select.option value="{{ $subj->id }}">{{ $subj->code }} - {{ $subj->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Professor Filter -->
                <div>
                    <flux:select wire:model.live="filterTeacher" class="w-full" placeholder="All Professors">
                        <flux:select.option value="">All Professors</flux:select.option>
                        @foreach($teachersList as $t)
                            <flux:select.option value="{{ $t->id }}">{{ $t->formatted_name ?? $t->full_name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>

            <!-- Reset Button -->
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset All Filters" class="shrink-0 self-end lg:self-center" />
        </div>
    </div>
    
    <!-- Skeleton Loading State -->
    <div wire:loading wire:target="search, filterSemester, filterDepartment, filterSubject, filterTeacher, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="7" />
    </div>

    <!-- Main Classes Table -->
    <div wire:loading.remove wire:target="search, filterSemester, filterDepartment, filterSubject, filterTeacher, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full min-w-[850px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[10%] min-w-[90px] px-4 py-3.5 whitespace-nowrap">Section</th>
                        <th class="w-[24%] min-w-[170px] px-4 py-3.5 whitespace-nowrap">Subject</th>
                        <th class="w-[20%] min-w-[160px] px-4 py-3.5 whitespace-nowrap">Assigned Professor</th>
                        <th class="w-[18%] min-w-[140px] px-4 py-3.5 whitespace-nowrap">Schedule</th>
                        <th class="w-[14%] min-w-[120px] px-4 py-3.5 whitespace-nowrap">Academic Period</th>
                        <th class="w-[8%] min-w-[90px] px-4 py-3.5 text-center whitespace-nowrap">Enrolled</th>
                        <th class="w-[6%] min-w-[80px] px-4 py-3.5 text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($classes as $classItem)
                        <tr wire:key="{{ $classItem->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Section -->
                            <td class="px-4 py-3.5 font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                {{ $classItem->section }}
                            </td>

                            <!-- Subject -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="font-mono text-xs font-bold text-[#9b0000] dark:text-[#f89696] block">
                                    {{ $classItem->subject->code }}
                                </span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate block max-w-[170px]" title="{{ $classItem->subject->name }}">
                                    {{ $classItem->subject->name }}
                                </span>
                            </td>

                            <!-- Teacher -->
                            <td class="px-4 py-3.5 whitespace-nowrap">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100 block truncate max-w-[160px]">
                                    {{ $classItem->teacher->formatted_name ?? $classItem->teacher->full_name }}
                                </span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400 block truncate">
                                    {{ $classItem->teacher->department->code ?? '' }} {{ $classItem->teacher->role ? '(' . ucfirst($classItem->teacher->role) . ')' : '' }}
                                </span>
                            </td>

                            <!-- Schedule -->
                            <td class="px-4 py-3.5 text-xs text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                @if($classItem->schedule)
                                    <span class="font-semibold block">{{ $classItem->schedule }}</span>
                                    @if($classItem->room)
                                        <span class="text-zinc-500 block">Room: {{ $classItem->room }}</span>
                                    @endif
                                @else
                                    <span class="text-zinc-400 italic">No schedule set</span>
                                @endif
                            </td>

                            <!-- Semester -->
                            <td class="px-4 py-3.5 text-xs text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                <span class="font-semibold block text-zinc-800 dark:text-zinc-200">
                                    {{ $classItem->semester->academicYear->name }}
                                </span>
                                <span>{{ $classItem->semester->name }}</span>
                            </td>

                            <!-- Enrolled Count -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <button type="button" wire:click="manageStudents({{ $classItem->id }})" class="hover:opacity-80 transition-opacity">
                                    @if($classItem->students_count > 0)
                                        <flux:badge size="sm" color="indigo" class="cursor-pointer font-bold">
                                            {{ $classItem->students_count }} {{ Str::plural('student', $classItem->students_count) }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc" class="cursor-pointer">
                                            0 students
                                        </flux:badge>
                                    @endif
                                </button>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="user-plus" wire:click="manageStudents({{ $classItem->id }})">
                                            Manage Enrollment
                                        </flux:menu.item>

                                        <flux:menu.item icon="pencil-square" wire:click="editClass({{ $classItem->id }})">
                                            Edit Class Details
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $classItem->id }})">
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

    <!-- Compact Fixed-Viewport Student Enrollment Modal -->
    @if($showEnrollmentModal && $managingClass)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm p-3 sm:p-4 flex min-h-full items-center justify-center">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full border border-zinc-200 dark:border-zinc-800 flex flex-col my-auto overflow-hidden" style="max-width: 560px !important; max-height: calc(100vh - 3.5rem) !important;">
            <!-- Compact Fixed Header -->
            <div class="px-4 py-3 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 shrink-0 flex justify-between items-center z-10">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="amber" class="font-bold">{{ $managingClass->section }}</flux:badge>
                        <flux:heading size="md">Manage Class Enrollment</flux:heading>
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-zinc-400 mt-0.5">
                        Subject: <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $managingClass->subject->code }} - {{ $managingClass->subject->name }}</span>
                    </p>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showEnrollmentModal', false)" tooltip="Close Modal" />
            </div>

            <!-- Scrollable Modal Body (min-h-0 forces flex container scrolling) -->
            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3.5">
                <!-- 1-Click Smart Batch Action Banner -->
                @if($matchingSectionCount > 0)
                    <div class="flex items-center justify-between gap-2 p-2.5 bg-amber-50 dark:bg-amber-950/40 rounded-lg border border-amber-200 dark:border-amber-800">
                        <div class="flex items-center gap-2">
                            <flux:icon name="bolt" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                            <span class="text-xs text-amber-800 dark:text-amber-300">
                                Found <strong>{{ $matchingSectionCount }}</strong> unenrolled in section <strong>"{{ $managingClass->section }}"</strong>.
                            </span>
                        </div>
                        <flux:button size="xs" variant="primary" wire:click="enrollMatchingSectionStudents" icon="user-group" class="shrink-0 font-bold">
                            Enroll All {{ $matchingSectionCount }}
                        </flux:button>
                    </div>
                @endif

                <!-- Enrolled Students Accordion List -->
                <div class="flex flex-col gap-1.5">
                    <div class="flex justify-between items-center">
                        <flux:heading size="sm" class="font-semibold text-xs uppercase tracking-wider">
                            Enrolled Students ({{ count($enrolledStudentIds) }})
                        </flux:heading>

                        @if(count($enrolledStudentIds) > 0)
                            <flux:button size="xs" variant="ghost" class="text-red-500 hover:text-red-600 text-[11px] py-0" wire:click="unenrollAllStudents">
                                Unenroll All
                            </flux:button>
                        @endif
                    </div>

                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg max-h-28 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                        @forelse($managingClass->students as $student)
                            <div class="flex justify-between items-center px-3 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800 text-xs">
                                <div class="truncate pr-2">
                                    <span class="font-medium dark:text-zinc-200 inline-block">{{ $student->formatted_name ?? $student->full_name }}</span>
                                    <span class="font-mono text-[11px] text-zinc-400 inline-block ml-1">({{ $student->student_number }}) · {{ $student->section ?: 'No Sec' }}</span>
                                </div>
                                <flux:button size="xs" variant="ghost" class="text-red-500 hover:text-red-600 shrink-0 text-[11px] py-0" wire:click="unenrollStudent({{ $student->id }})">
                                    Remove
                                </flux:button>
                            </div>
                        @empty
                            <div class="px-3 py-4 text-center text-xs text-gray-500 dark:text-zinc-400">
                                No students enrolled yet.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Fast Multi-Select & Filtered Batch Enrollment -->
                <div class="flex flex-col gap-2 border-t border-zinc-200 dark:border-zinc-700 pt-3">
                    <div class="flex justify-between items-center gap-2">
                        <flux:heading size="sm" class="font-semibold text-xs uppercase tracking-wider">Unenrolled Candidates</flux:heading>
                        
                        @if(count($selectedStudentIds) > 0)
                            <flux:button size="xs" variant="primary" wire:click="enrollSelectedStudents" icon="user-plus" class="font-bold py-0.5">
                                Enroll Selected ({{ count($selectedStudentIds) }})
                            </flux:button>
                        @endif
                    </div>

                    <!-- Filter Controls inside Modal -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 bg-zinc-50 dark:bg-zinc-800/40 p-2 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <flux:input wire:model.live.debounce.300ms="studentSearch" placeholder="Search name or section..." icon="magnifying-glass" class="w-full" />

                        <flux:select wire:model.live="enrollProgramFilter" class="w-full" placeholder="All Programs">
                            <flux:select.option value="">All Programs</flux:select.option>
                            @foreach($programsList as $prog)
                                <flux:select.option value="{{ $prog->id }}">{{ $prog->code }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model.live="enrollYearFilter" class="w-full" placeholder="All Year Levels">
                            <flux:select.option value="">All Years</flux:select.option>
                            <flux:select.option value="1">1st Year</flux:select.option>
                            <flux:select.option value="2">2nd Year</flux:select.option>
                            <flux:select.option value="3">3rd Year</flux:select.option>
                            <flux:select.option value="4">4th Year</flux:select.option>
                        </flux:select>
                    </div>

                    <!-- Candidate List with Checkboxes -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg max-h-36 overflow-y-auto divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                        @if($unenrolledCandidates->count() > 0)
                            <!-- Select All Header -->
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-zinc-100 dark:bg-zinc-800 font-semibold text-[11px] text-zinc-700 dark:text-zinc-300 sticky top-0 z-10">
                                <input type="checkbox" wire:model.live="selectAllUnenrolled" class="rounded border-zinc-300 text-[#9b0000] focus:ring-[#9b0000]">
                                <span>Select All Candidates ({{ $unenrolledCandidates->count() }})</span>
                            </div>

                            @foreach($unenrolledCandidates as $student)
                                <div wire:key="candidate-{{ $student->id }}" class="flex justify-between items-center px-3 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/40 text-xs">
                                    <div class="flex items-center gap-2.5 truncate pr-2">
                                        <input type="checkbox" wire:model.live="selectedStudentIds" value="{{ $student->id }}" class="rounded border-zinc-300 text-[#9b0000] focus:ring-[#9b0000]">
                                        <div class="truncate">
                                            <span class="font-medium dark:text-zinc-200 inline-block">{{ $student->formatted_name ?? $student->full_name }}</span>
                                            <span class="font-mono text-[11px] text-zinc-400 inline-block ml-1">({{ $student->student_number }}) · Sec: {{ $student->section ?: 'N/A' }} · Yr {{ $student->year_level }} · {{ $student->program?->code }}</span>
                                        </div>
                                    </div>
                                    <flux:button size="xs" variant="outline" class="shrink-0 text-[11px] py-0" wire:click="enrollStudent({{ $student->id }})">
                                        Enroll
                                    </flux:button>
                                </div>
                            @endforeach
                        @else
                            <div class="px-3 py-6 text-center text-xs text-gray-500 dark:text-zinc-400">
                                No matching unenrolled students found.
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Compact Fixed Footer -->
            <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800 shrink-0 flex justify-between items-center z-10">
                <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
                    Selected: <strong>{{ count($selectedStudentIds) }}</strong>
                </span>
                <flux:button size="sm" variant="primary" wire:click="$set('showEnrollmentModal', false)">Close</flux:button>
            </div>
        </div>
    </div>
    @endif

    <!-- Bulk Import Classes & Rosters Modal -->
    <flux:modal wire:model="showImportModal" class="min-w-[520px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">Bulk Import Classes & Rosters</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Upload a CSV spreadsheet containing section schedules and student ID allocations for the active semester.</p>
            </div>

            <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl p-4 bg-zinc-50/50 dark:bg-zinc-800/30 text-xs space-y-2">
                <span class="font-bold text-zinc-800 dark:text-zinc-200 block">CSV File Format Requirements:</span>
                <ul class="list-disc list-inside text-zinc-600 dark:text-zinc-400 space-y-1">
                    <li>Required Columns: <code class="font-mono text-zinc-900 dark:text-zinc-100 font-bold">subject_code, teacher_employee_number, section</code></li>
                    <li>Optional Columns: <code class="font-mono text-zinc-700 dark:text-zinc-300">schedule, room, student_numbers_comma_separated</code></li>
                    <li>In the <code class="font-mono">student_numbers_comma_separated</code> column, list enrolled student IDs separated by commas (e.g. <code class="font-mono">2026-01-0001, 2026-01-0002</code>).</li>
                </ul>
            </div>

            <form wire:submit="importClasses" class="space-y-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white">Select Spreadsheet (.CSV)</label>
                        <flux:button size="xs" variant="outline" icon="arrow-down-tray" wire:click="downloadTemplate">
                            Download Template
                        </flux:button>
                    </div>
                    <input type="file" wire:model="importFile" accept=".csv,text/csv" class="w-full text-xs text-zinc-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#9b0000] file:text-white hover:file:bg-[#7a0000] cursor-pointer" required />
                    @error('importFile') <span class="text-xs text-rose-500 mt-1 block font-semibold">{{ $message }}</span> @enderror
                </div>

                <div wire:loading wire:target="importFile" class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
                    Uploading and verifying file...
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                    <flux:button variant="ghost" wire:click="$set('showImportModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        Upload & Import
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
