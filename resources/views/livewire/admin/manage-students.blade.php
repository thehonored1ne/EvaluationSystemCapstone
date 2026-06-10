<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\User;
use App\Models\Student;
use App\Models\Program;
use App\Models\Department;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    // Fields
    public string $email = '';
    public string $password = '';
    public bool $showModal = false;
    public ?User $editingUser = null;
    public bool $showDeleteModal = false;
    public ?User $deletingUser = null;

    // Student specific
    public string $student_number = '';
    public string $first_name = '';
    public string $last_name = '';
    public string $program_id = '';
    public string $year_level = '';
    public string $section = '';

    public string $selectedDepartmentId = '';
    public string $selectedProgramId = '';
    public string $selectedYearLevel = '';
    public string $search = '';

    public function updatedSelectedDepartmentId() { $this->resetPage(); }
    public function updatedSelectedProgramId() { $this->resetPage(); }
    public function updatedSelectedYearLevel() { $this->resetPage(); }

    public function updatedSearch() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedDepartmentId', 'selectedProgramId', 'selectedYearLevel']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset([
            'email', 'password', 'editingUser',
            'student_number', 'first_name', 'last_name', 'program_id', 'year_level', 'section'
        ]);
        $this->showModal = true;
    }

    public function with(): array
    {
        $query = User::query()->whereHas('student');

        if ($this->selectedDepartmentId === 'none') {
            $query->whereHas('student', function ($q) {
                $q->whereNull('program_id')
                  ->orWhereHas('program', function ($pq) {
                      $pq->whereNull('department_id');
                  });
            });
        } elseif ($this->selectedDepartmentId) {
            $query->whereHas('student.program', function ($q) {
                $q->where('department_id', $this->selectedDepartmentId);
            });
        }

        if ($this->selectedProgramId === 'none') {
            $query->whereHas('student', function ($q) {
                $q->whereNull('program_id');
            });
        } elseif ($this->selectedProgramId) {
            $query->whereHas('student', function ($q) {
                $q->where('program_id', $this->selectedProgramId);
            });
        }

        if ($this->selectedYearLevel) {
            $query->whereHas('student', function ($q) {
                $q->where('year_level', $this->selectedYearLevel);
            });
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhereHas('student', function ($sub) {
                      $sub->where('student_number', 'like', '%' . $this->search . '%')
                          ->orWhere('section', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return [
            'users' => $query->orderBy('id', 'desc')->paginate(10),
            'programs' => Program::orderBy('name')->get(),
            'departments' => Department::orderBy('name')->get(),
        ];
    }

    public function createUser()
    {
        $this->validate([
            'student_number' => 'required|string|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
        ]);

        $student = Student::create([
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'program_id' => $this->program_id,
            'year_level' => $this->year_level,
            'section' => $this->section ?: null,
            'status' => 'regular',
        ]);

        $user = User::create([
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'student_id' => $student->id,
            'password' => bcrypt($this->password),
            'is_active' => true,
        ]);

        $user->assignRole('student');

        $this->showModal = false;
        \Flux::toast(
            heading: 'Student Created',
            text: 'The student account has been successfully created.',
            variant: 'success'
        );
    }

    public function editUser(User $user)
    {
        $this->editingUser = $user;
        $this->email = $user->email;
        $this->password = '';

        $this->student_number = $user->student->student_number ?? '';
        $this->first_name = $user->student->first_name ?? '';
        $this->last_name = $user->student->last_name ?? '';
        $this->program_id = $user->student->program_id ?? '';
        $this->year_level = $user->student->year_level ?? '';
        $this->section = $user->student->section ?? '';

        $this->showModal = true;
    }

    public function updateUser()
    {
        $this->validate([
            'student_number' => 'required|string|unique:students,student_number,' . $this->editingUser->student_id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
            'password' => 'nullable|min:8',
        ]);

        $this->editingUser->student->update([
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'program_id' => $this->program_id,
            'year_level' => $this->year_level,
            'section' => $this->section ?: null,
        ]);

        $this->editingUser->update([
            'name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
        ]);

        if ($this->password) {
            $this->editingUser->update(['password' => bcrypt($this->password)]);
        }

        $this->showModal = false;
        \Flux::toast(
            heading: 'Student Updated',
            text: 'The student account has been successfully updated.',
            variant: 'success'
        );
    }

    public function toggleActive(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();

        \Flux::toast(
            heading: $user->is_active ? 'Account Enabled' : 'Account Disabled',
            text: "The user account status has been updated.",
            variant: 'success'
        );
    }

    public function confirmDelete(User $user)
    {
        $this->deletingUser = $user;
        $this->showDeleteModal = true;
    }

    public function deleteUser()
    {
        if (!$this->deletingUser) return;

        \Illuminate\Support\Facades\DB::transaction(function () {
            $student = $this->deletingUser->student;
            $this->deletingUser->delete();
            if ($student) {
                $student->delete();
            }
        });

        $this->showDeleteModal = false;
        $this->deletingUser = null;

        \Flux::toast(
            heading: 'Student Deleted',
            text: 'The student account has been successfully deleted.',
            variant: 'success'
        );
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl" level="1">Manage Students</flux:heading>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Create Student</flux:button>
    </div>
    
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or ID..." />
        </div>
        
        <div class="w-full md:w-64">
            <flux:select wire:model.live="selectedDepartmentId" placeholder="Filter by Department">
                <flux:select.option value="">All Departments</flux:select.option>
                <flux:select.option value="none">None</flux:select.option>
                @foreach($departments as $dept)
                    <flux:select.option value="{{ $dept->id }}">{{ $dept->code }} - {{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-64">
            <flux:select wire:model.live="selectedProgramId" placeholder="Filter by Program">
                <flux:select.option value="">All Programs</flux:select.option>
                <flux:select.option value="none">None</flux:select.option>
                @foreach($programs as $prog)
                    <flux:select.option value="{{ $prog->id }}">{{ $prog->code }} - {{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-full md:w-48">
            <flux:select wire:model.live="selectedYearLevel" placeholder="Filter by Year Level">
                <flux:select.option value="">All Year Levels</flux:select.option>
                <flux:select.option value="1">1st Year</flux:select.option>
                <flux:select.option value="2">2nd Year</flux:select.option>
                <flux:select.option value="3">3rd Year</flux:select.option>
                <flux:select.option value="4">4th Year</flux:select.option>
            </flux:select>
        </div>
        
        <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
    </div>
    
    <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700">
        <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
            <thead class="bg-gray-50 dark:bg-zinc-800">
                <tr>
                    <th class="w-[30%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Name</th>
                    <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Student ID</th>
                    <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Email Address</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Program & Section</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Status</th>
                    <th class="w-[15%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                @forelse ($users as $user)
                    <tr wire:key="{{ $user->id }}">
                        <td class="px-4 py-3 dark:text-zinc-300 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 font-mono text-xs">{{ $user->student?->student_number }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 text-xs">{{ $user->email }}</td>
                        <td class="px-4 py-3 dark:text-zinc-300 text-xs">
                            <span class="font-semibold block">{{ $user->student?->program?->code ?: 'None' }}</span>
                            @if($user->student?->section)
                                <span class="text-zinc-500 font-mono text-[10px]">{{ $user->student->section }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge variant="{{ $user->is_active ? 'success' : 'danger' }}" size="sm">
                                {{ $user->is_active ? 'Active' : 'Disabled' }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="editUser({{ $user->id }})">
                                    Edit
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $user->id }})">
                                    {{ $user->is_active ? 'Disable' : 'Enable' }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" class="text-red-500 hover:text-red-600 dark:hover:text-red-400" wire:click="confirmDelete({{ $user->id }})">
                                    Delete
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
                            No student accounts found matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $users->links() }}
    </div>

    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh]">
            <flux:heading size="lg" class="mb-4">{{ $editingUser ? 'Edit Student' : 'Create Student' }}</flux:heading>
            
            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="flex flex-col gap-4">
                
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />
                
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="student_number" label="Student Number" type="text" placeholder="e.g. STU-001" required />
                    <flux:input wire:model="section" label="Section" type="text" placeholder="e.g. BSCS-3A" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-searchable-select 
                        name="program_id" 
                        label="Program" 
                        placeholder="Select Program" 
                        required 
                        :options="array_merge([['value' => '', 'label' => 'Select Program']], $programs->map(fn($p) => ['value' => (string)$p->id, 'label' => $p->code . ' - ' . $p->name])->toArray())" 
                    />

                    <flux:select wire:model="year_level" label="Year Level" required>
                        <flux:select.option value="">Select Year</flux:select.option>
                        <flux:select.option value="1">1st Year</flux:select.option>
                        <flux:select.option value="2">2nd Year</flux:select.option>
                        <flux:select.option value="3">3rd Year</flux:select.option>
                        <flux:select.option value="4">4th Year</flux:select.option>
                    </flux:select>
                </div>

                <flux:input wire:model="password" label="Password" type="password" :placeholder="$editingUser ? 'Leave blank to keep current' : 'Password'" :required="!$editingUser" />

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingUser)
    <x-confirmation-modal 
        title="Delete Student Account" 
        on-confirm="deleteUser" 
        on-cancel="$set('showDeleteModal', false)"
    >
        Are you sure you want to delete this student account? This action cannot be undone and will remove all login access.

        <x-slot:details>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Student ID</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->student?->student_number ?: 'None' }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Email Address</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->email }}</span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Program & Section</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ $deletingUser->student?->program?->code ?: 'None' }}
                        @if($deletingUser->student?->section)
                            - {{ $deletingUser->student->section }}
                        @endif
                    </span>
                </div>
            </div>
        </x-slot:details>

        @if(\App\Models\Evaluation::where('evaluator_id', $deletingUser->id)->orWhere('evaluatee_id', $deletingUser->id)->exists())
            <x-slot:warning>
                Deleting this student will permanently delete all evaluations they submitted or received.
            </x-slot:warning>
        @elseif($deletingUser->student?->classes()->exists())
            <x-slot:warning>
                This student is enrolled in {{ $deletingUser->student->classes()->count() }} class(es). Deleting this student will remove them from these classes.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>
