<?php

use App\Jobs\ProcessEvaluationSubmission;
use App\Models\AcademicClass;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationExemption;
use App\Models\Semester;
use App\Models\User;
use App\Services\ProfanityFilterService;
use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Component;

new class extends Component
{
    public User $evaluatee;

    public ?AcademicClass $class = null;

    public string $evaluationType = 'upward_student'; // 'upward_student', 'upward_employee', 'downward', 'peer', 'self'

    public array $ratings = []; // [question_id => rating]

    public string $comments = '';

    public int $retryAfter = 0;

    public function updatedComments($value)
    {
        $filterService = app(ProfanityFilterService::class);

        if ($filterService->hasProfanity($value)) {
            $this->comments = $filterService->clean($value);

            Flux::toast(
                heading: 'Respectful Feedback Required',
                text: "Saying a bad word is not a good thing. Let's keep our comments constructive and respectful!",
                variant: 'danger'
            );
        } else {
            $this->comments = $value;
        }
    }

    public function filterProfanity(?string $text): string
    {
        return app(ProfanityFilterService::class)->clean($text);
    }

    public function mount()
    {
        $rateLimitKey = 'submit-evaluation:'.auth()->id().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 50)) {
            $this->retryAfter = RateLimiter::availableIn($rateLimitKey);
        }
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->ratings = [];
        $this->comments = '';

        $types = match ($this->evaluationType) {
            'student', 'upward_student' => ['upward_student', 'student'],
            'dean' => ['dean'],
            'program_head', 'ph_dh' => ['program_head'],
            'department_head' => ['department_head'],
            'peer' => ['peer'],
            'superior', 'upward_employee' => ['upward_employee', 'superior'],
            'self' => ['self'],
            default => [$this->evaluationType],
        };

        $criteria = EvaluationCriterion::getForTypes($types);

        foreach ($criteria as $criterion) {
            foreach ($criterion->questions as $question) {
                $this->ratings[$question->id] = '';
            }
        }
    }

    public function getCriteriaProperty()
    {
        $types = match ($this->evaluationType) {
            'student', 'upward_student' => ['upward_student', 'student'],
            'dean' => ['dean'],
            'program_head', 'ph_dh' => ['program_head'],
            'department_head' => ['department_head'],
            'peer' => ['peer'],
            'superior', 'upward_employee' => ['upward_employee', 'superior'],
            'self' => ['self'],
            default => [$this->evaluationType],
        };

        return EvaluationCriterion::getForTypes($types);
    }

    public function getQuestionsProperty()
    {
        $questions = [];
        foreach ($this->criteria as $criterion) {
            foreach ($criterion->questions as $question) {
                $questions[] = [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'criterion_id' => $criterion->id,
                    'criterion_name' => $criterion->name,
                    'max_points' => (float) $criterion->max_points,
                ];
            }
        }

        return $questions;
    }

    public function submit()
    {
        $activeSem = Semester::where('is_active', true)->first();

        if (! $activeSem) {
            Flux::toast(
                heading: 'No Active Semester',
                text: 'There is no active semester. Submissions are disabled.',
                variant: 'danger'
            );
            session()->flash('error', 'There is no active semester. Submissions are disabled.');

            return;
        }

        if (! $activeSem->isEvaluationWindowActive()) {
            Flux::toast(
                heading: 'Evaluations Closed',
                text: 'Evaluations are currently closed for this semester.',
                variant: 'danger'
            );
            session()->flash('error', 'Evaluations are currently closed.');

            return;
        }

        $rateLimitKey = 'submit-evaluation:'.auth()->id().':'.request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 50)) {
            $this->retryAfter = RateLimiter::availableIn($rateLimitKey);
            Flux::toast(
                heading: 'Rate Limited',
                text: 'Too many submission attempts. Please wait before submitting again.',
                variant: 'warning'
            );

            return;
        }

        $rules = [];
        $messages = [];
        foreach ($this->ratings as $questionId => $rating) {
            $rules["ratings.{$questionId}"] = 'required|integer|between:1,5';
            $messages["ratings.{$questionId}.required"] = 'Please provide a rating for this question.';
        }

        $rules['comments'] = 'required|string|min:3';
        $messages['comments.required'] = 'Please provide comments / feedback before submitting your evaluation.';
        $messages['comments.min'] = 'Your comment must be at least 3 characters long.';

        $this->validate($rules, $messages);

        RateLimiter::hit($rateLimitKey, 300);

        $sanitizedRatings = collect($this->ratings)->map(fn ($val) => (int) $val)->toArray();
        $cleanComments = $this->filterProfanity($this->comments);

        ProcessEvaluationSubmission::dispatch(
            auth()->id(),
            $this->evaluatee->id,
            $activeSem->id,
            $this->class?->id,
            $this->evaluationType,
            $sanitizedRatings,
            $cleanComments ?: null
        );

        $this->dispatch('evaluation-submitted');

        Flux::toast(
            heading: 'Evaluation Submitted',
            text: 'Your evaluation has been submitted successfully.',
            variant: 'success'
        );

        session()->flash('success', 'Evaluation submitted successfully.');
        $this->resetForm();
    }

    public bool $showExemptionModal = false;

    public string $exemptionReason = '';

    public string $exemptionNotes = '';

    public function openExemptionModal(): void
    {
        $this->exemptionReason = '';
        $this->exemptionNotes = '';
        $this->showExemptionModal = true;
    }

    public function submitExemption(): void
    {
        if ($this->evaluationType !== 'peer') {
            return;
        }

        $activeSem = Semester::where('is_active', true)->first();
        if (! $activeSem || ! $activeSem->isEvaluationWindowActive()) {
            Flux::toast(
                heading: 'Evaluations Closed',
                text: 'Evaluations are currently closed for this semester.',
                variant: 'danger'
            );
            session()->flash('error', 'Evaluations are currently closed.');

            return;
        }

        $this->validate([
            'exemptionReason' => 'required|string|in:schedule_conflict,different_specialization,new_faculty,other',
            'exemptionNotes' => 'nullable|string|max:500'.($this->exemptionReason === 'other' ? '|required|min:5' : ''),
        ], [
            'exemptionReason.required' => 'Please select an institutional reason.',
            'exemptionNotes.required' => 'Please provide specific remarks for this reason.',
        ]);

        EvaluationExemption::updateOrCreate(
            [
                'evaluator_id' => auth()->id(),
                'evaluatee_id' => $this->evaluatee->id,
                'semester_id' => $activeSem->id,
                'evaluation_type' => 'peer',
            ],
            [
                'reason' => $this->exemptionReason,
                'notes' => $this->exemptionNotes ?: null,
            ]
        );

        activity('evaluation_exemption')
            ->performedOn($this->evaluatee)
            ->causedBy(auth()->user())
            ->withProperties([
                'semester_id' => $activeSem->id,
                'reason' => $this->exemptionReason,
                'notes' => $this->exemptionNotes,
            ])
            ->log('Peer evaluation exemption recorded: Unable to observe');

        $this->showExemptionModal = false;
        $this->dispatch('evaluation-submitted');

        Flux::toast(
            heading: 'Exemption Recorded',
            text: 'Peer evaluation exemption recorded: "No Basis to Observe".',
            variant: 'success'
        );

        session()->flash('success', 'Evaluation exemption recorded: "No Basis to Observe" logged in audit trail.');
    }
}; ?>

