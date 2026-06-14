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
    public string $evaluationType = 'student'; // 'student', 'peer', 'self'

    public array $ratings = []; // [question_id => rating]
    public string $comments = '';
    public int $retryAfter = 0;

    // Configurable list of curse words to automatically mask/filter out
    protected array $curseWords = [
        // English
        'fuck', 'fucker', 'fucking', 'fucked', 'fuckup', 'motherfucker', 'motherfucking',
        'shit', 'shitty', 'shithead', 'bullshit', 'horseshit',
        'bitch', 'bitchy', 'bitches', 'son of a bitch',
        'asshole', 'ass', 'arse', 'arsehole',
        'bastard', 'bastards',
        'cunt', 'cunts',
        'dick', 'dickhead', 'dickface',
        'cock', 'cocksucker', 'cock sucker',
        'pussy', 'pussies',
        'whore', 'whores', 'whorehouse',
        'slut', 'slutty', 'sluts',
        'damn', 'dammit', 'damned',
        'hell', 'bloody hell',
        'crap', 'crappy',
        'piss', 'pissed', 'pisser',
        'twat', 'twats',
        'wanker', 'wankers', 'wank',
        'bollocks', 'bollock',
        'bugger', 'buggered',
        'prick', 'pricks',
        'skank', 'skanky',
        'douchebag', 'douche',
        'jackass', 'jackasses',
        'dipshit', 'dumbass', 'dumbfuck',
        'numbnuts', 'shitface', 'shitbag',
        'fag', 'faggot',
        'retard', 'retarded',
        'scumbag', 'scum',
        'pervert', 'perv',
        'cuck', 'cucked',

        // Tagalog
        'putangina', 'putanginamo', 'putangina mo', 'puta', 'putang ina', 'putang ina mo',
        'tangina', 'tanginamo', 'tangina mo',
        'gago', 'gagong', 'gaga', 'mga gago',
        'tarantado', 'tarantadong',
        'ulol', 'ulolang',
        'pucha', 'puchanggala', 'puchangina',
        'kupal', 'kupaling',
        'siraulo', 'sira ulo', 'sira ang ulo',
        'bobo', 'bobong', 'boba',
        'tanga', 'tangang', 'mga tanga',
        'inutil', 'inutilang',
        'lintik', 'lintikan', 'lintik ka',
        'leche', 'letse', 'letseplan',
        'pakyu', 'pak yu', 'pakyu ka',
        'bwisit', 'bwisitin', 'nakakabwisit',
        'hayop', 'hayop ka', 'hayup',
        'hindot', 'hindotan', 'naka hindot',
        'jakol', 'jakulero',
        'kantot', 'kantotin', 'nakatantot',
        'pakshet', 'pak shet',
        'pesteng yawa', 'yawa',
        'demonyo', 'demonyong',
        'buang', 'buanga',
        'gunggong', 'gungong',
        'engot', 'engoting',
        'ungas', 'ungasang',
        'baliw', 'baliwang', 'baliw ka',
        'ampota', 'ampotak',
        'leching', 'leching yan',
        'salbaheng', 'salbahe',
        'walanghiya', 'wala kang hiya', 'walang hiya',
        'walang kwenta', 'wala kang kwenta',
        'mangmang', 'mangmang ka',
        'hudas', 'hudasang',
        'duwag', 'duwaging',
        'sinungaling', 'sinungalingang',
        'burat', 'burating',
        'puke', 'puking',
        'pwet', 'pwetan',
        'bilat', 'bilating',
    ];

    // Words that overlap with common, clean words and therefore require strict word boundaries (\b)
    protected array $strictBoundaryWords = [
        'ass', 'hell', 'crap', 'aso', 'arte', 'supot', 'tanga', 'leche', 'cock'
    ];

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
        if (!$text) {
            return '';
        }

        foreach ($this->curseWords as $word) {
            // If the word requires strict boundaries, compile with \b
            if (in_array($word, $this->strictBoundaryWords)) {
                $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            } else {
                $pattern = '/' . preg_quote($word, '/') . '/i';
            }
            $text = preg_replace($pattern, '', $text);
        }

        // Clean up double spaces and trim whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public function mount()
    {
        $rateLimitKey = 'submit-evaluation:' . auth()->id() . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->retryAfter = RateLimiter::availableIn($rateLimitKey);
        }
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->ratings = [];
        $this->comments = '';
        
        // Initialize ratings keys for all active questions in this type
        $criteria = EvaluationCriterion::where('evaluation_type', $this->evaluationType)
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
        return EvaluationCriterion::where('evaluation_type', $this->evaluationType)
            ->with(['questions' => fn($q) => $q->where('is_active', true)])
            ->orderBy('order')
            ->get();
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
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->retryAfter = RateLimiter::availableIn($rateLimitKey);
            return;
        }

        // Validate that all questions have a rating between 1 and 5
        $rules = [];
        $messages = [];
        foreach ($this->ratings as $questionId => $rating) {
            $rules["ratings.{$questionId}"] = 'required|integer|between:1,5';
            $messages["ratings.{$questionId}.required"] = 'Please provide a rating for this question.';
        }

        $this->validate($rules, $messages);

        // Record the hit to the rate limiter on successful validation (3-minute cooldown)
        RateLimiter::hit($rateLimitKey, 180);

        // Convert values to integers
        $sanitizedRatings = collect($this->ratings)->map(fn($val) => (int)$val)->toArray();

        // Final sanitation check on submission
        $cleanComments = $this->filterProfanity($this->comments);

        // Dispatch background job for queue processing and idempotency
        ProcessEvaluationSubmission::dispatch(
            auth()->id(),
            $this->evaluatee->id,
            $activeSem->id,
            $this->class?->id,
            $this->evaluationType,
            $sanitizedRatings,
            $cleanComments ?: null
        );

        // If queue driver is sync, the database updates immediately.
        // If database queue is used, it will be processed in background.
        
        $this->dispatch('evaluation-submitted');
        
        session()->flash('success', 'Evaluation submitted successfully to the background processing queue.');
        $this->resetForm();
    }
}; ?>

