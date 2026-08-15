<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Layout;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use App\Models\Semester;

new #[Layout('components.layouts.app')] #[Lazy] class extends Component {
    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Current Active Tab: 'student', 'dean', 'program_head', 'department_head', 'peer', 'superior', 'self'
    public string $activeTab = 'student';

    // Search query
    public string $search = '';

    // Question form state
    public string $questionText = '';
    public string $criterionId = '';
    public string $evaluationType = 'student';
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
        $types = match ($this->activeTab) {
            'student' => ['student', 'upward_student'],
            'dean' => ['dean'],
            'program_head' => ['program_head', 'ph_dh'],
            'department_head' => ['department_head', 'downward'],
            'peer' => ['peer'],
            'superior' => ['superior', 'upward_employee'],
            'self' => ['self'],
            default => [$this->activeTab],
        };

        return EvaluationCriterion::whereIn('evaluation_type', $types)
            ->orderBy('order')
            ->get();
    }

    public function getModalCriteriaProperty()
    {
        $types = match ($this->evaluationType) {
            'student', 'upward_student' => ['student', 'upward_student'],
            'dean' => ['dean'],
            'program_head', 'ph_dh' => ['program_head', 'ph_dh'],
            'department_head', 'downward' => ['department_head', 'downward'],
            'peer' => ['peer'],
            'superior', 'upward_employee' => ['superior', 'upward_employee'],
            'self' => ['self'],
            default => [$this->evaluationType],
        };

        return EvaluationCriterion::whereIn('evaluation_type', $types)
            ->orderBy('order')
            ->get();
    }

    public function getQuestionsByCriterionProperty()
    {
        $criterionIds = $this->criteria->pluck('id')->toArray();
        $query = EvaluationQuestion::whereIn('criterion_id', $criterionIds)->orderBy('order');

        if (trim($this->search) !== '') {
            $query->where('question_text', 'like', '%' . trim($this->search) . '%');
        }

        return $query->get()->groupBy('criterion_id');
    }

    public function selectTab($tab)
    {
        $this->activeTab = $tab;
        $this->search = '';
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->questionText = '';
        $this->evaluationType = $this->activeTab;
        $this->criterionId = $this->modalCriteria->first()?->id ? (string)$this->modalCriteria->first()->id : '';
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
            $max = EvaluationQuestion::where('criterion_id', $this->criterionId)->max('order') ?? 0;
            $this->order = (string)($max + 1);
        }
    }

    public function updatedCriterionId()
    {
        $this->autoOrder();
    }

    public function updatedEvaluationType()
    {
        $this->criterionId = $this->modalCriteria->first()?->id ? (string)$this->modalCriteria->first()->id : '';
        $this->autoOrder();
    }

    public function openEditModal($id)
    {
        $q = EvaluationQuestion::with('criterion')->findOrFail($id);
        $this->editingQuestionId = $q->id;
        $this->questionText = $q->question_text;
        
        $type = $q->criterion->evaluation_type;
        $this->evaluationType = match ($type) {
            'upward_student' => 'student',
            'ph_dh' => 'program_head',
            'downward' => 'department_head',
            'upward_employee' => 'superior',
            default => $type,
        };

        $this->criterionId = (string)$q->criterion_id;
        $this->order = (string)$q->order;

        $this->showFormModal = true;
    }

    public function saveQuestion()
    {
        $this->validate([
            'criterionId' => 'required|exists:evaluation_criteria,id',
            'evaluationType' => 'required|in:student,dean,program_head,department_head,peer,superior,self,upward_student,upward_employee,downward,ph_dh',
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
            $msg = "Evaluation question updated successfully.";
        } else {
            EvaluationQuestion::create([
                'criterion_id' => $this->criterionId,
                'question_text' => $this->questionText,
                'order' => (int)$this->order,
                'is_active' => true,
            ]);
            $msg = "Evaluation question created successfully.";
        }

        $this->showFormModal = false;
        $this->resetForm();
        \Flux::toast(variant: 'success', text: $msg);
    }

    public function toggleStatus($id)
    {
        $q = EvaluationQuestion::findOrFail($id);
        $q->is_active = !$q->is_active;
        $q->save();

        $statusStr = $q->is_active ? 'activated' : 'deactivated';
        \Flux::toast(variant: 'info', text: "Question has been {$statusStr}.");
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
            \Flux::toast(variant: 'success', text: "Evaluation question deleted successfully.");
        }
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header -->
    <div class="flex justify-between items-start md:items-center flex-col md:flex-row gap-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Questions Setup</flux:heading>
            <flux:subheading>Configure evaluation question rubrics across all academic and administrative evaluation types.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Add Question
        </flux:button>
    </div>

    @php
        $sem = $this->activeSemester;
        $overall = $sem ? (float)($sem->overall_max_points ?? 200) : 200.0;
        
        $studentPts = $sem ? (float)($sem->upward_student_max_points ?? 90) : 90.0;
        $deanPts = $sem ? (float)($sem->dean_max_points ?? 20) : 20.0;
        $phPts = $sem ? (float)($sem->program_head_max_points ?? $sem->downward_max_points ?? 50) : 50.0;
        $dhPts = $sem ? (float)($sem->department_head_max_points ?? $sem->downward_max_points ?? 50) : 50.0;
        $peerPts = $sem ? (float)($sem->peer_max_points ?? 50) : 50.0;
        $superiorPts = $sem ? (float)($sem->upward_employee_max_points ?? 30) : 30.0;
        $selfPts = $sem ? (float)($sem->self_max_points ?? 10) : 10.0;
    @endphp

    <!-- Tabs Selection with Standardized Terms & Badges -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-2 md:gap-4 overflow-x-auto pb-0">
        <button 
            wire:click="selectTab('student')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'student' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Student ({{ $studentPts }} pts)
        </button>
        <button 
            wire:click="selectTab('dean')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'dean' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Dean ({{ $deanPts }} pts)
        </button>
        <button 
            wire:click="selectTab('program_head')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'program_head' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Program Head ({{ $phPts }} pts)
        </button>
        <button 
            wire:click="selectTab('department_head')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'department_head' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Department Head ({{ $dhPts }} pts)
        </button>
        <button 
            wire:click="selectTab('peer')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'peer' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Peer ({{ $peerPts }} pts)
        </button>
        <button 
            wire:click="selectTab('superior')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'superior' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Supervisor ({{ $superiorPts }} pts)
        </button>
        <button 
            wire:click="selectTab('self')" 
            class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2 whitespace-nowrap {{ $activeTab === 'self' ? 'border-[#9b0000] text-[#9b0000] dark:border-[#f89696] dark:text-[#f89696] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
        >
            Self ({{ $selfPts }} pts)
        </button>
    </div>

    <!-- Subheader Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
            Category Context: 
            <span class="font-bold text-zinc-800 dark:text-zinc-200">
                {{ match($activeTab) {
                    'student' => 'Student evaluates Faculty Professor',
                    'dean' => 'Dean evaluates Program Head',
                    'program_head' => 'Program Head evaluates Department Faculty',
                    'department_head' => 'Department Head evaluates Administrative Staff',
                    'peer' => 'Faculty evaluates Faculty / Staff evaluates Staff',
                    'superior' => 'Faculty evaluates PH / Staff evaluates DH / PH/DH evaluates Dean',
                    'self' => 'Individual Employee Self Evaluation',
                    default => ucfirst($activeTab)
                } }}
            </span>
        </div>
        <div class="w-full sm:w-64">
            <flux:input 
                wire:model.live.debounce.250ms="search" 
                placeholder="Search questions..." 
                icon="magnifying-glass" 
                size="sm"
            />
        </div>
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

            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                <!-- Group Header -->
                <div class="flex items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 p-4 border-b border-zinc-200 dark:border-zinc-800">
                    <div class="flex items-center gap-3">
                        <span class="bg-[#9b0000]/10 text-[#9b0000] dark:bg-red-950/60 dark:text-[#f89696] text-xs font-bold px-2.5 py-0.5 rounded-full">
                            Part {{ $criterion->order }}
                        </span>
                        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $criterion->name }}</h2>
                    </div>
                    <flux:badge variant="neutral" size="sm">Max Points: {{ $criterion->max_points }} pts</flux:badge>
                </div>

                <!-- Questions List -->
                <div class="divide-y divide-zinc-150 dark:divide-zinc-800">
                    @forelse($questions as $question)
                        <div class="flex items-center justify-between p-4 gap-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition duration-150">
                            <div class="flex items-start gap-4 flex-1">
                                <span class="text-xs font-bold text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded font-mono">
                                    Q#{{ $question->order }}
                                </span>
                                <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed font-medium">
                                    {{ $question->question_text }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <!-- Active Toggle -->
                                <button 
                                    wire:click="toggleStatus({{ $question->id }})"
                                    class="focus:outline-none cursor-pointer"
                                    title="Click to toggle active status"
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
                            @if(trim($search) !== '')
                                No questions matched your search query in this part.
                            @else
                                No questions configured for this part yet.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-zinc-400 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                <flux:icon icon="clipboard-document-list" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-700" />
                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">No parts configured for this evaluation category yet.</p>
                <p class="text-xs text-zinc-400 mt-1">Please create criteria parts in the Evaluation Settings module first.</p>
            </div>
        @endforelse
    </div>

    <!-- Create/Edit Form Modal -->
    @if($showFormModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="bg-white dark:bg-zinc-900 p-6 rounded-2xl shadow-2xl w-full max-w-lg border border-zinc-200 dark:border-zinc-800">
            <flux:heading size="lg" class="mb-4">
                {{ $editingQuestionId ? 'Edit Evaluation Question' : 'Create Evaluation Question' }}
            </flux:heading>
            
            <form wire:submit="saveQuestion" class="space-y-4">
                <flux:select wire:model.live="evaluationType" label="Evaluation Target Category" required>
                    <flux:select.option value="student">Student Evaluation</flux:select.option>
                    <flux:select.option value="dean">Dean Evaluation</flux:select.option>
                    <flux:select.option value="program_head">Program Head Evaluation</flux:select.option>
                    <flux:select.option value="department_head">Department Head Evaluation</flux:select.option>
                    <flux:select.option value="peer">Peer Evaluation</flux:select.option>
                    <flux:select.option value="superior">Supervisor Evaluation</flux:select.option>
                    <flux:select.option value="self">Self Evaluation</flux:select.option>
                </flux:select>

                <flux:select wire:model.live="criterionId" label="Part Category" required>
                    @forelse($this->modalCriteria as $criterion)
                        <flux:select.option value="{{ $criterion->id }}">Part #{{ $criterion->order }}: {{ $criterion->name }} (Max: {{ $criterion->max_points }} pts)</flux:select.option>
                    @empty
                        <flux:select.option value="">No parts defined for this category</flux:select.option>
                    @endforelse
                </flux:select>

                <flux:input 
                    type="number" 
                    wire:model="order" 
                    label="Display Order (Q#)" 
                    min="1" 
                    required 
                />

                <div>
                    <label class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1.5 font-semibold">Question Prompt / Text</label>
                    <textarea 
                        wire:model="questionText" 
                        rows="3" 
                        class="w-full text-sm rounded-lg border border-zinc-200 dark:border-zinc-800 p-2.5 bg-transparent text-zinc-900 dark:text-zinc-100 focus:outline-none focus:ring-1 focus:ring-[#9b0000] dark:focus:ring-[#f89696] font-medium"
                        placeholder="e.g. The instructor displays a thorough understanding of the subject matter."
                        required
                    ></textarea>
                    @error('questionText')
                        <span class="text-xs text-rose-500 font-semibold">{{ $message }}</span>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <flux:button size="sm" wire:click="$set('showFormModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit">Save Question</flux:button>
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
        Are you sure you want to delete this evaluation question? This action cannot be undone.

        <x-slot:details>
            <div class="flex flex-col gap-3 text-sm">
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Evaluation Category</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">
                        {{ match($deletingQuestion->criterion->evaluation_type) {
                            'student', 'upward_student' => 'Student Evaluation',
                            'dean' => 'Dean Evaluation',
                            'program_head', 'ph_dh' => 'Program Head Evaluation',
                            'department_head', 'downward' => 'Department Head Evaluation',
                            'peer' => 'Peer Evaluation',
                            'superior', 'upward_employee' => 'Supervisor Evaluation',
                            'self' => 'Self Evaluation',
                            default => ucfirst($deletingQuestion->criterion->evaluation_type)
                        } }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Part Category (Order Q#{{ $deletingQuestion->order }})</span>
                    <span class="font-bold text-zinc-900 dark:text-zinc-100">Part {{ $deletingQuestion->criterion->order }}: {{ $deletingQuestion->criterion->name }}</span>
                </div>
                <div>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 font-semibold uppercase block tracking-wider">Question Prompt</span>
                    <p class="font-bold text-zinc-900 dark:text-zinc-100 leading-relaxed mt-1">"{{ $deletingQuestion->question_text }}"</p>
                </div>
            </div>
        </x-slot:details>
    </x-confirmation-modal>
    @endif
</div>
