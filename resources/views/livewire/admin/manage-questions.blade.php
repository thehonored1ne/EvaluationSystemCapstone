<?php

use App\Models\EvaluationCriterion;
use App\Models\EvaluationQuestion;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public function placeholder()
    {
        return view('livewire.placeholders.manage-questions-skeleton');
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

    public bool $deletingQuestionHasAnswers = false;

    public function getCategoryCountsProperty(): array
    {
        $typeMap = [
            'student' => ['student', 'upward_student'],
            'dean' => ['dean'],
            'program_head' => ['program_head', 'ph_dh'],
            'department_head' => ['department_head', 'downward'],
            'peer' => ['peer'],
            'superior' => ['superior', 'upward_employee'],
            'self' => ['self'],
        ];

        $criteriaByType = EvaluationCriterion::select('id', 'evaluation_type')
            ->withCount([
                'questions as total_questions',
                'questions as active_questions' => function ($q) {
                    $q->where('is_active', true);
                },
            ])
            ->get();

        $counts = [];
        foreach ($typeMap as $tab => $types) {
            $tabCriteria = $criteriaByType->whereIn('evaluation_type', $types);
            $counts[$tab] = [
                'total' => (int) $tabCriteria->sum('total_questions'),
                'active' => (int) $tabCriteria->sum('active_questions'),
            ];
        }

        return $counts;
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
            ->withCount([
                'questions as total_questions',
                'questions as active_questions' => function ($q) {
                    $q->where('is_active', true);
                },
            ])
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
            $query->where('question_text', 'like', '%'.trim($this->search).'%');
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
        $this->criterionId = $this->modalCriteria->first()?->id ? (string) $this->modalCriteria->first()->id : '';
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
            $this->order = (string) ($max + 1);
        }
    }

    public function updatedCriterionId()
    {
        $this->autoOrder();
    }

    public function updatedEvaluationType()
    {
        $this->criterionId = $this->modalCriteria->first()?->id ? (string) $this->modalCriteria->first()->id : '';
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

        $this->criterionId = (string) $q->criterion_id;
        $this->order = (string) $q->order;

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
                'order' => (int) $this->order,
            ]);
            $msg = 'Evaluation question updated successfully.';
        } else {
            EvaluationQuestion::create([
                'criterion_id' => $this->criterionId,
                'question_text' => $this->questionText,
                'order' => (int) $this->order,
                'is_active' => true,
            ]);
            $msg = 'Evaluation question created successfully.';
        }

        $this->showFormModal = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: $msg);
    }

    public function toggleStatus($id)
    {
        $q = EvaluationQuestion::findOrFail($id);
        $q->is_active = ! $q->is_active;
        $q->save();

        $statusStr = $q->is_active ? 'activated' : 'deactivated';
        Flux::toast(variant: 'info', text: "Question has been {$statusStr}.");
    }

    public function moveUp($id)
    {
        $question = EvaluationQuestion::findOrFail($id);
        $previous = EvaluationQuestion::where('criterion_id', $question->criterion_id)
            ->where('order', '<', $question->order)
            ->orderBy('order', 'desc')
            ->first();

        if ($previous) {
            DB::transaction(function () use ($question, $previous) {
                $tempOrder = $question->order;
                $question->order = $previous->order;
                $previous->order = $tempOrder;
                $question->save();
                $previous->save();
            });
            Flux::toast(variant: 'success', text: 'Question moved up.');
        }
    }

    public function moveDown($id)
    {
        $question = EvaluationQuestion::findOrFail($id);
        $next = EvaluationQuestion::where('criterion_id', $question->criterion_id)
            ->where('order', '>', $question->order)
            ->orderBy('order', 'asc')
            ->first();

        if ($next) {
            DB::transaction(function () use ($question, $next) {
                $tempOrder = $question->order;
                $question->order = $next->order;
                $next->order = $tempOrder;
                $question->save();
                $next->save();
            });
            Flux::toast(variant: 'success', text: 'Question moved down.');
        }
    }

    public function confirmDelete($id)
    {
        $this->deletingQuestion = EvaluationQuestion::with('criterion')->findOrFail($id);
        $this->deletingQuestionHasAnswers = $this->deletingQuestion->answers()->exists();
        $this->showDeleteModal = true;
    }

    public function deleteQuestion()
    {
        if ($this->deletingQuestion) {
            if ($this->deletingQuestion->answers()->exists()) {
                Flux::toast(
                    variant: 'danger',
                    heading: 'Action Blocked',
                    text: 'Cannot delete question: Evaluation responses already exist. Deactivate it instead to preserve evaluation records.'
                );
                $this->showDeleteModal = false;
                $this->deletingQuestion = null;

                return;
            }

            $criterionId = $this->deletingQuestion->criterion_id;
            $deletedOrder = $this->deletingQuestion->order;
            $this->deletingQuestion->delete();

            // Re-normalize subsequent question orders in this criterion
            EvaluationQuestion::where('criterion_id', $criterionId)
                ->where('order', '>', $deletedOrder)
                ->decrement('order');

            $this->deletingQuestion = null;
            $this->showDeleteModal = false;
            Flux::toast(variant: 'success', text: 'Evaluation question deleted successfully.');
        }
    }

    public function deactivateFromModal()
    {
        if ($this->deletingQuestion) {
            $this->deletingQuestion->is_active = false;
            $this->deletingQuestion->save();
            $this->deletingQuestion = null;
            $this->showDeleteModal = false;
            Flux::toast(variant: 'info', text: 'Question has been deactivated.');
        }
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header -->
    <div class="flex justify-between items-start md:items-center flex-col md:flex-row gap-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Questions Setup</flux:heading>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Configure, organize, and reorder evaluation questions across all institutional evaluation roles.</p>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            Add Question
        </flux:button>
    </div>

    <!-- Tabs Selection with Live Question Counts -->
    @php
        $counts = $this->categoryCounts;
        $tabs = [
            'student' => 'Student',
            'dean' => 'Dean',
            'program_head' => 'Program Head',
            'department_head' => 'Department Head',
            'peer' => 'Peer',
            'superior' => 'Supervisor',
            'self' => 'Self',
        ];
    @endphp
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-1.5 md:gap-3 overflow-x-auto pb-0">
        @foreach($tabs as $key => $label)
            <button 
                wire:click="selectTab('{{ $key }}')" 
                class="pb-3 text-xs md:text-sm font-semibold transition-all border-b-2 px-2.5 whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === $key ? 'border-[#9b0000] text-[#9b0000] dark:border-[#e07a7a] dark:text-[#e07a7a] font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
            >
                <span>{{ $label }}</span>
                <span class="text-[11px] px-1.5 py-0.5 rounded-full font-mono font-medium {{ $activeTab === $key ? 'bg-[#9b0000]/10 text-[#9b0000] dark:bg-red-950/60 dark:text-[#e07a7a]' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                    {{ $counts[$key]['total'] ?? 0 }}
                </span>
            </button>
        @endforeach
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
                clearable
            />
        </div>
    </div>

    <!-- Questions list grouped by Criteria -->
    @php
        $groupedQuestions = $this->questionsByCriterion;
        $allQuestionsInTab = $groupedQuestions->flatten();
        $isSearching = trim($search) !== '';
    @endphp

    @if($isSearching && $allQuestionsInTab->isEmpty())
        <!-- Global Empty Search State -->
        <div class="text-center py-12 px-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl">
            <flux:icon icon="magnifying-glass" class="size-10 mx-auto mb-2 text-zinc-400 dark:text-zinc-600" />
            <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">No questions found matching “{{ $search }}”</p>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Try adjusting your search query or switch to a different category tab.</p>
            <div class="mt-4">
                <flux:button size="sm" variant="ghost" wire:click="$set('search', '')">Clear Search</flux:button>
            </div>
        </div>
    @else
        <div class="space-y-6">
            @forelse($this->criteria as $criterion)
                @php
                    $questions = $groupedQuestions->get($criterion->id, collect());
                @endphp

                <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                    <!-- Group Header -->
                    <div class="flex flex-wrap items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 px-4 py-3.5 border-b border-zinc-200 dark:border-zinc-800 gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="bg-zinc-200/80 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 text-xs font-bold px-2 py-0.5 rounded-md font-mono">
                                Part {{ $criterion->order }}
                            </span>
                            <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">{{ $criterion->name }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge variant="neutral" size="sm">
                                {{ $criterion->total_questions ?? $questions->count() }} {{ \Illuminate\Support\Str::plural('Question', $criterion->total_questions ?? $questions->count()) }}
                            </flux:badge>
                            <flux:badge variant="neutral" size="sm">Max Points: {{ $criterion->max_points }} pts</flux:badge>
                        </div>
                    </div>

                    <!-- Questions List -->
                    <div class="divide-y divide-zinc-150 dark:divide-zinc-800">
                        @forelse($questions as $question)
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 gap-3 sm:gap-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 transition duration-150">
                                <div class="flex items-start gap-3 flex-1 min-w-0">
                                    <span class="text-xs font-bold text-zinc-500 bg-zinc-100 dark:bg-zinc-800 px-2 py-1 rounded font-mono shrink-0">
                                        Q#{{ $question->order }}
                                    </span>
                                    <p class="text-sm text-zinc-800 dark:text-zinc-200 leading-relaxed font-medium">
                                        {{ $question->question_text }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                                    <!-- Active Status Badge -->
                                    <button 
                                        wire:click="toggleStatus({{ $question->id }})"
                                        class="focus:outline-none cursor-pointer"
                                        title="Click to toggle active status"
                                    >
                                        <flux:badge variant="{{ $question->is_active ? 'success' : 'neutral' }}" size="sm">
                                            {{ $question->is_active ? 'Active' : 'Inactive' }}
                                        </flux:badge>
                                    </button>

                                    <!-- 3-Dot Actions Dropdown -->
                                    <flux:dropdown align="end">
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost" 
                                            icon="ellipsis-vertical" 
                                            aria-label="Actions for Q#{{ $question->order }}"
                                        />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="openEditModal({{ $question->id }})">
                                                Edit Question
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="chevron-up" wire:click="moveUp({{ $question->id }})">
                                                Move Up
                                            </flux:menu.item>
                                            <flux:menu.item icon="chevron-down" wire:click="moveDown({{ $question->id }})">
                                                Move Down
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="{{ $question->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleStatus({{ $question->id }})">
                                                {{ $question->is_active ? 'Deactivate' : 'Activate' }}
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $question->id }})">
                                                Delete Question
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center text-zinc-500 text-xs italic">
                                @if($isSearching)
                                    No questions matched your search query in this part.
                                @else
                                    No questions configured for this part yet.
                                @endif
                            </div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-zinc-400 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl">
                    <flux:icon icon="clipboard-document-list" class="size-10 mx-auto mb-2 text-zinc-300 dark:text-zinc-700" />
                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">No parts configured for this evaluation category yet.</p>
                    <p class="text-xs text-zinc-400 mt-1">Please create criteria parts in the Evaluation Settings module first.</p>
                </div>
            @endforelse
        </div>
    @endif

    <!-- Create/Edit Form Modal -->
    <flux:modal wire:model="showFormModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">
                    {{ $editingQuestionId ? 'Edit Evaluation Question' : 'Create Evaluation Question' }}
                </flux:heading>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Configure question prompt, target category, and display sequence.
                </p>
            </div>

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

                <flux:textarea 
                    wire:model="questionText" 
                    label="Question Prompt / Text" 
                    rows="3" 
                    placeholder="e.g. The instructor displays a thorough understanding of the subject matter."
                    required
                />

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button size="sm" variant="ghost" wire:click="$set('showFormModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveQuestion">{{ $editingQuestionId ? 'Save Changes' : 'Create Question' }}</span>
                        <span wire:loading wire:target="saveQuestion" class="inline-flex items-center gap-1.5">
                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                            <span>Saving…</span>
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal with Safety Guard -->
    @if($showDeleteModal && $deletingQuestion)
    <x-confirmation-modal 
        title="{{ $deletingQuestionHasAnswers ? 'Deactivate Question' : 'Delete Question' }}" 
        on-confirm="{{ $deletingQuestionHasAnswers ? 'deactivateFromModal' : 'deleteQuestion' }}" 
        on-cancel="$set('showDeleteModal', false)"
        :confirm-text="$deletingQuestionHasAnswers ? 'Deactivate Question' : 'Delete'"
        :variant="$deletingQuestionHasAnswers ? 'warning' : 'danger'"
    >
        @if($deletingQuestionHasAnswers)
            This question already has submitted evaluation responses from evaluators. Deleting it would permanently corrupt evaluation records. You can safely deactivate this question instead so it will not appear in future evaluation cycles.
        @else
            Are you sure you want to delete this evaluation question? This action cannot be undone.
        @endif

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

        @if($deletingQuestionHasAnswers)
            <x-slot:warning>
                Submitted evaluation data detected. Deletion is blocked to protect historical evaluation records.
            </x-slot:warning>
        @endif
    </x-confirmation-modal>
    @endif
</div>
