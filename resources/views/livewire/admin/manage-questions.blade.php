<?php

use Livewire\Volt\Component;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Semester;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    // Current Active Tab
    public string $activeTab = 'upward_student'; // 'upward_student', 'upward_employee', 'downward', 'peer', 'self'

    // Question form state
    public string $questionText = '';
    public string $criterionId = '';
    public string $evaluationType = 'upward_student';
    public string $order = '1';
    public ?int $editingQuestionId = null;

    // Modals
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;
    public ?EvaluationQuestion $deletingQuestion = null;

    public function getActiveSemesterProperty()
    {
        return Semester::where('is_active', true)->first();
    }

    public function getCriteriaProperty()
    {
        return EvaluationCriterion::where('evaluation_type', $this->activeTab)
            ->orderBy('order')
            ->get();
    }

    public function getModalCriteriaProperty()
    {
        return EvaluationCriterion::where('evaluation_type', $this->evaluationType)
            ->orderBy('order')
            ->get();
    }

    public function getQuestionsByCriterionProperty()
    {
        $criterionIds = $this->criteria->pluck('id')->toArray();
        return EvaluationQuestion::whereIn('criterion_id', $criterionIds)
            ->orderBy('order')
            ->get()
            ->groupBy('criterion_id');
    }

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->questionText = '';
        $this->evaluationType = $this->activeTab;
        $this->criterionId = $this->modalCriteria->first()?->id ?? '';
        $this->order = '1';
        $this->editingQuestionId = null;
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->autoOrder();
        $this->showFormModal = true;
    }

    public function autoOrder()
    {
        if ($this->criterionId) {
            $max = EvaluationQuestion::where('criterion_id', $this->criterionId)
                ->max('order') ?? 0;
            $this->order = (string)($max + 1);
        }
    }

    public function updatedCriterionId()
    {
        $this->autoOrder();
    }

    public function updatedEvaluationType()
    {
        $this->criterionId = $this->modalCriteria->first()?->id ?? '';
        $this->autoOrder();
    }

    public function openEditModal($id)
    {
        $q = EvaluationQuestion::with('criterion')->findOrFail($id);
        $this->editingQuestionId = $q->id;
        $this->questionText = $q->question_text;
        $this->evaluationType = $q->criterion->evaluation_type;
        $this->criterionId = (string)$q->criterion_id;
        $this->order = (string)$q->order;

        $this->showFormModal = true;
    }

    public function saveQuestion()
    {
        $this->validate([
            'criterionId' => 'required|exists:evaluation_criteria,id',
            'evaluationType' => 'required|in:upward_student,upward_employee,downward,peer,self',
            'questionText' => 'required|string|max:500',
            'order' => 'required|integer|min:1',
        ]);

        if ($this->editingQuestionId) {
            $q = EvaluationQuestion::findOrFail($this->editingQuestionId);
            $q->update([
                'criterion_id' => $this->criterionId,
                'question_text' => $this->questionText,
                'order' => (int)$this->order,
            ]);
            $msg = "Question updated successfully.";
        } else {
            EvaluationQuestion::create([
                'criterion_id' => $this->criterionId,
                'question_text' => $this->questionText,
                'order' => (int)$this->order,
                'is_active' => true,
            ]);
            $msg = "Question created successfully.";
        }

        $this->showFormModal = false;
        $this->resetForm();
        session()->flash('status', $msg);
    }

    public function toggleStatus($id)
    {
        $q = EvaluationQuestion::findOrFail($id);
        $q->is_active = !$q->is_active;
        $q->save();

        session()->flash('status', "Question status updated.");
    }

    public function confirmDelete($id)
    {
        $this->deletingQuestion = EvaluationQuestion::with('criterion')->findOrFail($id);
        $this->showDeleteModal = true;
    }

    public function deleteQuestion()
    {
        if ($this->deletingQuestion) {
            $this->deletingQuestion->delete();
            $this->deletingQuestion = null;
            $this->showDeleteModal = false;
            session()->flash('status', "Question deleted successfully.");
        }
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header -->
    <div class="flex justify-between items-start md:items-center flex-col md:flex-row gap-4">
        <div>
            <flux:heading size="xl" level="1">Manage Evaluation Questions</flux:heading>
            <flux:subheading>Manage question rubrics for student evaluations, peer reviews, and self-evaluations.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Add Question
        </flux:button>
    </div>

    @if (session()->has('status'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-300 rounded-lg border border-emerald-200 dark:border-emerald-800 text-sm font-medium animate-pulse">
            {{ session('status') }}
        </div>
    @endif

    @php
        $sem = $this->activeSemester;
        $studentTarget = $sem ? (float)$sem->upward_student_max_points : 90;
        $employeeTarget = $sem ? (float)$sem->upward_employee_max_points : 50;
        $downwardTarget = $sem ? (float)$sem->downward_max_points : 50;
        $peerTarget = $sem ? (float)$sem->peer_max_points : 50;
        $selfTarget = $sem ? (float)$sem->self_max_points : 10;
    @endphp

    <!-- Tabs Selection -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-6 overflow-x-auto">
        <button 
            wire:click="selectTab('upward_student')" 
            class="pb-3 text-sm font-semibold transition-all border-b-2 px-1 whitespace-nowrap {{ $activeTab === 'upward_student' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
        >
            Student Upward ({{ $studentTarget }} pts Target)
        </button>
        <button 
            wire:click="selectTab('upward_employee')" 
            class="pb-3 text-sm font-semibold transition-all border-b-2 px-1 whitespace-nowrap {{ $activeTab === 'upward_employee' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
        >
            Employee Upward ({{ $employeeTarget }} pts Target)
        </button>
        <button 
            wire:click="selectTab('downward')" 
            class="pb-3 text-sm font-semibold transition-all border-b-2 px-1 whitespace-nowrap {{ $activeTab === 'downward' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
        >
            Downward ({{ $downwardTarget }} pts Target)
        </button>
        <button 
            wire:click="selectTab('peer')" 
            class="pb-3 text-sm font-semibold transition-all border-b-2 px-1 whitespace-nowrap {{ $activeTab === 'peer' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
        >
            Peer ({{ $peerTarget }} pts Target)
        </button>
        <button 
            wire:click="selectTab('self')" 
            class="pb-3 text-sm font-semibold transition-all border-b-2 px-1 whitespace-nowrap {{ $activeTab === 'self' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}"
        >
            Self ({{ $selfTarget }} pts Target)
        </button>
    </div>

    <!-- Questions list grouped by Criteria -->
    <div class="space-y-6">
        @php
            $groupedQuestions = $this->questionsByCriterion;
        @endphp

        @forelse($this->criteria as $criterion)
            @php
                $questions = $groupedQuestions->get($criterion->id, collect());
            @endphp

            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <!-- Group Header -->
                <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 p-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <span class="bg-indigo-100 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 text-xs font-semibold px-2.5 py-0.5 rounded-full font-semibold">
                            Part {{ $criterion->order }}
                        </span>
                        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $criterion->name }}</h2>
                    </div>
                    <flux:badge variant="neutral" size="sm">Max Points: {{ $criterion->max_points }} pts</flux:badge>
                </div>

                <!-- Questions List -->
                <div class="divide-y divide-zinc-150 dark:divide-zinc-800">
                    @forelse($questions as $question)
                        <div class="flex items-center justify-between p-4 gap-4 hover:bg-zinc-50/30 dark:hover:bg-zinc-800/5 transition duration-150">
                            <div class="flex items-start gap-4 flex-1">
                                <span class="text-xs font-bold text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded">
                                    Q#{{ $question->order }}
                                </span>
                                <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed font-medium">
                                    {{ $question->question_text }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <!-- Active Toggle -->
                                <button 
                                    wire:click="toggleStatus({{ $question->id }})"
                                    class="focus:outline-none cursor-pointer"
                                >
                                    <flux:badge variant="{{ $question->is_active ? 'success' : 'neutral' }}" size="sm">
                                        {{ $question->is_active ? 'Active' : 'Inactive' }}
                                    </flux:badge>
                                </button>

                                <div class="flex items-center gap-1">
                                    <flux:button 
                                        size="sm" 
                                        variant="ghost" 
                                        icon="pencil-square" 
                                        wire:click="openEditModal({{ $question->id }})" 
                                        tooltip="Edit Question"
                                    />
                                    <flux:button 
                                        size="sm" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="confirmDelete({{ $question->id }})" 
                                        tooltip="Delete Question"
                                        class="text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-zinc-500 text-xs italic">
                            No questions configured for this part.
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-zinc-400 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl">
                <flux:icon icon="exclamation-circle" class="size-8 mx-auto mb-2 text-zinc-300" />
                <p class="text-sm font-semibold">No parts configured for this evaluation type yet.</p>
                <p class="text-xs mt-1">Please create criteria parts in the Settings module first.</p>
            </div>
        @endforelse
    </div>

    <!-- Create/Edit Form Modal -->
    @if($showFormModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-xl shadow-xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">
                {{ $editingQuestionId ? 'Edit Evaluation Question' : 'Create Evaluation Question' }}
            </flux:heading>
            
            <form wire:submit="saveQuestion" class="space-y-4">
                <flux:select wire:model.live="evaluationType" label="Evaluation Target Type" required>
                    <flux:select.option value="upward_student">Student Upward</flux:select.option>
                    <flux:select.option value="upward_employee">Employee Upward</flux:select.option>
                    <flux:select.option value="downward">Downward</flux:select.option>
                    <flux:select.option value="peer">Peer</flux:select.option>
                    <flux:select.option value="self">Self</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="criterionId" label="Part Category" required>
                    @forelse($this->modalCriteria as $criterion)
                        <flux:select.option value="{{ $criterion->id }}">Part #{{ $criterion->order }}: {{ $criterion->name }} (Max: {{ $criterion->max_points }} pts)</flux:select.option>
                    @empty
                        <flux:select.option value="">No parts defined for this type</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:input 
                    type="number" 
                    wire:model="order" 
                    label="Display Order" 
                    min="1" 
                    required 
                />

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5 font-semibold">Question Prompt / Text</label>
                    <textarea 
                        wire:model="questionText" 
                        rows="3" 
                        class="w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-800 p-2.5 bg-transparent text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-medium"
                        placeholder="e.g. The instructor displays a thorough understanding of the subject matter."
                        required
                    ></textarea>
                    @error('questionText')
                        <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showFormModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Save</flux:button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingQuestion)
    <x-confirmation-modal 
        title="Delete Question" 
        on-confirm="deleteQuestion" 
        on-cancel="$set('showDeleteModal', false)" 
    >
        Are you sure you want to delete this evaluation question? This action cannot be undone and will remove any history associated with this specific question.

        <x-slot:details>
            <div class="flex flex-col gap-3 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Evaluation Target Type</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">
                        {{ match($deletingQuestion->criterion->evaluation_type) {
                            'upward_student' => 'Student Upward',
                            'upward_employee' => 'Employee Upward',
                            'downward' => 'Downward',
                            'peer' => 'Peer',
                            'self' => 'Self',
                            default => ucfirst($deletingQuestion->criterion->evaluation_type)
                        } }} Evaluation
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Part Category (Order Q#{{ $deletingQuestion->order }})</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-150">Part {{ $deletingQuestion->criterion->order }}: {{ $deletingQuestion->criterion->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Question Prompt</span>
                    <p class="font-bold text-zinc-900 dark:text-zinc-150 leading-relaxed mt-1">{{ $deletingQuestion->question_text }}</p>
                </div>
            </div>
        </x-slot:details>
    </x-confirmation-modal>
    @endif
</div>
