<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\AcademicClass;
use App\Models\EvaluationCriterion;
use App\Models\Semester;
use App\Jobs\ProcessEvaluationSubmission;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component {
    public User $evaluatee;
    public ?AcademicClass $class = null;
    public string $evaluationType = 'upward_student'; // 'upward_student', 'upward_employee', 'downward', 'peer', 'self'

    public array $ratings = []; // [question_id => rating]
    public string $comments = '';
    public int $retryAfter = 0;

    public function updatedComments($value)
    {
        $filtered = $this->filterProfanity($value);

        if ($filtered !== $value) {
            $this->comments = $filtered;

            \Flux::toast(
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
        return app(\App\Services\ProfanityFilterService::class)->clean($text);
    }

    public function mount()
    {
        $rateLimitKey = 'submit-evaluation:' . auth()->id() . ':' . request()->ip();
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
            'student', 'upward_student' => ['student', 'upward_student'],
            'dean' => ['dean', 'downward'],
            'program_head', 'ph_dh' => ['program_head', 'ph_dh', 'downward'],
            'department_head' => ['department_head', 'downward'],
            'downward' => ['downward', 'dean', 'program_head', 'department_head'],
            'peer' => ['peer'],
            'superior', 'upward_employee' => ['superior', 'upward_employee'],
            'self' => ['self'],
            default => [$this->evaluationType],
        };

        $criteria = EvaluationCriterion::whereIn('evaluation_type', $types)
            ->with(['questions' => fn($q) => $q->where('is_active', true)])
            ->get();

        foreach ($criteria as $criterion) {
            foreach ($criterion->questions as $question) {
                $this->ratings[$question->id] = '';
            }
        }
    }

    public function getCriteriaProperty()
    {
        $types = match ($this->evaluationType) {
            'student', 'upward_student' => ['student', 'upward_student'],
            'dean' => ['dean', 'downward'],
            'program_head', 'ph_dh' => ['program_head', 'ph_dh', 'downward'],
            'department_head' => ['department_head', 'downward'],
            'downward' => ['downward', 'dean', 'program_head', 'department_head'],
            'peer' => ['peer'],
            'superior', 'upward_employee' => ['superior', 'upward_employee'],
            'self' => ['self'],
            default => [$this->evaluationType],
        };

        return EvaluationCriterion::whereIn('evaluation_type', $types)
            ->with(['questions' => fn($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get();
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
                    'max_points' => (float)$criterion->max_points,
                ];
            }
        }
        return $questions;
    }

    public function submit()
    {
        $activeSem = Semester::where('is_active', true)->first();
        
        if (!$activeSem) {
            session()->flash('error', 'There is no active semester. Submissions are disabled.');
            return;
        }

        if (!$activeSem->isEvaluationWindowActive()) {
            session()->flash('error', 'Evaluations are currently closed.');
            return;
        }

        $rateLimitKey = 'submit-evaluation:' . auth()->id() . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 50)) {
            $this->retryAfter = RateLimiter::availableIn($rateLimitKey);
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

        $sanitizedRatings = collect($this->ratings)->map(fn($val) => (int)$val)->toArray();
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
        
        session()->flash('success', 'Evaluation submitted successfully to the background processing queue.');
        $this->resetForm();
    }
}; ?>

@php
    $flatQuestions = $this->questions;
    $totalQuestionsCount = count($flatQuestions);
@endphp

