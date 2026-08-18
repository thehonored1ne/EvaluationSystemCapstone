<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Program;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Student;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Filter properties
    public string $search = '';
    public string $departmentFilter = '';
    public string $headFilter = '';
    public string $sortBy = 'name_asc';

    // Program Form properties
    public string $code = '';
    public string $name = '';
    public string $department_id = '';
    public string $program_head_id = '';
    
    public bool $showModal = false;
    public bool $showDeleteModal = false;

    public ?Program $editingProgram = null;
    public ?Program $deletingProgram = null;

    // Student Enrollment modal properties
    public bool $showStudentsModal = false;
    public ?Program $managingProgram = null;
    public string $studentSearch = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedDepartmentFilter() { $this->resetPage(); }
    public function updatedHeadFilter() { $this->resetPage(); }
    public function updatedSortBy() { $this->resetPage(); }

    public function updatedDepartmentId($value)
    {
        if (!$value) {
            $this->program_head_id = '';
            return;
        }

        $dept = Department::with(['programHead', 'programHeads'])->find($value);
        if (!$dept) {
            $this->program_head_id = '';
            return;
        }

        // Auto-detect program head from explicit column or employee department relationship
        $headId = $dept->program_head_id ?: $dept->programHeads->first()?->id;

        // Fallback: If no program head is assigned to this department, fallback to unassigned
        $this->program_head_id = $headId ? (string)$headId : '';
    }

    public function clearFilters()
    {
        $this->reset(['search', 'departmentFilter', 'headFilter']);
        $this->sortBy = 'name_asc';
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset(['code', 'name', 'department_id', 'program_head_id', 'editingProgram']);
        $this->showModal = true;
    }

    public function saveProgram()
    {
        $rules = [
            'code' => 'required|string|max:50|unique:programs,code' . ($this->editingProgram ? ',' . $this->editingProgram->id : ''),
            'name' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'program_head_id' => 'nullable|exists:employees,id',
        ];

        $this->validate($rules);

        $codeFormatted = strtoupper(trim($this->code));
        $headIdValue = $this->program_head_id ?: null;

        if ($this->editingProgram) {
            $oldHeadId = $this->editingProgram->program_head_id;

            $this->editingProgram->update([
                'code' => $codeFormatted,
                'name' => $this->name,
                'department_id' => $this->department_id,
                'program_head_id' => $headIdValue,
            ]);

            // Sync old and new Program Head employee department
            if ($oldHeadId && $oldHeadId != $headIdValue) {
                // Keep department or reset if needed
            }
            if ($headIdValue) {
                Employee::where('id', $headIdValue)->update(['department_id' => $this->department_id]);
            }

            \Flux::toast(
                heading: 'Program Updated',
                text: 'Academic program details updated successfully.',
                variant: 'success'
            );
        } else {
            $program = Program::create([
                'code' => $codeFormatted,
                'name' => $this->name,
                'department_id' => $this->department_id,
                'program_head_id' => $headIdValue,
            ]);

            if ($headIdValue) {
                Employee::where('id', $headIdValue)->update(['department_id' => $this->department_id]);
            }

            \Flux::toast(
                heading: 'Program Created',
                text: 'New academic program created successfully.',
                variant: 'success'
            );
        }

        $this->showModal = false;
    }

    public function editProgram(Program $program)
    {
        $this->editingProgram = $program;
        $this->code = $program->code;
        $this->name = $program->name;
        $this->department_id = (string)$program->department_id;
        $this->program_head_id = $program->program_head_id ? (string)$program->program_head_id : '';
        $this->showModal = true;
    }

    public function confirmDelete(Program $program)
    {
        $this->deletingProgram = $program;
        $this->showDeleteModal = true;
    }

    public function deleteProgram()
    {
        if (!$this->deletingProgram) return;

        $programToDelete = $this->deletingProgram;

        // Check if program has enrolled students
        if ($programToDelete->students()->exists()) {
            \Flux::toast(
                heading: 'Cannot Delete Program',
                text: 'This program has enrolled students linked to it. Re-assign or remove them first using the Students modal.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            $this->deletingProgram = null;
            return;
        }

        // Reset component properties BEFORE deleting to avoid Livewire dehydration 404
        $this->deletingProgram = null;
        $this->editingProgram = null;
        $this->managingProgram = null;
        $this->showDeleteModal = false;

        $programToDelete->delete();

        \Flux::toast(
            heading: 'Program Deleted',
            text: 'Academic program deleted successfully.',
            variant: 'success'
        );
    }

    // Student Enrollment management actions
    public function manageStudents(Program $program)
    {
        $this->managingProgram = $program;
        $this->studentSearch = '';
        $this->showStudentsModal = true;
    }

    public function addStudentToProgram(Student $student)
    {
        if (!$this->managingProgram) return;

        $student->update(['program_id' => $this->managingProgram->id]);
        $this->managingProgram->refresh();

        \Flux::toast(
            heading: 'Student Enrolled',
            text: "{$student->formatted_name} was enrolled into {$this->managingProgram->code}.",
            variant: 'success'
        );
    }

    public function removeStudentFromProgram(Student $student)
    {
        if (!$this->managingProgram) return;

        $student->update(['program_id' => null]);
        $this->managingProgram->refresh();

        \Flux::toast(
            heading: 'Student Unlinked',
            text: "{$student->formatted_name} was removed from {$this->managingProgram->code}.",
            variant: 'success'
        );
    }

    public function unenrollAllStudents()
    {
        if (!$this->managingProgram) return;

        $count = Student::where('program_id', $this->managingProgram->id)->count();
        Student::where('program_id', $this->managingProgram->id)->update(['program_id' => null]);
        $this->managingProgram->refresh();

        \Flux::toast(
            heading: 'All Students Unlinked',
            text: "{$count} student(s) were unlinked from {$this->managingProgram->code}. You can now safely delete or manage this program.",
            variant: 'success'
        );
    }

    public function with(): array
    {
        // 1. Calculate Summary Statistics
        $totalPrograms = Program::count();
        $assignedHeadsCount = Program::whereNotNull('program_head_id')->count();
        $totalStudentsEnrolled = Student::whereNotNull('program_id')->count();
        $departmentsCovered = Department::has('programs')->count();

        // 2. Query Programs with relations and counts
        $query = Program::query()
            ->with(['department', 'programHead'])
            ->withCount('students');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->departmentFilter) {
            $query->where('department_id', $this->departmentFilter);
        }

        if ($this->headFilter === 'assigned') {
            $query->whereNotNull('program_head_id');
        } elseif ($this->headFilter === 'unassigned') {
            $query->whereNull('program_head_id');
        }

        match ($this->sortBy) {
            'name_desc' => $query->orderBy('name', 'desc'),
            'code_asc' => $query->orderBy('code', 'asc'),
            'code_desc' => $query->orderBy('code', 'desc'),
            'students_desc' => $query->orderBy('students_count', 'desc')->orderBy('name', 'asc'),
            default => $query->orderBy('name', 'asc'),
        };

        // Available dropdown lists
        $departmentsList = Department::orderBy('name')->get();
        $programHeadsList = Employee::where('role', 'program head')->where('status', 'active')->orderBy('last_name')->get();

        // Enrolled and Searchable Students data for modal
        $enrolledStudents = collect();
        $studentSearchResults = collect();

        if ($this->managingProgram) {
            $enrolledStudents = Student::where('program_id', $this->managingProgram->id)
                ->orderBy('last_name')
                ->get();

            if ($this->studentSearch) {
                $queryStr = '%' . $this->studentSearch . '%';
                $studentSearchResults = Student::where(function ($q) use ($queryStr) {
                        $q->where('first_name', 'like', $queryStr)
                          ->orWhere('last_name', 'like', $queryStr)
                          ->orWhere('student_number', 'like', $queryStr);
                    })
                    ->where(function ($q) {
                        $q->whereNull('program_id')
                          ->orWhere('program_id', '!=', $this->managingProgram->id);
                    })
                    ->with('program')
                    ->limit(8)
                    ->get();
            }
        }

        return [
            'programs' => $query->paginate(10),
            'totalPrograms' => $totalPrograms,
            'assignedHeadsCount' => $assignedHeadsCount,
            'totalStudentsEnrolled' => $totalStudentsEnrolled,
            'departmentsCovered' => $departmentsCovered,
            'departmentsList' => $departmentsList,
            'programHeadsList' => $programHeadsList,
            'enrolledStudents' => $enrolledStudents,
            'studentSearchResults' => $studentSearchResults,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-8">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Manage Academic Programs</flux:heading>
            <flux:subheading class="text-left">Degree programs catalog, department assignments, program head leadership, and student allocations.</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Program</flux:button>
    </div>

    <!-- Top Row Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1: Total Programs -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Academic Programs</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalPrograms" /></span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Active degree program offerings</span>
        </div>

        <!-- Card 2: Assigned Program Heads -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Assigned Program Heads</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$assignedHeadsCount" /></span>
                </div>
                <flux:icon name="user-group" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Programs with active leadership</span>
        </div>

        <!-- Card 3: Total Enrolled Students -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Students</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalStudentsEnrolled" /></span>
                </div>
                <flux:icon name="users" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Students linked to academic programs</span>
        </div>

        <!-- Card 4: Departments Covered -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Active Departments</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$departmentsCovered" /></span>
                </div>
                <flux:icon name="building-office-2" class="size-6 text-[#9b0000] dark:text-[#f89696]" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Departments housing degree programs</span>
        </div>
    </div>

    <!-- Search & Advanced Filter Controls Bar -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <!-- Search Input Bar -->
        <div class="flex items-center gap-3 w-full">
            <div class="flex-1">
                <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by program code or name..." />
            </div>
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset All Filters" class="shrink-0" />
        </div>

        <!-- Filter Dropdowns Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full">
            <!-- Filter Department -->
            <div>
                <flux:select wire:model.live="departmentFilter" class="w-full" placeholder="Filter Department">
                    <flux:select.option value="">All Departments</flux:select.option>
                    @foreach($departmentsList as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Program Head Filter -->
            <div>
                <flux:select wire:model.live="headFilter" class="w-full" placeholder="All Head Status">
                    <flux:select.option value="">All Head Status</flux:select.option>
                    <flux:select.option value="assigned">Assigned Program Head</flux:select.option>
                    <flux:select.option value="unassigned">Unassigned Program Head</flux:select.option>
                </flux:select>
            </div>

            <!-- Sort By -->
            <div>
                <flux:select wire:model.live="sortBy" class="w-full">
                    <flux:select.option value="name_asc">Program Name (A to Z)</flux:select.option>
                    <flux:select.option value="name_desc">Program Name (Z to A)</flux:select.option>
                    <flux:select.option value="code_asc">Code (A to Z)</flux:select.option>
                    <flux:select.option value="code_desc">Code (Z to A)</flux:select.option>
                    <flux:select.option value="students_desc">Most Enrolled Students</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Skeleton Loading State -->
    <div wire:loading wire:target="search, departmentFilter, headFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <!-- Main Programs Table -->
    <div wire:loading.remove wire:target="search, departmentFilter, headFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full min-w-[750px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[15%] min-w-[100px] px-4 py-3.5 whitespace-nowrap">Code</th>
                        <th class="w-[32%] min-w-[180px] px-4 py-3.5 whitespace-nowrap">Program Name</th>
                        <th class="w-[20%] min-w-[140px] px-4 py-3.5 whitespace-nowrap">Department</th>
                        <th class="w-[20%] min-w-[140px] px-4 py-3.5 whitespace-nowrap">Program Head</th>
                        <th class="w-[8%] min-w-[90px] px-4 py-3.5 text-center whitespace-nowrap">Students</th>
                        <th class="w-[5%] min-w-[80px] px-4 py-3.5 text-right whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($programs as $prog)
                        <tr wire:key="{{ $prog->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Program Code -->
                            <td class="px-4 py-3.5 font-mono text-xs font-bold text-[#9b0000] dark:text-[#f89696] whitespace-nowrap">
                                {{ $prog->code }}
                            </td>
                            
                            <!-- Program Name -->
                            <td class="px-4 py-3.5 dark:text-zinc-200 font-semibold" title="{{ $prog->name }}">
                                {{ $prog->name }}
                            </td>

                            <!-- Department -->
                            <td class="px-4 py-3.5 dark:text-zinc-300 text-xs whitespace-nowrap" title="{{ $prog->department?->name }}">
                                <span class="font-bold text-zinc-900 dark:text-zinc-100">{{ $prog->department?->code }}</span>
                                <span class="text-zinc-500 block truncate max-w-[180px]">{{ $prog->department?->name }}</span>
                            </td>

                            <!-- Program Head -->
                            <td class="px-4 py-3.5 dark:text-zinc-300 text-xs whitespace-nowrap">
                                @if($prog->programHead)
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100 block">{{ $prog->programHead->formatted_name }}</span>
                                    <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-mono">{{ $prog->programHead->employee_number }}</span>
                                @else
                                    <span class="text-zinc-400 italic">Unassigned</span>
                                @endif
                            </td>

                            <!-- Enrolled Students Count -->
                            <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                <flux:badge size="sm" color="purple" class="font-bold cursor-pointer hover:bg-purple-200 dark:hover:bg-purple-900 transition-colors" wire:click="manageStudents({{ $prog->id }})" tooltip="Click to manage enrolled students">
                                    {{ $prog->students_count }} {{ Str::plural('Student', $prog->students_count) }}
                                </flux:badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editProgram({{ $prog->id }})">
                                            Edit Details
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $prog->id }})">
                                            Delete Program
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon name="academic-cap" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-base font-semibold text-zinc-700 dark:text-zinc-300">No programs found</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No academic programs match your current search or filter criteria.</p>
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
            {{ $programs->links() }}
        </div>
    </div>

    <!-- Create/Edit Program Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <flux:heading size="lg">{{ $editingProgram ? 'Edit Program' : 'Create Program' }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
            </div>
            
            <form wire:submit="saveProgram" class="flex flex-col gap-4">
                <flux:input wire:model="code" label="Program Code" type="text" placeholder="e.g. BSCS" required />
                <flux:input wire:model="name" label="Program Name" type="text" placeholder="e.g. Bachelor of Science in Computer Science" required />
                
                <flux:select wire:model.live="department_id" label="Department / College" required>
                    <flux:select.option value="">Select Department</flux:select.option>
                    @foreach($departmentsList as $dept)
                        <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="program_head_id" label="Assigned Program Head (Optional)">
                    <flux:select.option value="">Unassigned (None)</flux:select.option>
                    @foreach($programHeadsList as $head)
                        <flux:select.option value="{{ $head->id }}">{{ $head->formatted_name }} ({{ $head->employee_number }})</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingProgram ? 'Save Changes' : 'Create Program' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingProgram)
    <x-confirmation-modal 
        title="Delete Program" 
        on-confirm="deleteProgram" 
        on-cancel="$set('showDeleteModal', false)" 
        :disabled="$deletingProgram->students()->exists()"
    >
        Are you sure you want to delete this academic program? This action cannot be undone.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Code</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProgram->code }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProgram->department?->code }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProgram->name }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program Head</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingProgram->programHead->formatted_name ?? 'Unassigned' }}</span>
                </div>
            </div>
        </x-slot:details>

        @if($deletingProgram->students()->exists())
            <x-slot:warning>
                This program currently has {{ $deletingProgram->students()->count() }} student(s) enrolled under it. You cannot delete it until those student records are re-assigned or removed. You can click the Students badge in the table to manage or unenroll them.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif

    <!-- Manage Enrolled Students Modal -->
    @if($showStudentsModal && $managingProgram)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm p-3 sm:p-4 flex min-h-full items-center justify-center">
        <div class="bg-white dark:bg-zinc-900 rounded-xl shadow-2xl w-full border border-zinc-200 dark:border-zinc-800 flex flex-col my-auto overflow-hidden" style="max-width: 540px !important; max-height: calc(100vh - 3.5rem) !important;">
            <!-- Header -->
            <div class="px-4 py-3 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 shrink-0 flex justify-between items-center z-10">
                <div>
                    <div class="flex items-center gap-2">
                        <flux:badge color="purple" size="sm" class="font-mono font-bold">{{ $managingProgram->code }}</flux:badge>
                        <flux:heading size="md">Enrolled Students</flux:heading>
                    </div>
                    <flux:subheading class="text-[11px] mt-0.5">{{ $managingProgram->name }} ({{ $managingProgram->department?->code }})</flux:subheading>
                </div>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showStudentsModal', false)" />
            </div>

            <!-- Scrollable Body (min-h-0 forces flex container scrolling) -->
            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-3.5">
                <!-- Search & Add Section -->
                <div class="flex flex-col gap-1.5 bg-gray-50 dark:bg-zinc-800/40 p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Add / Transfer Student</span>
                    <flux:input wire:model.live.debounce.300ms="studentSearch" icon="magnifying-glass" placeholder="Search student name or number..." class="w-full" />

                    @if($studentSearch && $studentSearchResults->isNotEmpty())
                        <div class="flex flex-col divide-y divide-zinc-200 dark:divide-zinc-700 mt-1 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 max-h-36 overflow-y-auto">
                            @foreach($studentSearchResults as $searchResult)
                                <div class="flex justify-between items-center px-3 py-1.5 text-xs hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <div class="truncate pr-2">
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100 inline-block">{{ $searchResult->formatted_name }}</span>
                                        <span class="text-[11px] font-mono text-zinc-400 inline-block ml-1">({{ $searchResult->student_number }})</span>
                                    </div>
                                    <flux:button size="xs" variant="primary" icon="plus" class="shrink-0 text-[11px] py-0" wire:click="addStudentToProgram({{ $searchResult->id }})">
                                        Enroll
                                    </flux:button>
                                </div>
                            @endforeach
                        </div>
                    @elseif($studentSearch && $studentSearchResults->isEmpty())
                        <p class="text-[11px] text-zinc-500 italic mt-0.5">No matching unenrolled students found.</p>
                    @endif
                </div>

                <!-- Enrolled Students List Header -->
                <div class="flex justify-between items-center">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-800 dark:text-zinc-200">
                        Currently Enrolled ({{ $enrolledStudents->count() }})
                    </span>
                    @if($enrolledStudents->isNotEmpty())
                        <flux:button size="xs" variant="danger" icon="user-minus" class="text-[11px] py-0" wire:click="unenrollAllStudents" wire:confirm="Are you sure you want to remove ALL students from this program?">
                            Unenroll All
                        </flux:button>
                    @endif
                </div>

                <!-- Enrolled Students List Table -->
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800 max-h-40 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 dark:bg-zinc-800/80 text-[11px] font-semibold text-zinc-600 dark:text-zinc-300 uppercase">
                            <tr>
                                <th class="px-3 py-2">Student</th>
                                <th class="px-3 py-2">Year/Sec</th>
                                <th class="px-3 py-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                            @forelse($enrolledStudents as $student)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/30">
                                    <td class="px-3 py-1.5 font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $student->formatted_name }}
                                        <span class="block text-[11px] font-mono text-zinc-400">{{ $student->student_number }}</span>
                                    </td>
                                    <td class="px-3 py-1.5 text-[11px] text-zinc-500">
                                        Yr {{ $student->year_level }} {{ $student->section ? '('.$student->section.')' : '' }}
                                    </td>
                                    <td class="px-3 py-1.5 text-right">
                                        <flux:button size="xs" variant="ghost" icon="trash" class="text-red-500 hover:text-red-600 text-[11px] py-0" wire:click="removeStudentFromProgram({{ $student->id }})">
                                            Remove
                                        </flux:button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-3 py-6 text-center text-zinc-500">
                                        <p class="text-xs font-semibold">No students enrolled in this program.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 border-t border-zinc-100 dark:border-zinc-800 shrink-0 flex justify-end">
                <flux:button size="sm" variant="primary" wire:click="$set('showStudentsModal', false)">Close</flux:button>
            </div>
        </div>
    </div>
    @endif
</div>
