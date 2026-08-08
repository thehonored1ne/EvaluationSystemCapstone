<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Program;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Filter properties
    public string $search = '';
    public string $headFilter = '';
    public string $sortBy = 'name_asc';

    // Department Form properties
    public string $code = '';
    public string $name = '';
    public string $program_head_id = '';
    
    public bool $showModal = false;
    public bool $showDeleteModal = false;

    public ?Department $editingDepartment = null;
    public ?Department $deletingDepartment = null;

    public function updatedSearch() { $this->resetPage(); }
    public function updatedHeadFilter() { $this->resetPage(); }
    public function updatedSortBy() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'headFilter']);
        $this->sortBy = 'name_asc';
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset(['code', 'name', 'program_head_id', 'editingDepartment']);
        $this->showModal = true;
    }

    public function saveDepartment()
    {
        $rules = [
            'code' => 'required|string|max:50|unique:departments,code' . ($this->editingDepartment ? ',' . $this->editingDepartment->id : ''),
            'name' => 'required|string|max:255',
            'program_head_id' => 'nullable|exists:employees,id',
        ];

        $this->validate($rules);

        $codeFormatted = strtoupper(trim($this->code));
        $headIdValue = $this->program_head_id ?: null;

        if ($this->editingDepartment) {
            $oldHeadId = $this->editingDepartment->program_head_id;

            $this->editingDepartment->update([
                'code' => $codeFormatted,
                'name' => $this->name,
                'program_head_id' => $headIdValue,
            ]);

            // Sync old and new Program Head department relationship
            if ($oldHeadId && $oldHeadId != $headIdValue) {
                Employee::where('id', $oldHeadId)->update(['department_id' => null]);
            }
            if ($headIdValue) {
                Employee::where('id', $headIdValue)->update(['department_id' => $this->editingDepartment->id]);
            }

            \Flux::toast(
                heading: 'Department Updated',
                text: 'Department information has been updated successfully.',
                variant: 'success'
            );
        } else {
            $department = Department::create([
                'code' => $codeFormatted,
                'name' => $this->name,
                'program_head_id' => $headIdValue,
            ]);

            if ($headIdValue) {
                Employee::where('id', $headIdValue)->update(['department_id' => $department->id]);
            }

            \Flux::toast(
                heading: 'Department Created',
                text: 'New academic department created successfully.',
                variant: 'success'
            );
        }

        $this->showModal = false;
    }

    public function editDepartment(Department $dept)
    {
        $this->editingDepartment = $dept;
        $this->code = $dept->code;
        $this->name = $dept->name;
        
        // Auto-detect program head from explicit column or employee department relationship
        $headId = $dept->program_head_id ?: $dept->programHeads->first()?->id;
        $this->program_head_id = $headId ? (string)$headId : '';
        
        $this->showModal = true;
    }

    public function confirmDelete(Department $dept)
    {
        $this->deletingDepartment = $dept;
        $this->showDeleteModal = true;
    }

    public function deleteDepartment()
    {
        if (!$this->deletingDepartment) return;

        $deptToDelete = $this->deletingDepartment;

        // Check if department has associated programs or active non-head employees
        $hasNonHeadEmployees = Employee::where('department_id', $deptToDelete->id)
            ->when($deptToDelete->program_head_id, fn($q) => $q->where('id', '!=', $deptToDelete->program_head_id))
            ->where('role', '!=', 'program head')
            ->exists();

        if ($deptToDelete->programs()->exists() || $hasNonHeadEmployees) {
            \Flux::toast(
                heading: 'Cannot Delete Department',
                text: 'This department has academic programs or faculty assigned to it. Re-assign or remove them first.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            $this->deletingDepartment = null;
            return;
        }

        // Unlink program head if assigned
        if ($deptToDelete->program_head_id) {
            Employee::where('id', $deptToDelete->program_head_id)->update(['department_id' => null]);
        }

        // Reset component properties BEFORE deleting to avoid Livewire dehydration 404
        $this->deletingDepartment = null;
        $this->editingDepartment = null;
        $this->showDeleteModal = false;

        $deptToDelete->delete();

        \Flux::toast(
            heading: 'Department Deleted',
            text: 'Academic department deleted successfully.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        // 1. Calculate Summary Statistics
        $totalDepartments = Department::count();
        
        // Auto-detect departments with active Program Head leadership
        $assignedHeadsCount = Department::where(function ($q) {
            $q->whereNotNull('program_head_id')
              ->orWhereHas('employees', fn($empQ) => $empQ->where('role', 'program head'));
        })->count();

        $totalPrograms = Program::count();
        $totalFaculty = Employee::where('role', 'faculty')->whereNotNull('department_id')->count();

        // 2. Query Departments with relations and counts
        $query = Department::query()
            ->with(['programHead', 'programHeads'])
            ->withCount(['programs', 'employees']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('name', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->headFilter === 'assigned') {
            $query->where(function ($q) {
                $q->whereNotNull('program_head_id')
                  ->orWhereHas('employees', fn($empQ) => $empQ->where('role', 'program head'));
            });
        } elseif ($this->headFilter === 'unassigned') {
            $query->whereNull('program_head_id')
                  ->whereDoesntHave('employees', fn($empQ) => $empQ->where('role', 'program head'));
        }

        match ($this->sortBy) {
            'name_desc' => $query->orderBy('name', 'desc'),
            'code_asc' => $query->orderBy('code', 'asc'),
            'code_desc' => $query->orderBy('code', 'desc'),
            'programs_desc' => $query->orderBy('programs_count', 'desc')->orderBy('name', 'asc'),
            'faculty_desc' => $query->orderBy('employees_count', 'desc')->orderBy('name', 'asc'),
            default => $query->orderBy('name', 'asc'),
        };

        // Available active program heads list
        $programHeadsList = Employee::where('role', 'program head')->where('status', 'active')->orderBy('last_name')->get();

        return [
            'departments' => $query->paginate(10),
            'totalDepartments' => $totalDepartments,
            'assignedHeadsCount' => $assignedHeadsCount,
            'totalPrograms' => $totalPrograms,
            'totalFaculty' => $totalFaculty,
            'programHeadsList' => $programHeadsList,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-8">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Manage Departments</flux:heading>
            <flux:subheading class="text-left">Institutional college & department management, program head assignments, academic programs, and department faculty members.</flux:subheading>
        </div>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Department</flux:button>
    </div>

    <!-- Top Row Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1: Total Departments -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Total Departments</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalDepartments" /></span>
                </div>
                <flux:icon name="building-office-2" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Active institutional colleges & departments</span>
        </div>

        <!-- Card 2: Assigned Program Heads -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Assigned Program Heads</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$assignedHeadsCount" /></span>
                </div>
                <flux:icon name="user-group" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Departments with active Program Head leadership</span>
        </div>

        <!-- Card 3: Total Programs -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Academic Programs</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalPrograms" /></span>
                </div>
                <flux:icon name="academic-cap" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Degree programs across departments</span>
        </div>

        <!-- Card 4: Faculty Members -->
        <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs hover:shadow-md transition-all duration-200" style="border-left: 5px solid #800000 !important;">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Faculty Members</span>
                    <span class="text-3xl font-bold text-zinc-900 dark:text-zinc-100 block mt-1"><x-odometer :value="$totalFaculty" /></span>
                </div>
                <flux:icon name="users" class="size-6 text-[#800000] dark:text-red-400" />
            </div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400 font-medium mt-2">Active professors assigned to departments</span>
        </div>
    </div>

    <!-- Search & Advanced Filter Controls Bar -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <!-- Search Input Bar -->
        <div class="flex items-center gap-3 w-full">
            <div class="flex-1">
                <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by department code or name..." />
            </div>
            <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset All Filters" class="shrink-0" />
        </div>

        <!-- Filter Dropdowns Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
            <!-- Program Head Assignment Filter -->
            <div>
                <flux:select wire:model.live="headFilter" class="w-full" placeholder="All Program Head Status">
                    <flux:select.option value="">All Program Head Status</flux:select.option>
                    <flux:select.option value="assigned">Assigned Program Head</flux:select.option>
                    <flux:select.option value="unassigned">Unassigned Program Head</flux:select.option>
                </flux:select>
            </div>

            <!-- Sort By -->
            <div>
                <flux:select wire:model.live="sortBy" class="w-full">
                    <flux:select.option value="name_asc">Department Name (A to Z)</flux:select.option>
                    <flux:select.option value="name_desc">Department Name (Z to A)</flux:select.option>
                    <flux:select.option value="code_asc">Code (A to Z)</flux:select.option>
                    <flux:select.option value="code_desc">Code (Z to A)</flux:select.option>
                    <flux:select.option value="programs_desc">Most Academic Programs</flux:select.option>
                    <flux:select.option value="faculty_desc">Most Faculty Members</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Skeleton Loading State -->
    <div wire:loading wire:target="search, headFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <!-- Main Departments Table -->
    <div wire:loading.remove wire:target="search, headFilter, sortBy, clearFilters, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-xl border border-gray-200 dark:border-zinc-700 shadow-xs">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800 text-xs font-semibold text-gray-700 dark:text-zinc-300 uppercase tracking-wider">
                    <tr>
                        <th class="w-[14%] px-4 py-3.5">Code</th>
                        <th class="w-[32%] px-4 py-3.5">Department Name</th>
                        <th class="w-[24%] px-4 py-3.5">Assigned Program Head</th>
                        <th class="w-[10%] px-4 py-3.5 text-center">Programs</th>
                        <th class="w-[14%] px-4 py-3.5 text-center">Faculty Members</th>
                        <th class="w-[6%] px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($departments as $dept)
                        <tr wire:key="{{ $dept->id }}" class="hover:bg-gray-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Department Code -->
                            <td class="px-4 py-3.5 font-mono text-xs font-bold text-[#800000] dark:text-red-400">
                                {{ $dept->code }}
                            </td>
                            
                            <!-- Department Name -->
                            <td class="px-4 py-3.5 dark:text-zinc-200 font-semibold truncate" title="{{ $dept->name }}">
                                {{ $dept->name }}
                            </td>

                            <!-- Assigned Program Head (Auto-Detected) -->
                            <td class="px-4 py-3.5 dark:text-zinc-300 text-xs">
                                @php
                                    $detectedHeads = $dept->programHeads;
                                    if ($detectedHeads->isEmpty() && $dept->programHead) {
                                        $detectedHeads = collect([$dept->programHead]);
                                    }
                                @endphp
                                @if($detectedHeads->isNotEmpty())
                                    @foreach($detectedHeads as $head)
                                        <div class="mb-1 last:mb-0">
                                            <span class="font-semibold text-zinc-900 dark:text-zinc-100 block">{{ $head->formatted_name }}</span>
                                            <span class="text-[11px] text-zinc-400 dark:text-zinc-500 font-mono">{{ $head->employee_number }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    <span class="text-zinc-400 italic">Unassigned</span>
                                @endif
                            </td>

                            <!-- Programs Count -->
                            <td class="px-4 py-3.5 text-center">
                                <flux:badge size="sm" color="purple" class="font-bold">
                                    {{ $dept->programs_count }} {{ Str::plural('Program', $dept->programs_count) }}
                                </flux:badge>
                            </td>

                            <!-- Faculty Count -->
                            <td class="px-4 py-3.5 text-center">
                                <flux:badge size="sm" color="indigo" class="font-bold">
                                    {{ $dept->employees_count }} {{ Str::plural('Faculty Member', $dept->employees_count) }}
                                </flux:badge>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3.5 text-right">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editDepartment({{ $dept->id }})">
                                            Edit Details
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $dept->id }})">
                                            Delete Department
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon name="building-office-2" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                    <p class="text-base font-semibold text-zinc-700 dark:text-zinc-300">No departments found</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">No departments match your current search or filter criteria.</p>
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
            {{ $departments->links() }}
        </div>
    </div>

    <!-- Create/Edit Department Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800 overflow-y-auto max-h-[90vh] space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <flux:heading size="lg">{{ $editingDepartment ? 'Edit Department' : 'Create Department' }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('showModal', false)" />
            </div>
            
            <form wire:submit="saveDepartment" class="flex flex-col gap-4">
                <flux:input wire:model="code" label="Department Code" type="text" placeholder="e.g. CCS" required />
                <flux:input wire:model="name" label="Department Name" type="text" placeholder="e.g. College of Computer Studies" required />
                
                <flux:select wire:model="program_head_id" label="Assigned Program Head (Optional)">
                    <flux:select.option value="">Unassigned (None)</flux:select.option>
                    @foreach($programHeadsList as $head)
                        <flux:select.option value="{{ $head->id }}">{{ $head->formatted_name }} ({{ $head->employee_number }})</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end gap-2 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-3">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingDepartment ? 'Save Changes' : 'Create Department' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingDepartment)
    <x-confirmation-modal 
        title="Delete Department" 
        on-confirm="deleteDepartment" 
        on-cancel="$set('showDeleteModal', false)" 
        :disabled="$deletingDepartment->programs()->exists() || \App\Models\Employee::where('department_id', $deletingDepartment->id)->exists()"
    >
        Are you sure you want to delete this academic department? This action cannot be undone.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department Code</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingDepartment->code }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Assigned Program Head</span>
                    @php
                        $delHeads = $deletingDepartment->programHeads;
                        if ($delHeads->isEmpty() && $deletingDepartment->programHead) {
                            $delHeads = collect([$deletingDepartment->programHead]);
                        }
                    @endphp
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ $delHeads->isNotEmpty() ? $delHeads->pluck('formatted_name')->join(', ') : 'Unassigned' }}
                    </span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Department Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingDepartment->name }}</span>
                </div>
            </div>
        </x-slot:details>

        @if($deletingDepartment->programs()->exists() || \App\Models\Employee::where('department_id', $deletingDepartment->id)->exists())
            <x-slot:warning>
                This department currently has {{ $deletingDepartment->programs()->count() }} academic program(s) and {{ \App\Models\Employee::where('department_id', $deletingDepartment->id)->count() }} employee(s) attached to it. You cannot delete it until those relationships are re-assigned or removed.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>