<div 
    x-data="{ 
        ratings: @entangle('ratings'),
        comments: @entangle('comments'),
        currentIndex: 0,
        totalQuestions: {{ $totalQuestionsCount }},
        isReviewStep: false,
        autoAdvanceTimeout: null,
        storageKey: 'draft_eval_{{ auth()->id() }}_{{ $evaluationType }}_{{ $evaluatee->id }}_{{ $class?->id ?? 'noclass' }}',

        init() {
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
                } else {
                    this.isReviewStep = true;
                }
            }, 300);
        },
        
        goToQuestion(index) {
            this.isReviewStep = false;
            this.currentIndex = index;
            this.saveDraft();
        },

        nextQuestion() {
            if (this.currentIndex < this.totalQuestions - 1) {
                this.currentIndex++;
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
            }
            this.saveDraft();
        },

        get answeredCount() {
            return Object.values(this.ratings).filter(r => r !== '' && r !== null && r !== undefined).length;
        },

        get progressPercent() {
            if (this.totalQuestions === 0) return 0;
            return Math.round((this.answeredCount / this.totalQuestions) * 100);
        }
    }" 
    @evaluation-submitted.window="clearDraft()"
    class="max-w-4xl mx-auto bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-lg overflow-hidden"
>
    <!-- Simple Header: Name & Progress -->
    <div class="px-4 sm:px-6 py-4 bg-[#9b0000] text-white flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
        <div>
            <h2 class="text-base sm:text-lg md:text-xl font-bold">
                @if($evaluationType === 'self')
                    Self Evaluation
                @else
                    Evaluating: {{ $evaluatee->name }}
                @endif
            </h2>
            @if($class)
                <p class="text-xs text-red-100 mt-0.5">
                    {{ $class->subject->code }} - {{ $class->subject->name }} ({{ $class->section }})
                </p>
            @endif
        </div>

        <div class="flex items-center self-start sm:self-auto gap-2 text-xs font-bold bg-white/15 px-3 py-1.5 rounded-full border border-white/20 shrink-0">
            <span x-text="`${answeredCount}/${totalQuestions} Answered`"></span>
            <span class="opacity-60">•</span>
            <span x-text="`${progressPercent}%`" class="text-amber-300"></span>
        </div>
    </div>

    <!-- Progress Bar Line -->
    <div class="w-full h-1.5 bg-zinc-200 dark:bg-zinc-800">
        <div 
            class="h-full bg-amber-400 dark:bg-amber-400 transition-all duration-300 ease-out shadow-sm" 
            :style="`width: ${progressPercent}%`"
        ></div>
    </div>

    <!-- Alert Messages -->
    @if(session()->has('success'))
        <div class="mx-4 sm:mx-6 mt-4 sm:mt-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
            <div class="text-sm font-semibold">{{ session('success') }}</div>
        </div>
    @endif

    @if($retryAfter > 0)
        <div x-data="{ seconds: @entangle('retryAfter') }" 
             x-init="const interval = setInterval(() => { if (seconds > 0) { seconds--; } else { clearInterval(interval); $wire.set('retryAfter', 0); } }, 1000)"
             class="mx-4 sm:mx-6 mt-4 sm:mt-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="clock" class="size-5 text-rose-600 dark:text-rose-400 animate-pulse shrink-0" />
            <div class="text-sm font-semibold">
                Too many submission attempts. Please wait <span x-text="seconds" class="font-bold"></span> seconds before submitting again.
            </div>
        </div>
    @elseif(session()->has('error'))
        <div class="mx-4 sm:mx-6 mt-4 sm:mt-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-300 rounded-xl flex items-center gap-3">
            <flux:icon icon="x-circle" class="size-5 text-rose-600 dark:text-rose-400 shrink-0" />
            <div class="text-sm font-semibold">{{ session('error') }}</div>
        </div>
    @endif

    <!-- Question Number Pills Grid Navigator -->
    <div class="px-4 sm:px-6 py-3 border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-800/40">
        <div class="flex items-center gap-1.5 overflow-x-auto sm:flex-wrap sm:max-h-24 sm:overflow-y-auto pb-1 sm:pb-0">
            @foreach($flatQuestions as $idx => $q)
                <button
                    type="button"
                    @click="goToQuestion({{ $idx }})"
                    class="size-8 min-w-8 rounded-lg text-xs font-bold transition-all duration-150 flex items-center justify-center cursor-pointer border shrink-0"
                    :class="{
                        'bg-[#9b0000] border-[#9b0000] text-white shadow-sm scale-105': !isReviewStep && currentIndex === {{ $idx }},
                        'bg-emerald-500/15 border-emerald-500/30 text-emerald-700 dark:text-emerald-400': (!isReviewStep && currentIndex !== {{ $idx }}) && ratings[{{ $q['id'] }}],
                        'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 hover:border-zinc-400': (!isReviewStep && currentIndex !== {{ $idx }}) && !ratings[{{ $q['id'] }}],
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
                class="h-8 px-3 rounded-lg text-xs font-bold transition-all duration-150 flex items-center gap-1.5 cursor-pointer border shrink-0 whitespace-nowrap"
                :class="{
                    'bg-[#9b0000] border-[#9b0000] text-white shadow-sm': isReviewStep,
                    'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-zinc-400': !isReviewStep
                }"
            >
                <flux:icon icon="clipboard-document-check" class="size-3.5" />
                <span>Review & Submit</span>
            </button>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="p-4 sm:p-6 md:p-8">

        <!-- Active Question Card Step (Wrapped in single root div for Alpine template compatibility) -->
        <template x-if="!isReviewStep && totalQuestions > 0">
            <div>
                @foreach($flatQuestions as $idx => $q)
                    <div 
                        x-show="currentIndex === {{ $idx }}" 
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 scale-98"
                        x-transition:enter-end="opacity-100 scale-100"
                        class="flex flex-col gap-6"
                    >
                        <!-- Question Header Info -->
                        <div class="flex items-center justify-between gap-2 pb-3 sm:pb-4 border-b border-zinc-200 dark:border-zinc-800/80">
                            <span class="px-2.5 sm:px-3 py-1 rounded-lg bg-red-950/10 dark:bg-red-950/40 text-[#9b0000] dark:text-[#f89696] text-[11px] sm:text-xs font-bold border border-red-900/20 truncate max-w-[200px] sm:max-w-none">
                                {{ $q['criterion_name'] }}
                            </span>
                            <span class="text-xs font-bold text-zinc-500 dark:text-zinc-400 shrink-0">
                                Question {{ $idx + 1 }} of {{ $totalQuestionsCount }}
                            </span>
                        </div>

                        <!-- Question Text -->
                        <div class="py-4 sm:py-6 min-h-[80px] sm:min-h-[90px] flex items-center justify-center">
                            <h3 class="text-base sm:text-xl md:text-2xl font-bold text-zinc-900 dark:text-zinc-100 text-center leading-relaxed max-w-2xl px-1">
                                {{ $q['question_text'] }}
                            </h3>
                        </div>

                        <!-- Horizontal 1-5 Rating Buttons Container -->
                        <div class="my-3 sm:my-5 flex flex-col items-center justify-center gap-4 sm:gap-6 w-full">
                            <!-- 1 to 5 Buttons with Clean Aspect-Square Dimensions -->
                            <div class="grid grid-cols-5 gap-2 sm:gap-3 md:gap-5 w-full max-w-xs sm:max-w-md md:max-w-lg justify-items-center py-2">
                                @for($ratingVal = 1; $ratingVal <= 5; $ratingVal++)
                                    <button
                                        type="button"
                                        @click="selectRating({{ $q['id'] }}, {{ $ratingVal }})"
                                        class="w-full max-w-[4rem] sm:max-w-[4.5rem] md:max-w-[5.5rem] aspect-square rounded-xl sm:rounded-2xl border-2 text-lg sm:text-2xl md:text-3xl font-black transition-all duration-200 flex items-center justify-center cursor-pointer select-none shrink-0 shadow-sm"
                                        :class="ratings[{{ $q['id'] }}] == {{ $ratingVal }}
                                            ? 'bg-[#9b0000] border-[#9b0000] text-white shadow-xl shadow-red-950/50 scale-105 sm:scale-110 ring-2 sm:ring-4 ring-red-900/30'
                                            : 'bg-zinc-100 dark:bg-zinc-800 border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 hover:bg-red-100/80 dark:hover:bg-red-900/50 hover:border-[#9b0000] dark:hover:border-[#f89696] hover:text-[#9b0000] dark:hover:text-[#f89696]'"
                                    >
                                        <span class="leading-none">{{ $ratingVal }}</span>
                                    </button>
                                @endfor
                            </div>

                            <!-- Separate Divider Line & Scale Legend Badges -->
                            <div class="w-full pt-4 sm:pt-6 border-t border-zinc-200 dark:border-zinc-800/80 flex flex-wrap justify-center items-center gap-2 sm:gap-4 md:gap-6 text-[11px] sm:text-xs md:text-sm font-semibold text-zinc-600 dark:text-zinc-400">
                                <span class="flex items-center gap-1.5"><strong class="size-4 sm:size-5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-[10px] sm:text-[11px] font-black flex items-center justify-center">1</strong> Poor</span>
                                <span class="opacity-40">•</span>
                                <span class="flex items-center gap-1.5"><strong class="size-4 sm:size-5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-[10px] sm:text-[11px] font-black flex items-center justify-center">2</strong> Fair</span>
                                <span class="opacity-40">•</span>
                                <span class="flex items-center gap-1.5"><strong class="size-4 sm:size-5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-[10px] sm:text-[11px] font-black flex items-center justify-center">3</strong> Satisfactory</span>
                                <span class="opacity-40">•</span>
                                <span class="flex items-center gap-1.5"><strong class="size-4 sm:size-5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-[10px] sm:text-[11px] font-black flex items-center justify-center">4</strong> Very Satisfactory</span>
                                <span class="opacity-40">•</span>
                                <span class="flex items-center gap-1.5"><strong class="size-4 sm:size-5 rounded-full bg-zinc-200 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 text-[10px] sm:text-[11px] font-black flex items-center justify-center">5</strong> Outstanding</span>
                            </div>
                        </div>

                        <!-- Card Controls -->
                        <div class="flex items-center justify-between gap-3 border-t border-zinc-200 dark:border-zinc-800/80 pt-6 mt-2">
                            <flux:button 
                                variant="subtle" 
                                type="button" 
                                @click="prevQuestion()" 
                                ::disabled="currentIndex === 0"
                                icon="arrow-left"
                                class="cursor-pointer"
                            >
                                Previous
                            </flux:button>

                            <button 
                                type="button" 
                                @click="nextQuestion()" 
                                class="px-5 sm:px-8 py-2.5 sm:py-3 rounded-xl bg-[#9b0000] hover:bg-[#7a0000] text-white dark:bg-[#f89696] dark:hover:bg-[#f57575] dark:text-[#171717] text-xs sm:text-sm font-bold shadow-md transition-all duration-150 flex items-center gap-2 cursor-pointer border border-[#9b0000] dark:border-[#f89696]"
                            >
                                <span x-text="currentIndex === totalQuestions - 1 ? 'Review & Submit →' : 'Next Question →'"></span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </template>

        <!-- Final Summary & Review Step -->
        <template x-if="isReviewStep">
            <form wire:submit="submit" class="flex flex-col gap-6">
                <!-- Review Step Header -->
                <div class="flex items-center justify-between pb-3 border-b border-zinc-200 dark:border-zinc-800">
                    <div>
                        <h3 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">
                            Evaluation Summary & Final Review
                        </h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            Review your ratings and optionally add feedback comments before submitting.
                        </p>
                    </div>

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
                    <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 rounded-xl flex items-center gap-3">
                        <flux:icon icon="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400 shrink-0" />
                        <div class="text-xs font-semibold">
                            You have answered <span class="font-bold text-amber-900 dark:text-amber-200" x-text="answeredCount"></span> of <span class="font-bold text-amber-900 dark:text-amber-200" x-text="totalQuestions"></span> questions. All questions require a rating before submitting.
                        </div>
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
                <div class="flex flex-col gap-4">
                    @foreach($this->criteria as $criterion)
                        <div class="border border-zinc-200 dark:border-zinc-800 rounded-xl p-3.5 sm:p-4 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col gap-2.5">
                            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-1.5">
                                <h4 class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">
                                    {{ $criterion->name }}
                                </h4>
                                <span class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 shrink-0">
                                    Max: {{ (float)$criterion->max_points }} pts
                                </span>
                            </div>

                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach($criterion->questions as $q)
                                    @php
                                        $qIndexInFlat = array_search($q->id, array_column($flatQuestions, 'id'));
                                    @endphp
                                    <div class="py-2 flex items-center justify-between gap-3 text-xs">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <span class="font-bold text-zinc-400 shrink-0">{{ $qIndexInFlat !== false ? $qIndexInFlat + 1 : '' }}.</span>
                                            <span class="font-medium text-zinc-800 dark:text-zinc-200 truncate">{{ $q->question_text }}</span>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            <template x-if="ratings[{{ $q->id }}]">
                                                <span class="px-2 py-0.5 rounded-md bg-[#9b0000] text-white font-bold text-xs">
                                                    Score: <span x-text="ratings[{{ $q->id }}]"></span> / 5
                                                </span>
                                            </template>
                                            <template x-if="!ratings[{{ $q->id }}]">
                                                <button 
                                                    type="button" 
                                                    @click="goToQuestion({{ $qIndexInFlat !== false ? $qIndexInFlat : 0 }})" 
                                                    class="px-2 py-0.5 rounded-md bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/50 font-bold text-[11px] hover:underline cursor-pointer"
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
                    <label for="comments" class="text-sm font-bold text-zinc-800 dark:text-zinc-200 flex items-center gap-1">
                        Comments & Suggestions <span class="text-rose-500 font-bold">*</span>
                    </label>
                    <textarea 
                        id="comments" 
                        wire:model.live.debounce.1000ms="comments" 
                        rows="3" 
                        placeholder="Share constructive feedback here (required)..." 
                        class="w-full rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 py-3 text-sm focus:border-[#9b0000] focus:ring-1 focus:ring-[#9b0000] outline-none text-zinc-800 dark:text-zinc-200 transition-colors duration-200"
                    ></textarea>
                    @error('comments')
                        <div class="text-xs text-rose-500 font-semibold mt-1 flex items-center gap-1">
                            <flux:icon icon="exclamation-circle" class="size-4 shrink-0 text-rose-500" />
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                </div>

                <!-- Terms & Privacy Confirmation Notice -->
                <div class="p-3.5 bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 rounded-xl flex items-start gap-2.5 text-xs text-zinc-600 dark:text-zinc-300">
                    <flux:icon icon="shield-check" class="size-4.5 text-[#9b0000] dark:text-[#f89696] shrink-0 mt-0.5" />
                    <div class="leading-relaxed">
                        By submitting this evaluation, you confirm that your feedback is constructive, truthful, and adheres to institutional guidelines. All evaluation responses are processed in accordance with the 
                        <button 
                            type="button" 
                            @click="$dispatch('open-terms-modal')" 
                            class="font-bold underline text-[#9b0000] dark:text-[#f89696] hover:opacity-80 transition-opacity cursor-pointer inline-flex items-center gap-0.5"
                        >
                            Terms of Use & Privacy Policy
                        </button>.
                    </div>
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
                        <flux:button 
                            variant="primary"
                            type="submit" 
                            :disabled="$retryAfter > 0"
                            class="flex-1 sm:flex-none px-5 sm:px-6 py-2.5 font-bold cursor-pointer justify-center text-sm"
                        >
                            Submit Evaluation
                        </flux:button>
                    </div>
                </div>
            </form>
        </template>
    </div>
</div>