@php
    $flatQuestions = $this->questions;
    $totalQuestionsCount = count($flatQuestions);
    $questionIds = array_column($flatQuestions, 'id');
@endphp

<div 
    x-data="{ 
        ratings: @entangle('ratings'),
        comments: @entangle('comments'),
        currentIndex: 0,
        totalQuestions: {{ $totalQuestionsCount }},
        questionIds: {{ json_encode($questionIds) }},
        isReviewStep: false,
        autoAdvanceTimeout: null,
        storageKey: 'draft_eval_{{ auth()->id() }}_{{ $evaluationType }}_{{ $evaluatee->id }}_{{ $class?->id ?? 'noclass' }}',
        isOffline: !navigator.onLine,
        scaleLabels: {
            1: 'Poor',
            2: 'Fair',
            3: 'Satisfactory',
            4: 'Very Satisfactory',
            5: 'Outstanding'
        },

        init() {
            window.addEventListener('online', () => this.isOffline = false);
            window.addEventListener('offline', () => this.isOffline = true);
            document.addEventListener('livewire:navigated', () => this.isOffline = !navigator.onLine);

            const saved = localStorage.getItem(this.storageKey);
            if (saved) {
                try {
                    const data = JSON.parse(saved);
                    if (data.ratings && typeof data.ratings === 'object') {
                        Object.keys(data.ratings).forEach(qId => {
                            if (this.ratings.hasOwnProperty(qId)) {
                                this.ratings[qId] = data.ratings[qId];
                            }
                        });
                    }
                    if (data.comments !== undefined && data.comments !== null) {
                        this.comments = data.comments;
                    }
                    if (typeof data.currentIndex === 'number' && data.currentIndex < this.totalQuestions) {
                        this.currentIndex = data.currentIndex;
                    }
                    if (typeof data.isReviewStep === 'boolean') {
                        this.isReviewStep = data.isReviewStep;
                    }
                } catch(e) {}
            }

            this.$watch('ratings', () => this.saveDraft());
            this.$watch('comments', () => this.saveDraft());
            this.$watch('currentIndex', () => this.saveDraft());
            this.$watch('isReviewStep', () => this.saveDraft());
        },

        saveDraft() {
            try {
                const draft = {
                    ratings: this.ratings,
                    comments: this.comments,
                    currentIndex: this.currentIndex,
                    isReviewStep: this.isReviewStep
                };
                localStorage.setItem(this.storageKey, JSON.stringify(draft));
            } catch(e) {}
        },

        clearDraft() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch(e) {}
        },
        
        selectRating(questionId, ratingVal) {
            this.ratings[questionId] = ratingVal;
            this.saveDraft();
            
            clearTimeout(this.autoAdvanceTimeout);
            this.autoAdvanceTimeout = setTimeout(() => {
                if (this.currentIndex < this.totalQuestions - 1) {
                    this.currentIndex++;
                    this.scrollPillIntoView(this.currentIndex);
                } else {
                    this.isReviewStep = true;
                }
            }, 300);
        },
        
        goToQuestion(index) {
            this.isReviewStep = false;
            this.currentIndex = index;
            this.saveDraft();
            this.scrollPillIntoView(index);
        },

        goToNextUnanswered() {
            const firstUnansweredIndex = this.questionIds.findIndex(qId => !this.ratings[qId]);
            if (firstUnansweredIndex !== -1) {
                this.goToQuestion(firstUnansweredIndex);
            }
        },

        scrollPillIntoView(idx) {
            this.$nextTick(() => {
                const pill = this.$refs['pill_' + idx];
                if (pill) {
                    pill.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        },

        nextQuestion() {
            if (this.currentIndex < this.totalQuestions - 1) {
                this.currentIndex++;
                this.scrollPillIntoView(this.currentIndex);
            } else {
                this.isReviewStep = true;
            }
            this.saveDraft();
        },

        prevQuestion() {
            if (this.isReviewStep) {
                this.isReviewStep = false;
            } else if (this.currentIndex > 0) {
                this.currentIndex--;
                this.scrollPillIntoView(this.currentIndex);
            }
            this.saveDraft();
        },

        handleKeydown(e) {
            // Ignore keystrokes inside inputs, textareas, selects, or editable elements
            const activeEl = document.activeElement;
            const tag = activeEl ? activeEl.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select' || activeEl?.isContentEditable) {
                return;
            }

            // Number Keys 1-5 (Top row and Numpad) for rapid rating
            const key = e.key;
            let ratingNum = null;
            if (key >= '1' && key <= '5') {
                ratingNum = parseInt(key, 10);
            } else if (e.code && e.code.startsWith('Numpad') && e.code.length === 7) {
                const numpadDigit = parseInt(e.code.replace('Numpad', ''), 10);
                if (numpadDigit >= 1 && numpadDigit <= 5) {
                    ratingNum = numpadDigit;
                }
            }

            if (!this.isReviewStep && ratingNum !== null) {
                e.preventDefault();
                const currentQId = this.questionIds[this.currentIndex];
                if (currentQId) {
                    this.selectRating(currentQId, ratingNum);
                }
                return;
            }

            // Navigation Keys
            if (key === 'ArrowRight' || (key === 'Enter' && !this.isReviewStep && e.target.tagName !== 'BUTTON')) {
                e.preventDefault();
                this.nextQuestion();
            } else if (key === 'ArrowLeft' || key === 'Backspace') {
                e.preventDefault();
                this.prevQuestion();
            }
        },

        get answeredCount() {
            return Object.values(this.ratings).filter(r => r !== '' && r !== null && r !== undefined).length;
        },

        get hasValidComment() {
            return typeof this.comments === 'string' && this.comments.trim().length >= 3;
        },

        get isReadyToSubmit() {
            return this.answeredCount === this.totalQuestions && this.hasValidComment && {{ $retryAfter }} === 0 && !this.isOffline;
        },

        get progressPercent() {
            if (this.totalQuestions === 0) return 0;
            return Math.round((this.answeredCount / this.totalQuestions) * 100);
        }
    }" 
    @keydown.window="handleKeydown($event)"
    @evaluation-submitted.window="clearDraft()"
    class="w-full max-w-6xl mx-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl sm:rounded-2xl shadow-lg overflow-hidden"
>
    <!-- Institutional Header: Context & Progress -->
    <div class="px-3 sm:px-6 py-2.5 sm:py-4 bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-6">
        <div class="min-w-0 flex-1">
            <!-- Top Sub-row: Evaluation Badge + Mobile Inline Progress -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                    <span class="text-[10px] sm:text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 font-sans">
                        @if($evaluationType === 'self')
                            Self Evaluation
                        @elseif($evaluationType === 'peer')
                            Peer Evaluation
                        @elseif($evaluationType === 'upward_student')
                            Student Evaluation
                        @elseif($evaluationType === 'upward_employee')
                            Superior Evaluation
                        @else
                            Evaluation
                        @endif
                    </span>
                    @if($class)
                        <span class="text-zinc-300 dark:text-zinc-600">•</span>
                        <span class="text-[10px] sm:text-xs font-mono font-bold text-zinc-500 dark:text-zinc-400">
                            {{ $class->subject->code }} • {{ $class->section }}
                        </span>
                    @endif
                </div>

                <!-- Mobile Progress Pill (Placed compactly on top-right) -->
                <div class="sm:hidden inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-2xs font-mono text-xs font-bold text-zinc-800 dark:text-zinc-200 shrink-0 tabular-nums">
                    <span x-text="`${answeredCount}/${totalQuestions}`"></span>
                </div>
            </div>

            <!-- Evaluatee Name & Details -->
            <h2 class="text-sm sm:text-base md:text-lg font-bold font-sans text-zinc-900 dark:text-zinc-50 leading-snug truncate mt-1">
                @if($evaluationType === 'self')
                    {{ auth()->user()->name }}
                @else
                    {{ $evaluatee->name }}
                @endif
            </h2>
            @if($class)
                <p class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400 truncate font-sans">
                    {{ $class->subject->name }}
                </p>
            @endif
        </div>

        <!-- Desktop Progress Pill + Peer Exemption Button -->
        <div class="hidden sm:flex items-center justify-end gap-2.5 shrink-0">
            @if($evaluationType === 'peer')
                <button 
                    type="button" 
                    wire:click="openExemptionModal"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 transition-all cursor-pointer shrink-0 shadow-2xs font-sans"
                    title="Exempt evaluation if no opportunity to observe peer"
                >
                    <flux:icon icon="hand-raised" class="size-3.5 text-amber-500" />
                    <span>No Basis to Observe</span>
                </button>
            @endif

            <!-- High-Contrast Progress Pill Badge with Tabular Figures -->
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 shadow-2xs font-mono text-xs">
                <span class="text-zinc-600 dark:text-zinc-300 font-semibold" x-text="`${answeredCount}/${totalQuestions} Answered`"></span>
                <span class="text-zinc-300 dark:text-zinc-600">•</span>
                <span x-text="`${progressPercent}%`" class="font-bold text-[#9b0000] dark:text-[#e07a7a] tabular-nums"></span>
            </div>
        </div>

        <!-- Mobile Peer Exemption Button (if applicable) -->
        @if($evaluationType === 'peer')
            <div class="sm:hidden">
                <button 
                    type="button" 
                    wire:click="openExemptionModal"
                    class="w-full inline-flex items-center justify-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-white dark:bg-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700 transition-all cursor-pointer shadow-2xs font-sans"
                >
                    <flux:icon icon="hand-raised" class="size-3 text-amber-500" />
                    <span>No Basis to Observe</span>
                </button>
            </div>
        @endif
    </div>

    <!-- Continuous Progress Bar Track -->
    <div class="w-full h-1.5 bg-zinc-200 dark:bg-zinc-800">
        <div 
            class="h-full bg-gradient-to-r from-[#9b0000] via-[#c02b2b] to-amber-500 dark:from-[#e07a7a] dark:to-amber-400 transition-all duration-300 ease-out shadow-xs" 
            :style="`width: ${progressPercent}%`"
        ></div>
    </div>

    <!-- In-Form Passive Offline Detection Notice -->
    <div 
        x-show="isOffline"
        x-cloak
        role="status"
        aria-live="polite"
        class="bg-amber-500 dark:bg-amber-600 text-amber-950 dark:text-amber-50 px-3 sm:px-5 py-2.5 text-xs font-semibold flex items-center justify-between gap-3 border-b border-amber-600/30 print:hidden"
    >
        <div class="flex items-center gap-2 min-w-0">
            <flux:icon icon="wifi" class="size-4 shrink-0 text-amber-950 dark:text-amber-50 opacity-90" />
            <span class="truncate sm:whitespace-normal">You are offline. Your ratings and feedback are saved locally on this device. Reconnect to submit.</span>
        </div>
        <span class="text-[11px] font-bold bg-amber-950/15 dark:bg-amber-950/30 px-2.5 py-0.5 rounded-full whitespace-nowrap shrink-0">Draft Saved Locally</span>
    </div>

    <!-- Alert Messages -->
    @if(session()->has('success'))
        <div class="mx-3 sm:mx-5 mt-3 sm:mt-5 p-3 sm:p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <div class="text-xs sm:text-sm font-semibold">{{ session('success') }}</div>
        </div>
    @endif

    @if($retryAfter > 0)
        <div x-data="{ seconds: @entangle('retryAfter') }" 
             x-init="const interval = setInterval(() => { if (seconds > 0) { seconds--; } else { clearInterval(interval); $wire.set('retryAfter', 0); } }, 1000)"
             class="mx-3 sm:mx-5 mt-3 sm:mt-5 p-3 sm:p-4 bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="clock" class="size-5 text-rose-600 dark:text-rose-400 animate-pulse shrink-0" />
            <div class="text-xs sm:text-sm font-semibold">
                Too many submission attempts. Please wait <span x-text="seconds" class="font-bold"></span> seconds before submitting again.
            </div>
        </div>
    @elseif(session()->has('error'))
        <div class="mx-3 sm:mx-5 mt-3 sm:mt-5 p-3 sm:p-4 bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="x-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0" />
            <div class="text-xs sm:text-sm font-semibold">{{ session('error') }}</div>
        </div>
    @endif

    <!-- Question Number Pills Grid Navigator -->
    <div class="px-3 sm:px-6 py-3 sm:py-3.5 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/60">
        <div class="flex items-center gap-2 overflow-x-auto scroll-smooth sm:flex-wrap sm:max-h-28 sm:overflow-y-auto py-1 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            @foreach($flatQuestions as $idx => $q)
                <button
                    type="button"
                    :x-ref="'pill_' + {{ $idx }}"
                    @click="goToQuestion({{ $idx }})"
                    class="size-8 sm:size-8.5 min-w-8 sm:min-w-8.5 rounded-lg text-xs font-mono font-bold transition-all duration-150 flex items-center justify-center cursor-pointer border shrink-0"
                    :class="{
                        'bg-[#9b0000] border-[#9b0000] text-white shadow-sm ring-2 ring-[#9b0000]/20 dark:bg-[#e07a7a] dark:border-[#e07a7a] dark:text-zinc-950 scale-105': !isReviewStep && currentIndex === {{ $idx }},
                        'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-500/20': (!isReviewStep && currentIndex !== {{ $idx }}) && ratings[{{ $q['id'] }}],
                        'bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-zinc-400 dark:hover:border-zinc-500': (!isReviewStep && currentIndex !== {{ $idx }}) && !ratings[{{ $q['id'] }}],
                        'opacity-60': isReviewStep && !ratings[{{ $q['id'] }}]
                    }"
                    title="Question {{ $idx + 1 }}"
                >
                    <template x-if="ratings[{{ $q['id'] }}] && currentIndex !== {{ $idx }}">
                        <flux:icon icon="check" class="size-3.5 stroke-[3]" />
                    </template>
                    <template x-if="!ratings[{{ $q['id'] }}] || currentIndex === {{ $idx }}">
                        <span>{{ $idx + 1 }}</span>
                    </template>
                </button>
            @endforeach

            <!-- Final Review Step Button Pill -->
            <button
                type="button"
                @click="isReviewStep = true"
                class="h-8 sm:h-8.5 px-3 rounded-lg text-xs font-sans font-bold transition-all duration-150 flex items-center gap-1.5 cursor-pointer border shrink-0 whitespace-nowrap"
                :class="{
                    'bg-[#9b0000] border-[#9b0000] text-white shadow-sm dark:bg-[#e07a7a] dark:border-[#e07a7a] dark:text-zinc-950': isReviewStep,
                    'bg-zinc-100 dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-400 dark:hover:border-zinc-600': !isReviewStep
                }"
            >
                <flux:icon icon="clipboard-document-check" class="size-3.5" />
                <span>Review & Submit</span>
            </button>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="px-3 py-5 sm:px-6 sm:py-7 md:p-8">

        <!-- Active Question Card Step (Wrapped in single root div for Alpine template compatibility) -->
        <template x-if="!isReviewStep && totalQuestions > 0">
            <div>
                @foreach($flatQuestions as $idx => $q)
                    <div 
                        x-show="currentIndex === {{ $idx }}" 
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-98"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="flex flex-col gap-4 sm:gap-6"
                    >
                        <!-- Hero Question Prompt -->
                        <div class="py-3 sm:py-8 md:py-10 min-h-[60px] sm:min-h-[100px] flex flex-col items-center justify-center text-center px-1 sm:px-4">
                            <h3 class="text-base sm:text-xl md:text-2xl lg:text-3xl font-bold sm:font-extrabold font-sans text-zinc-900 dark:text-zinc-50 leading-snug sm:leading-tight max-w-3xl tracking-tight">
                                {{ $q['question_text'] }}
                            </h3>
                        </div>

                        <!-- Integrated 5-Card Rating Grid -->
                        @php
                            $scaleOptions = [
                                1 => ['label' => 'Poor', 'sub' => null],
                                2 => ['label' => 'Fair', 'sub' => null],
                                3 => ['label' => 'Satisfactory', 'sub' => null],
                                4 => ['label' => 'Very', 'sub' => 'Satisfactory'],
                                5 => ['label' => 'Outstanding', 'sub' => null],
                            ];
                        @endphp
                        <!-- Mobile Vertical Rating Stack (< sm) -->
                        <div class="sm:hidden flex flex-col gap-1.5 w-full max-w-sm mx-auto my-2">
                            @for($ratingVal = 5; $ratingVal >= 1; $ratingVal--)
                                @php $opt = $scaleOptions[$ratingVal]; @endphp
                                <button
                                    type="button"
                                    @click="selectRating({{ $q['id'] }}, {{ $ratingVal }})"
                                    class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg min-[360px]:rounded-xl border transition-all duration-150 cursor-pointer select-none text-left min-h-[38px] min-[360px]:min-h-[40px] touch-manipulation"
                                    :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }}
                                        ? 'bg-[#9b0000] border-[#9b0000] text-white dark:bg-[#a82e2e] dark:border-[#e07a7a] shadow-2xs ring-1 ring-red-900/20'
                                        : 'bg-white dark:bg-zinc-800/90 border-zinc-200 dark:border-zinc-700/80 text-zinc-800 dark:text-zinc-200 hover:border-[#9b0000]/60 hover:bg-red-50/30 dark:hover:bg-red-950/20 shadow-2xs'"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span 
                                            class="size-6 min-[360px]:size-6.5 rounded-md flex items-center justify-center font-mono font-bold text-xs shrink-0 border transition-colors"
                                            :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }}
                                                ? 'bg-white/20 border-white/40 text-white'
                                                : 'bg-zinc-100 dark:bg-zinc-700/60 border-zinc-200 dark:border-zinc-600 text-zinc-700 dark:text-zinc-300'"
                                        >
                                            {{ $ratingVal }}
                                        </span>
                                        <span class="font-sans font-bold text-xs truncate">
                                            {{ $opt['label'] }}{{ $opt['sub'] ? ' '.$opt['sub'] : '' }}
                                        </span>
                                    </div>

                                    <!-- Selected Indicator Circle -->
                                    <div 
                                        class="size-4 min-[360px]:size-4.5 rounded-full flex items-center justify-center shrink-0 transition-all border"
                                        :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }} 
                                            ? 'bg-white border-white text-[#9b0000] dark:text-[#a82e2e]' 
                                            : 'border-zinc-300 dark:border-zinc-600 bg-transparent'"
                                    >
                                        <template x-if="ratings[{{ $q['id'] }}] == {{ $ratingVal }}">
                                            <flux:icon icon="check" class="size-2.5 stroke-[3]" />
                                        </template>
                                    </div>
                                </button>
                            @endfor
                        </div>

                        <!-- Desktop 5-Card Rating Grid (>= sm) -->
                        <div class="hidden sm:grid sm:grid-cols-5 gap-3 md:gap-4 w-full max-w-4xl mx-auto my-4 sm:my-6">
                            @for($ratingVal = 1; $ratingVal <= 5; $ratingVal++)
                                @php $opt = $scaleOptions[$ratingVal]; @endphp
                                <button
                                    type="button"
                                    @click="selectRating({{ $q['id'] }}, {{ $ratingVal }})"
                                    class="flex flex-col items-center justify-center p-3.5 md:p-4 rounded-2xl border transition-all duration-200 cursor-pointer select-none group min-h-[96px] md:min-h-[110px] text-center"
                                    :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }}
                                        ? 'bg-[#9b0000] border-[#9b0000] text-white dark:bg-[#a82e2e] dark:border-[#e07a7a] shadow-md -translate-y-0.5 ring-4 ring-red-900/20 dark:ring-red-500/20'
                                        : 'bg-white dark:bg-zinc-800/80 border-zinc-200 dark:border-zinc-700 text-zinc-800 dark:text-zinc-200 hover:border-[#9b0000]/60 dark:hover:border-[#e07a7a]/60 hover:bg-red-50/40 dark:hover:bg-red-950/20 hover:-translate-y-0.5 shadow-2xs'"
                                    title="Select {{ $ratingVal }} ({{ $opt['label'] }}{{ $opt['sub'] ? ' '.$opt['sub'] : '' }}) - Press {{ $ratingVal }} on keyboard"
                                >
                                    <!-- Rating Number -->
                                    <span class="font-mono font-black text-2xl md:text-3xl lg:text-4xl leading-none tabular-nums">
                                        {{ $ratingVal }}
                                    </span>

                                    <!-- Rubric Qualitative Label Directly on Card -->
                                    <div class="w-full flex flex-col items-center justify-center mt-1.5 sm:mt-2">
                                        @if($opt['sub'])
                                            <span 
                                                class="text-xs md:text-sm font-bold leading-tight font-sans text-center"
                                                :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }} ? 'text-white' : 'text-zinc-800 dark:text-zinc-200'"
                                            >
                                                {{ $opt['label'] }}
                                            </span>
                                            <span 
                                                class="text-xs md:text-sm font-bold leading-tight font-sans text-center mt-0.5"
                                                :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }} ? 'text-white' : 'text-zinc-800 dark:text-zinc-200'"
                                            >
                                                {{ $opt['sub'] }}
                                            </span>
                                        @else
                                            <span 
                                                class="text-xs md:text-sm font-bold leading-tight font-sans text-center"
                                                :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }} ? 'text-white' : 'text-zinc-800 dark:text-zinc-200'"
                                            >
                                                {{ $opt['label'] }}
                                            </span>
                                        @endif
                                    </div>
                                </button>
                            @endfor
                        </div>

                        <!-- Card Controls & Keyboard Navigation Legend -->
                        <div class="flex flex-col gap-3 border-t border-zinc-200 dark:border-zinc-800/80 pt-5 mt-2">
                            <div class="flex items-center justify-between gap-3">
                                <flux:button 
                                    variant="subtle" 
                                    type="button" 
                                    @click="prevQuestion()" 
                                    ::disabled="currentIndex === 0"
                                    icon="arrow-left"
                                    class="cursor-pointer font-sans min-h-[42px] sm:min-h-[44px] touch-manipulation"
                                >
                                    Previous
                                </flux:button>

                                <button 
                                    type="button" 
                                    @click="nextQuestion()" 
                                    class="px-5 sm:px-8 py-2.5 sm:py-3 rounded-xl bg-[#9b0000] hover:bg-[#7a0000] text-white dark:bg-[#a82e2e] dark:hover:bg-[#b93838] dark:text-[#f4f4f5] text-xs sm:text-sm font-bold font-sans shadow-md transition-all duration-150 flex items-center gap-2 cursor-pointer border border-[#9b0000] dark:border-[#b93b3b] min-h-[42px] sm:min-h-[44px] touch-manipulation"
                                >
                                    <span x-text="currentIndex === totalQuestions - 1 ? 'Review & Submit →' : 'Next Question →'"></span>
                                </button>
                            </div>

                            <!-- Keyboard Shortcuts Helper Badge Bar -->
                            <div class="hidden sm:flex items-center justify-center gap-3 text-[11px] text-zinc-500 dark:text-zinc-400 pt-2 border-t border-dashed border-zinc-200 dark:border-zinc-800 font-sans">
                                <span class="inline-flex items-center gap-1 font-mono">
                                    <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px] font-bold text-zinc-700 dark:text-zinc-200 shadow-2xs border border-zinc-300 dark:border-zinc-600">1</kbd>–<kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px] font-bold text-zinc-700 dark:text-zinc-200 shadow-2xs border border-zinc-300 dark:border-zinc-600">5</kbd> Rate
                                </span>
                                <span class="opacity-40">•</span>
                                <span class="inline-flex items-center gap-1 font-mono">
                                    <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px] font-bold text-zinc-700 dark:text-zinc-200 shadow-2xs border border-zinc-300 dark:border-zinc-600">←</kbd> / <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px] font-bold text-zinc-700 dark:text-zinc-200 shadow-2xs border border-zinc-300 dark:border-zinc-600">→</kbd> Navigate
                                </span>
                                <span class="opacity-40">•</span>
                                <span class="inline-flex items-center gap-1 font-mono">
                                    <kbd class="px-1.5 py-0.5 rounded bg-zinc-200 dark:bg-zinc-700 font-mono text-[10px] font-bold text-zinc-700 dark:text-zinc-200 shadow-2xs border border-zinc-300 dark:border-zinc-600">Enter</kbd> Next
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </template>

        <!-- Final Summary & Review Step -->
        <template x-if="isReviewStep">
            <form 
                @submit.prevent="if (answeredCount < totalQuestions || {{ $retryAfter }} > 0) { return false; } $wire.submit()" 
                class="flex flex-col gap-6"
            >
                <!-- Review Step Header -->
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800 gap-2">
                    <h3 class="text-sm sm:text-lg font-bold text-zinc-900 dark:text-zinc-100 leading-snug">
                        Evaluation Summary & Final Review
                    </h3>

                    <flux:button 
                        variant="subtle" 
                        type="button" 
                        @click="goToQuestion(0)" 
                        icon="pencil-square" 
                        size="sm"
                        class="cursor-pointer shrink-0"
                    >
                        Edit Answers
                    </flux:button>
                </div>

                <!-- Answer Completion Status Banner -->
                <template x-if="answeredCount < totalQuestions">
                    <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                            <div class="text-xs font-semibold">
                                You have answered <span class="font-bold text-amber-900 dark:text-amber-200" x-text="answeredCount"></span> of <span class="font-bold text-amber-900 dark:text-amber-200" x-text="totalQuestions"></span> questions. All questions require a rating before submitting.
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="goToNextUnanswered()" 
                            class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-bold text-xs shrink-0 transition-colors cursor-pointer self-start sm:self-auto flex items-center gap-1.5"
                        >
                            <span>Jump to Missing Question</span>
                            <span>→</span>
                        </button>
                    </div>
                </template>

                <template x-if="answeredCount === totalQuestions">
                    <div class="p-3.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300 rounded-xl flex items-center gap-3">
                        <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                        <div class="text-xs font-semibold">
                            All <span class="font-bold text-emerald-900 dark:text-emerald-200" x-text="totalQuestions"></span> questions answered! Ready for final submission.
                        </div>
                    </div>
                </template>

                <!-- Rating Matrix Breakdown by Criterion -->
                <div class="flex flex-col gap-3 sm:gap-4">
                    @foreach($this->criteria as $criterion)
                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3 sm:p-4 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col gap-2">
                            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-1.5">
                                <h4 class="font-bold font-sans text-zinc-900 dark:text-zinc-100 text-xs sm:text-sm">
                                    {{ $criterion->name }}
                                </h4>
                                <span class="text-[10px] sm:text-[11px] font-mono font-bold text-zinc-500 dark:text-zinc-400 shrink-0 tabular-nums">
                                    Max: {{ (float)$criterion->max_points }} pts
                                </span>
                            </div>

                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach($criterion->questions as $q)
                                    @php
                                        $qIndexInFlat = array_search($q->id, array_column($flatQuestions, 'id'));
                                    @endphp
                                    <div class="py-2 flex items-center justify-between gap-2.5 text-xs">
                                        <div class="flex items-start gap-1.5 flex-1 min-w-0 pr-1">
                                            <span class="font-mono font-bold text-zinc-400 shrink-0 mt-0.5">{{ $qIndexInFlat !== false ? $qIndexInFlat + 1 : '' }}.</span>
                                            <span class="font-medium font-sans text-zinc-800 dark:text-zinc-200 line-clamp-2 sm:truncate leading-snug">{{ $q->question_text }}</span>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0 self-center">
                                            <template x-if="ratings[{{ $q->id }}]">
                                                <span class="px-2 py-0.5 rounded-md bg-[#9b0000] dark:bg-[#a82e2e] text-white font-mono font-bold text-[11px] sm:text-xs whitespace-nowrap tabular-nums shadow-2xs">
                                                    <span x-text="ratings[{{ $q->id }}]"></span>/5
                                                </span>
                                            </template>
                                            <template x-if="!ratings[{{ $q->id }}]">
                                                <button 
                                                    type="button" 
                                                    @click="goToQuestion({{ $qIndexInFlat !== false ? $qIndexInFlat : 0 }})" 
                                                    class="px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50 font-sans font-bold text-[10.5px] sm:text-[11px] hover:underline cursor-pointer whitespace-nowrap"
                                                >
                                                    Unanswered
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Comments Textarea -->
                <div class="flex flex-col gap-2 border-t border-zinc-200 dark:border-zinc-800 pt-4">
                    <div class="flex items-center justify-between">
                        <label for="comments" class="text-sm font-bold font-sans text-zinc-800 dark:text-zinc-200 flex items-center gap-1">
                            Comments & Suggestions <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <span class="text-xs font-mono text-zinc-400 dark:text-zinc-500 tabular-nums" x-text="`${(comments || '').trim().length} chars (min 3)`"></span>
                    </div>
                    <textarea 
                        id="comments" 
                        wire:model.live.debounce.300ms="comments" 
                        rows="3" 
                        placeholder="Share constructive feedback here (required, min 3 characters)..." 
                        class="w-full rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 py-3 text-sm focus:border-[#9b0000] focus:ring-1 focus:ring-[#9b0000] outline-none text-zinc-800 dark:text-zinc-200 transition-colors duration-200 font-sans"
                    ></textarea>
                    @error('comments')
                        <div class="text-xs text-rose-500 font-semibold mt-1 flex items-center gap-1 font-sans">
                            <flux:icon icon="exclamation-circle" class="size-4 shrink-0 text-rose-500" />
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <!-- Terms & Privacy Confirmation Notice -->
                <div class="p-3 sm:p-3.5 bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 rounded-xl text-[11px] sm:text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed">
                    By submitting this evaluation, you confirm that your feedback is constructive, truthful, and adheres to institutional guidelines. All evaluation responses are processed in accordance with the 
                    <button 
                        type="button" 
                        @click="$dispatch('open-terms-modal')" 
                        class="font-bold underline text-[#9b0000] dark:text-[#e07a7a] hover:opacity-80 transition-opacity cursor-pointer inline-flex items-center gap-0.5"
                    >
                        Terms of Use & Privacy Policy
                    </button>.
                </div>

                <!-- Review Action Controls -->
                <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 border-t border-zinc-200 dark:border-zinc-800 pt-5 mt-2">
                    <flux:button 
                        variant="subtle" 
                        type="button" 
                        @click="goToQuestion(0)" 
                        icon="arrow-left"
                        class="w-full sm:w-auto cursor-pointer justify-center"
                    >
                        Back to Questions
                    </flux:button>

                    <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                        <flux:button 
                            variant="ghost" 
                            type="button" 
                            @click="clearDraft(); $wire.resetForm()" 
                            :disabled="$retryAfter > 0"
                            class="cursor-pointer text-xs shrink-0"
                        >
                            Reset All
                        </flux:button>
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            wire:offline.attr="disabled"
                            wire:target="submit"
                            :disabled="!isReadyToSubmit"
                            @click="if (!isReadyToSubmit) { $event.preventDefault(); $event.stopPropagation(); return false; }"
                            class="flex-1 sm:flex-none px-6 py-2.5 rounded-xl font-bold justify-center text-sm transition-all duration-150 inline-flex items-center gap-2 border shadow-md whitespace-nowrap shrink-0 disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="isReadyToSubmit ? 'bg-[#9b0000] hover:bg-[#7a0000] border-[#9b0000] text-white dark:bg-[#a82e2e] dark:hover:bg-[#b93838] dark:text-[#f4f4f5] dark:border-[#b93b3b] cursor-pointer' : 'bg-zinc-300 dark:bg-zinc-700 border-zinc-300 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400 cursor-not-allowed pointer-events-none'"
                        >
                            <!-- Offline State Indicator -->
                            <span x-show="isOffline" x-cloak class="inline-flex items-center gap-1.5 whitespace-nowrap text-zinc-500 dark:text-zinc-400">
                                <flux:icon icon="wifi" class="size-4 shrink-0 animate-pulse" />
                                <span>Waiting for Connection…</span>
                            </span>

                            <!-- Online States -->
                            <span x-show="!isOffline" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                <span wire:loading.remove wire:target="submit" class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                    <flux:icon icon="paper-airplane" class="size-4 shrink-0" />
                                    <span>Submit Evaluation</span>
                                </span>
                                <span wire:loading.inline-flex wire:target="submit" class="items-center gap-1.5 whitespace-nowrap">
                                    <flux:icon icon="arrow-path" class="size-4 shrink-0 animate-spin" />
                                    <span>Submitting...</span>
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </form>
        </template>
    </div>

    @if($evaluationType === 'peer')
        <flux:modal wire:model="showExemptionModal" class="md:w-[480px]">
            <form wire:submit="submitExemption" class="space-y-4">
                <div>
                    <flux:heading size="lg">No Basis to Observe</flux:heading>
                    <flux:subheading>
                        Exempt yourself from evaluating this peer professor if you did not have sufficient opportunity to observe or collaborate this semester.
                    </flux:subheading>
                </div>

                <flux:field>
                    <flux:label>Institutional Reason <span class="text-red-500">*</span></flux:label>
                    <flux:select wire:model.live="exemptionReason" placeholder="Select a reason…">
                        <flux:select.option value="schedule_conflict">Different schedule / no direct interaction</flux:select.option>
                        <flux:select.option value="different_specialization">Different specialization / separate department branch</flux:select.option>
                        <flux:select.option value="new_faculty">New faculty member / insufficient observation window</flux:select.option>
                        <flux:select.option value="other">Other reason (requires explanation below)</flux:select.option>
                    </flux:select>
                    <flux:error name="exemptionReason" />
                </flux:field>

                <flux:field>
                    <flux:label>Remarks / Notes {{ $exemptionReason === 'other' ? '(Required)' : '(Optional)' }}</flux:label>
                    <flux:textarea 
                        wire:model="exemptionNotes" 
                        placeholder="Provide details for Dean and HR audit review…" 
                        rows="3"
                    />
                    <flux:error name="exemptionNotes" />
                </flux:field>

                <div class="p-3 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 rounded-lg text-xs text-amber-900 dark:text-amber-200">
                    <span class="font-bold">Accreditation Audit Note:</span> This exemption creates a formal audit log for the Dean and HR. Dynamic weight normalization ensures the evaluatee is not penalized.
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <flux:button variant="ghost" type="button" wire:click="$set('showExemptionModal', false)">
                        Cancel
                    </flux:button>
                    <flux:button variant="danger" type="submit" wire:loading.attr="disabled">
                        Confirm Exemption
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
