<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\User;
use App\Models\Student;
use App\Models\Program;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.manage-students-skeleton');
    }

    use WithPagination;

    // Fields for Create/Edit Modal
    public string $email = '';
    public bool $showModal = false;
    public ?User $editingUser = null;
    public bool $showDeleteModal = false;
    public ?User $deletingUser = null;

    // Student specific
    public string $student_number = '';
    public string $first_name = '';
    public string $middle_name = '';
    public string $last_name = '';
    public string $suffix = '';
    public string $program_id = '';
    public string $year_level = '';
    public string $section = '';

    // Filters
    public string $selectedProgramId = '';
    public string $selectedYearLevel = '';
    public string $search = '';
    public string $sortDirection = 'asc'; // 'asc' (A-Z) or 'desc' (Z-A)

    public function updatedSelectedProgramId() { $this->resetPage(); }
    public function updatedSelectedYearLevel() { $this->resetPage(); }
    public function updatedSortDirection() { $this->resetPage(); }
    public function updatedSearch() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'selectedProgramId', 'selectedYearLevel', 'sortDirection']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset([
            'email', 'editingUser',
            'student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'program_id', 'year_level', 'section'
        ]);
        $this->showModal = true;
    }

    public function with(): array
    {
        $query = User::query()->whereHas('student');

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
                          ->orWhere('first_name', 'like', '%' . $this->search . '%')
                          ->orWhere('last_name', 'like', '%' . $this->search . '%')
                          ->orWhere('section', 'like', '%' . $this->search . '%');
                  });
            });
        }

        $orderDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'users' => $query->orderBy('name', $orderDirection)->paginate(10),
            'programs' => Program::orderBy('name')->get(),
        ];
    }

    public function createUser()
    {
        $this->validate([
            'student_number' => 'required|string|unique:students,student_number',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
        ]);

        $student = Student::create([
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix ?: null,
            'program_id' => $this->program_id,
            'year_level' => $this->year_level,
            'section' => $this->section ?: null,
            'status' => 'regular',
        ]);

        $user = User::create([
            'name' => $student->formatted_name,
            'email' => $this->email,
            'student_id' => $student->id,
            'password' => bcrypt('password'),
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

        $this->student_number = $user->student->student_number ?? '';
        $this->first_name = $user->student->first_name ?? '';
        $this->middle_name = $user->student->middle_name ?? '';
        $this->last_name = $user->student->last_name ?? '';
        $this->suffix = $user->student->suffix ?? '';
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
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:255',
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|integer|between:1,4',
            'section' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
        ]);

        $this->editingUser->student->update([
            'student_number' => $this->student_number,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name ?: null,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix ?: null,
            'program_id' => $this->program_id,
            'year_level' => $this->year_level,
            'section' => $this->section ?: null,
        ]);

        $this->editingUser->update([
            'name' => $this->editingUser->student->fresh()->formatted_name,
            'email' => $this->email,
        ]);

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
            text: 'The student account status has been updated.',
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
    
    <!-- Filters Bar -->
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by name, email or student ID..." />
        </div>
        
        <div class="w-full md:w-64">
            <flux:select wire:model.live="selectedProgramId" placeholder="Filter by Program">
                <flux:select.option value="">All Programs</flux:select.option>
                <flux:select.option value="none">Unassigned (None)</flux:select.option>
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
        
        <div class="flex items-center gap-2">
            <!-- Filter Icon Dropdown (A-Z / Z-A) -->
            <flux:dropdown align="end">
                <flux:button variant="outline" icon="funnel" tooltip="Sort Order">
                    {{ $sortDirection === 'desc' ? 'Z-A' : 'A-Z' }}
                </flux:button>

                <flux:menu>
                    <flux:menu.item icon="bars-arrow-down" wire:click="$set('sortDirection', 'asc')" :current="$sortDirection === 'asc'">
                        A to Z (A-Z)
                    </flux:menu.item>
                    <flux:menu.item icon="bars-arrow-up" wire:click="$set('sortDirection', 'desc')" :current="$sortDirection === 'desc'">
                        Z to A (Z-A)
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>

            @if($search || $selectedProgramId || $selectedYearLevel || $sortDirection !== 'asc')
                <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
            @endif
        </div>
    </div>
    
    <div wire:loading wire:target="search, selectedProgramId, selectedYearLevel, sortDirection, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="7" />
    </div>

    <div wire:loading.remove wire:target="search, selectedProgramId, selectedYearLevel, sortDirection, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700 shadow-2xs">
            <table class="w-full min-w-[850px] divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th class="w-[14%] min-w-[120px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Student ID</th>
                        <th class="w-[22%] min-w-[180px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Full Name</th>
                        <th class="w-[20%] min-w-[160px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Email</th>
                        <th class="w-[14%] min-w-[130px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 whitespace-nowrap">Program & Section</th>
                        <th class="w-[10%] min-w-[100px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-center whitespace-nowrap">Year Level</th>
                        <th class="w-[10%] min-w-[100px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-center whitespace-nowrap">Account Status</th>
                        <th class="w-[10%] min-w-[80px] px-4 py-3 font-semibold text-gray-900 dark:text-zinc-100 text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($users as $user)
                        <tr wire:key="{{ $user->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40 transition-colors">
                            <td class="px-4 py-3 dark:text-zinc-300 font-mono text-xs font-bold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                                {{ $user->student?->student_number }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-300 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $user->student?->formatted_name ?? $user->name }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-400 text-xs">
                                {{ $user->email }}
                            </td>
                            <td class="px-4 py-3 dark:text-zinc-300 text-xs whitespace-nowrap">
                                <span class="font-bold block text-zinc-900 dark:text-zinc-100">{{ $user->student?->program?->code ?: 'Unassigned' }}</span>
                                @if($user->student?->section)
                                    <span class="text-zinc-500 dark:text-zinc-400 font-mono text-[11px] block">{{ $user->student->section }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @php
                                    $yr = $user->student?->year_level;
                                    $yrLabel = match((int)$yr) {
                                        1 => '1st Year',
                                        2 => '2nd Year',
                                        3 => '3rd Year',
                                        4 => '4th Year',
                                        default => $yr ? "Year {$yr}" : '—'
                                    };
                                @endphp
                                <flux:badge variant="neutral" size="sm" class="font-semibold whitespace-nowrap">
                                    {{ $yrLabel }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button wire:click="toggleActive({{ $user->id }})" class="cursor-pointer">
                                    <flux:badge variant="{{ $user->is_active ? 'success' : 'danger' }}" size="sm">
                                        {{ $user->is_active ? 'Active' : 'Disabled' }}
                                    </flux:badge>
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <flux:dropdown align="end">
                                    <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                                        Action
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item icon="pencil-square" wire:click="editUser({{ $user->id }})">
                                            Edit Details
                                        </flux:menu.item>
                                        
                                        <flux:menu.item icon="{{ $user->is_active ? 'pause-circle' : 'play-circle' }}" wire:click="toggleActive({{ $user->id }})">
                                            {{ $user->is_active ? 'Disable Account' : 'Enable Account' }}
                                        </flux:menu.item>

                                        <flux:menu.separator />

                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">
                                            Delete Account
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
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
    </div>

    <!-- Create / Edit Student Modal -->
    <flux:modal wire:model="showModal" class="min-w-[480px]">
        <div class="space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">
                    {{ $editingUser ? 'Edit Student Account' : 'Create New Student Account' }}
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Fill in student details and academic program assignment below.</p>
            </div>

            <form wire:submit="{{ $editingUser ? 'updateUser' : 'createUser' }}" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="first_name" label="First Name" type="text" required />
                    <flux:input wire:model="middle_name" label="Middle Name (Optional)" type="text" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="last_name" label="Last Name" type="text" required />
                    <flux:input wire:model="suffix" label="Suffix (Optional)" type="text" placeholder="e.g. Jr., Sr., III" />
                </div>

                <flux:input wire:model="email" label="Email Address" type="email" required />

                <flux:input wire:model="student_number" label="Student Number" type="text" placeholder="e.g. 2026-00001" required />

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Academic Program</label>
                        <select wire:model="program_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" required>
                            <option value="">Select Program...</option>
                            @foreach($programs as $prog)
                                <option value="{{ $prog->id }}">{{ $prog->code }} - {{ $prog->name }}</option>
                            @endforeach
                        </select>
                        @error('program_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-zinc-900 dark:text-white mb-1">Year Level</label>
                        <select wire:model="year_level" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" required>
                            <option value="">Select Year...</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                        @error('year_level') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <flux:input wire:model="section" label="Section (Optional)" type="text" placeholder="e.g. BSIT-3A" />

                <div class="flex justify-end gap-2 pt-4">
                    <flux:button variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ $editingUser ? 'Save Changes' : 'Create Student' }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

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
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Full Name</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingUser->student?->formatted_name ?? $deletingUser->name }}</span>
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