<div x-data="{ ratings: @entangle('ratings') }" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl overflow-hidden">
    <!-- Header -->
    <div class="px-6 py-5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
        <div>
            <span class="text-xs uppercase tracking-wider bg-white/20 px-2.5 py-1 rounded-full font-semibold">
                {{ strtoupper($evaluationType) }} EVALUATION
            </span>
            <h2 class="text-xl font-bold mt-2">
                @if($evaluationType === 'self')
                    Self Evaluation
                @else
                    Evaluating: {{ $evaluatee->name }}
                @endif
            </h2>
            @if($class)
                <p class="text-sm opacity-90 font-medium mt-1">
                    Class: <span class="underline">{{ $class->subject->code }} - {{ $class->subject->name }}</span> ({{ $class->section }})
                </p>
            @endif
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session()->has('success'))
        <div class="mx-6 mt-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl flex items-center gap-3">
            <flux:icon icon="check-circle" class="size-6 text-emerald-600" />
            <div class="text-sm font-semibold">{{ session('success') }}</div>
        </div>
    @endif

    @if($retryAfter > 0)
        <div x-data="{ seconds: @entangle('retryAfter') }" 
             x-init="const interval = setInterval(() => { if (seconds > 0) { seconds--; } else { clearInterval(interval); $wire.set('retryAfter', 0); } }, 1000)"
             class="mx-6 mt-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-3">
            <flux:icon icon="clock" class="size-6 text-rose-600 animate-pulse" />
            <div class="text-sm font-semibold">
                Too many submission attempts. Please wait <span x-text="seconds" class="font-bold"></span> seconds before submitting again.
            </div>
        </div>
    @elseif(session()->has('error'))
        <div class="mx-6 mt-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl flex items-center gap-3">
            <flux:icon icon="x-circle" class="size-6 text-rose-600" />
            <div class="text-sm font-semibold">{{ session('error') }}</div>
        </div>
    @endif

    <!-- Form -->
    <form wire:submit="submit" class="p-6 flex flex-col gap-6">
        @php $qIndex = 1; @endphp
        @foreach($this->criteria as $criterion)
            <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl p-4 md:p-6 bg-zinc-50/50 dark:bg-zinc-800/20 flex flex-col gap-4">
                <div class="flex justify-between items-center border-b border-zinc-150 dark:border-zinc-800 pb-3">
                    <h3 class="font-bold text-zinc-800 dark:text-zinc-100 text-base md:text-lg">
                        {{ $criterion->name }}
                    </h3>
                    <span class="text-xs font-semibold bg-zinc-200 dark:bg-zinc-700 text-zinc-700 dark:text-zinc-300 px-2 py-1 rounded">
                        Max: {{ (float)$criterion->max_points }} pts
                    </span>
                </div>

                <div class="flex flex-col gap-6 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($criterion->questions as $question)
                        <div class="pt-4 flex flex-col lg:flex-row lg:justify-between lg:items-center gap-4 {{ $loop->first ? 'pt-0' : '' }}">
                            <div class="flex-1">
                                <p class="text-sm md:text-base font-medium text-zinc-800 dark:text-zinc-200 flex gap-2">
                                    <span class="text-indigo-500 font-bold">{{ $qIndex++ }}.</span>
                                    <span>{{ $question->question_text }}</span>
                                </p>
                                @error("ratings.{$question->id}")
                                    <span class="text-xs text-rose-500 font-semibold mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Horizontal 1-5 rating buttons -->
                            <div class="flex justify-center items-center gap-1.5 md:gap-2 self-center lg:self-auto shrink-0">
                                @for($ratingVal = 1; $ratingVal <= 5; $ratingVal++)
                                    <label class="group cursor-pointer select-none">
                                        <input type="radio" 
                                               x-model="ratings[{{ $question->id }}]" 
                                               value="{{ $ratingVal }}" 
                                               class="sr-only" />
                                        <div class="w-10 h-10 md:w-11 md:h-11 rounded-full flex flex-col justify-center items-center text-xs md:text-sm font-bold border transition-all duration-200"
                                             :class="ratings && ratings[{{ $question->id }}] == {{ $ratingVal }}
                                                 ? 'bg-indigo-600 border-indigo-600 text-white shadow-md shadow-indigo-200 dark:shadow-none scale-110'
                                                 : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 hover:border-indigo-400 dark:hover:border-indigo-500'">
                                            <span>{{ $ratingVal }}</span>
                                        </div>
                                    </label>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <!-- Legend / Rating Guide -->
        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-100 dark:border-zinc-800 flex flex-wrap justify-between items-center gap-4 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
            <span class="uppercase tracking-wider">Rating Scale:</span>
            <div class="flex flex-wrap gap-4">
                <span>1 - Poor</span>
                <span>2 - Fair</span>
                <span>3 - Satisfactory</span>
                <span>4 - Very Satisfactory</span>
                <span>5 - Outstanding</span>
            </div>
        </div>

        <!-- Comments -->
        <div class="flex flex-col gap-2">
            <label for="comments" class="text-sm font-bold text-zinc-700 dark:text-zinc-300">
                Comments / Suggestions (Optional)
            </label>
            <textarea id="comments" 
                      wire:model.live.debounce.1000ms="comments" 
                      rows="4" 
                      placeholder="Share constructive feedback here..." 
                      class="w-full rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none text-zinc-800 dark:text-zinc-200 transition-colors duration-200"></textarea>
        </div>

        <!-- Action buttons -->
        <div class="flex justify-end gap-3 mt-4 border-t border-zinc-100 dark:border-zinc-800 pt-4">
            <flux:button variant="ghost" type="button" wire:click="resetForm" :disabled="$retryAfter > 0">
                Clear Form
            </flux:button>
            <flux:button variant="primary" type="submit" :disabled="$retryAfter > 0">
                Submit Evaluation
            </flux:button>
        </div>
    </form>
</div>
