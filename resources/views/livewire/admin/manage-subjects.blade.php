<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use App\Models\Subject;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }
    use WithPagination;

    public string $code = '';
    public string $name = '';
    public string $description = '';
    public int $units = 3;
    public bool $showModal = false;
    public bool $showDeleteModal = false;
    public ?Subject $editingSubject = null;
    public ?Subject $deletingSubject = null;

    public string $search = '';

    public function updatedSearch() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search']);
        $this->resetPage();
    }

    public function prepareCreate()
    {
        $this->reset(['code', 'name', 'description', 'units', 'editingSubject']);
        $this->units = 3;
        $this->showModal = true;
    }

    public function createSubject()
    {
        $this->validate([
            'code' => 'required|string|unique:subjects,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'units' => 'required|integer|min:1',
        ]);

        Subject::create([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description ?: null,
            'units' => $this->units,
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
        $this->description = $subject->description ?? '';
        $this->units = $subject->units;
        $this->showModal = true;
    }

    public function updateSubject()
    {
        $this->validate([
            'code' => 'required|string|unique:subjects,code,' . $this->editingSubject->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'units' => 'required|integer|min:1',
        ]);

        $this->editingSubject->update([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description ?: null,
            'units' => $this->units,
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

        // Check if subject has classes
        if ($this->deletingSubject->classes()->exists()) {
            \Flux::toast(
                heading: 'Cannot Delete Subject',
                text: 'This subject has classes associated with it. Delete the classes first.',
                variant: 'danger'
            );
            $this->showDeleteModal = false;
            return;
        }

        $this->deletingSubject->delete();
        $this->showDeleteModal = false;
        $this->deletingSubject = null;

        \Flux::toast(
            heading: 'Subject Deleted',
            text: 'The subject has been successfully deleted.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        $query = Subject::query()->withCount('classes');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('code', 'like', '%' . $this->search . '%')
                  ->orWhere('name', 'like', '%' . $this->search . '%');
            });
        }

        return [
            'subjects' => $query->orderBy('code')->paginate(10),
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl" level="1">Manage Subjects</flux:heading>
        <flux:button variant="primary" wire:click="prepareCreate" icon="plus">Add Subject</flux:button>
    </div>
    
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px]">
            <flux:input class="w-full" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search by code or name..." />
        </div>
        
        <flux:button variant="ghost" icon="arrow-path" wire:click="clearFilters" tooltip="Reset Filters" />
    </div>
    
    <div wire:loading wire:target="search, gotoPage, nextPage, previousPage" class="w-full">
        <x-skeleton type="table" :rows="5" :cols="6" />
    </div>

    <div wire:loading.remove wire:target="search, gotoPage, nextPage, previousPage" class="w-full flex flex-col gap-4">
        <div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-zinc-700">
            <table class="w-full table-fixed divide-y divide-gray-200 dark:divide-zinc-700 text-sm text-left">
                <thead class="bg-gray-50 dark:bg-zinc-800">
                    <tr>
                        <th class="w-[20%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Code</th>
                        <th class="w-[35%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Name</th>
                        <th class="w-[25%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100">Description</th>
                        <th class="w-[10%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-center">Units</th>
                        <th class="w-[10%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-center">Classes</th>
                        <th class="w-[10%] px-4 py-3 font-medium text-gray-900 dark:text-zinc-100 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                    @forelse ($subjects as $subject)
                        <tr wire:key="{{ $subject->id }}">
                            <td class="px-4 py-3 font-mono text-xs font-semibold dark:text-zinc-300">{{ $subject->code }}</td>
                            <td class="px-4 py-3 dark:text-zinc-300 font-medium">{{ $subject->name }}</td>
                            <td class="px-4 py-3 dark:text-zinc-400 text-xs truncate max-w-xs" title="{{ $subject->description }}">{{ $subject->description ?: 'No description' }}</td>
                            <td class="px-4 py-3 dark:text-zinc-300 text-center">{{ $subject->units }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" color="zinc">
                                    {{ $subject->classes_count }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="editSubject({{ $subject->id }})">
                                        Edit
                                    </flux:button>
                                    <flux:button size="sm" variant="ghost" class="text-red-500 hover:text-red-600 dark:hover:text-red-400" wire:click="confirmDelete({{ $subject->id }})">
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-zinc-400">
                                No subjects found matching your criteria.
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

    <!-- Create/Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-lg shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-700 overflow-y-auto max-h-[90vh]">
            <flux:heading size="lg" class="mb-4">{{ $editingSubject ? 'Edit Subject' : 'Create Subject' }}</flux:heading>
            
            <form wire:submit="{{ $editingSubject ? 'updateSubject' : 'createSubject' }}" class="flex flex-col gap-4">
                <flux:input wire:model="code" label="Subject Code" type="text" placeholder="e.g. CS101" required />
                <flux:input wire:model="name" label="Subject Name" type="text" placeholder="e.g. Introduction to Programming" required />
                <flux:input wire:model="units" label="Units" type="number" min="1" required />
                
                <flux:input wire:model="description" label="Description" type="text" placeholder="Optional brief details..." />

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="$set('showModal', false)">Cancel</flux:button>
                    <flux:button variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
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
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Units</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">{{ $deletingSubject->units }}</span>
                </div>
                <div class="sm:col-span-2">
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
