<?php

use App\Models\Profanity;
use App\Services\ProfanityFilterService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.generic-table-skeleton');
    }

    // Filter & Search state
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    #[Url(as: 'sort')]
    public string $sortBy = 'latest';

    public int $perPage = 15;

    // Modals
    public bool $showAddModal = false;

    public string $addTab = 'single'; // 'single' | 'bulk'

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    // Single Add Form
    public string $newWord = '';

    public string $newCategory = 'custom';

    public bool $newIsActive = true;

    // Bulk Add Form
    public string $bulkWords = '';

    public string $bulkCategory = 'custom';

    // Edit Form
    public ?int $editingProfanityId = null;

    public string $editWord = '';

    public string $editCategory = 'custom';

    public bool $editIsActive = true;

    // Delete Form
    public ?int $deletingProfanityId = null;

    public string $deletingWord = '';

    public string $deletingCategory = '';

    // Live Tester Sandbox
    public string $testInput = '';

    public bool $testHasProfanity = false;

    public string $testCleanedText = '';

    public array $testMatchedWords = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'statusFilter', 'typeFilter']);
        $this->sortBy = 'latest';
        $this->resetPage();
    }

    public function updatedTestInput(string $value): void
    {
        $this->runTestSandbox($value);
    }

    public function runTestSandbox(?string $value = null): void
    {
        $text = $value ?? $this->testInput;
        $service = app(ProfanityFilterService::class);

        if (blank($text)) {
            $this->testHasProfanity = false;
            $this->testCleanedText = '';
            $this->testMatchedWords = [];

            return;
        }

        $this->testHasProfanity = $service->hasProfanity($text);
        $this->testCleanedText = $service->clean($text);

        // Find matching active words
        $activeWords = Profanity::getActiveWords();
        $matched = [];

        foreach ($activeWords as $word) {
            $pattern = $service->buildRegexPattern($word);
            if (preg_match($pattern, $text)) {
                $matched[] = $word;
            }
        }

        $this->testMatchedWords = array_unique($matched);
    }

    public function openAddModal(): void
    {
        $this->reset(['newWord', 'newCategory', 'newIsActive', 'bulkWords', 'bulkCategory']);
        $this->newCategory = 'custom';
        $this->bulkCategory = 'custom';
        $this->newIsActive = true;
        $this->addTab = 'single';
        $this->showAddModal = true;
    }

    public function createSingle(): void
    {
        $this->newWord = strtolower(trim($this->newWord));

        $this->validate([
            'newWord' => ['required', 'string', 'min:2', 'max:191', 'unique:profanities,word'],
            'newCategory' => ['required', 'string', 'in:tagalog,english,custom'],
            'newIsActive' => ['boolean'],
        ], [
            'newWord.required' => 'Please enter the word or phrase to filter.',
            'newWord.unique' => 'This word is already registered in the profanity library.',
        ]);

        Profanity::create([
            'word' => $this->newWord,
            'category' => $this->newCategory,
            'is_active' => $this->newIsActive,
            'created_by' => Auth::id(),
        ]);

        $this->showAddModal = false;
        $this->reset(['newWord', 'newCategory']);

        Flux::toast(
            heading: 'Profanity Word Added',
            text: 'The term has been added and cached for real-time sanitization.',
            variant: 'success'
        );

        $this->runTestSandbox();
    }

    public function createBulk(): void
    {
        $this->validate([
            'bulkWords' => ['required', 'string', 'min:2'],
            'bulkCategory' => ['required', 'string', 'in:tagalog,english,custom'],
        ], [
            'bulkWords.required' => 'Please enter one or more words separated by commas or new lines.',
        ]);

        // Split by newlines, commas, or semicolons
        $rawTokens = preg_split('/[\r\n,;]+/', $this->bulkWords);
        $wordsToInsert = [];
        $skippedCount = 0;
        $now = now();

        $existingWords = Profanity::pluck('word')->map(fn ($w) => strtolower($w))->flip()->toArray();

        foreach ($rawTokens as $raw) {
            $normalized = strtolower(trim($raw));
            if (strlen($normalized) < 2) {
                continue;
            }

            if (isset($existingWords[$normalized]) || isset($wordsToInsert[$normalized])) {
                $skippedCount++;
                continue;
            }

            $wordsToInsert[$normalized] = [
                'word' => $normalized,
                'category' => $this->bulkCategory,
                'is_active' => true,
                'created_by' => Auth::id(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($wordsToInsert)) {
            $this->addError('bulkWords', 'No new unique words found. All entered terms already exist in the library.');

            return;
        }

        Profanity::insert(array_values($wordsToInsert));
        Profanity::clearCache();

        $insertedCount = count($wordsToInsert);
        $this->showAddModal = false;
        $this->reset(['bulkWords', 'bulkCategory']);

        $message = "Added {$insertedCount} words to the library.";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} duplicate words skipped)";
        }

        Flux::toast(
            heading: 'Bulk Import Completed',
            text: $message,
            variant: 'success'
        );

        $this->runTestSandbox();
    }

    public function openEditModal(int $id): void
    {
        $profanity = Profanity::findOrFail($id);

        $this->editingProfanityId = $profanity->id;
        $this->editWord = $profanity->word;
        $this->editCategory = $profanity->category;
        $this->editIsActive = (bool) $profanity->is_active;

        $this->showEditModal = true;
    }

    public function updateProfanity(): void
    {
        if (! $this->editingProfanityId) {
            return;
        }

        $this->editWord = strtolower(trim($this->editWord));

        $this->validate([
            'editWord' => [
                'required',
                'string',
                'min:2',
                'max:191',
                Rule::unique('profanities', 'word')->ignore($this->editingProfanityId),
            ],
            'editCategory' => ['required', 'string', 'in:tagalog,english,custom'],
            'editIsActive' => ['boolean'],
        ]);

        $profanity = Profanity::findOrFail($this->editingProfanityId);
        $profanity->update([
            'word' => $this->editWord,
            'category' => $this->editCategory,
            'is_active' => $this->editIsActive,
        ]);

        $this->showEditModal = false;
        $this->reset(['editingProfanityId', 'editWord', 'editCategory', 'editIsActive']);

        Flux::toast(
            heading: 'Word Updated',
            text: 'Changes saved and profanity cache refreshed.',
            variant: 'success'
        );

        $this->runTestSandbox();
    }

    public function toggleStatus(int $id): void
    {
        $profanity = Profanity::findOrFail($id);
        $profanity->is_active = ! $profanity->is_active;
        $profanity->save();

        Flux::toast(
            heading: $profanity->is_active ? 'Word Activated' : 'Word Deactivated',
            text: "{$profanity->word} is now ".($profanity->is_active ? 'actively filtered' : 'inactive').'.',
            variant: $profanity->is_active ? 'success' : 'warning'
        );

        $this->runTestSandbox();
    }

    public function confirmDelete(int $id): void
    {
        $profanity = Profanity::findOrFail($id);
        $this->deletingProfanityId = $profanity->id;
        $this->deletingWord = $profanity->word;
        $this->deletingCategory = $profanity->category;
        $this->showDeleteModal = true;
    }

    public function deleteProfanity(): void
    {
        if (! $this->deletingProfanityId) {
            return;
        }

        $profanity = Profanity::findOrFail($this->deletingProfanityId);
        $word = $profanity->word;
        $profanity->delete();

        $this->showDeleteModal = false;
        $this->reset(['deletingProfanityId', 'deletingWord', 'deletingCategory']);

        Flux::toast(
            heading: 'Word Deleted',
            text: "\"{$word}\" was removed from the profanity library.",
            variant: 'danger'
        );

        $this->runTestSandbox();
    }

    public function refreshCache(): void
    {
        Profanity::clearCache();

        Flux::toast(
            heading: 'Cache Refreshed',
            text: 'Profanity filter cache has been reloaded from the database.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        $query = Profanity::query()->with('creator');

        if (trim($this->search) !== '') {
            $term = '%'.strtolower(trim($this->search)).'%';
            $query->where('word', 'like', $term);
        }

        if ($this->categoryFilter !== '') {
            $query->where('category', $this->categoryFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        if ($this->typeFilter === 'single') {
            $query->where('word', 'not like', '% %');
        } elseif ($this->typeFilter === 'phrase') {
            $query->where('word', 'like', '% %');
        }

        match ($this->sortBy) {
            'oldest' => $query->orderBy('id', 'asc'),
            'alpha_asc' => $query->orderBy('word', 'asc'),
            'alpha_desc' => $query->orderBy('word', 'desc'),
            'length_desc' => $query->orderByRaw('LENGTH(word) DESC')->orderBy('word', 'asc'),
            default => $query->orderBy('id', 'desc'),
        };

        $counts = [
            'total' => Profanity::count(),
            'active' => Profanity::where('is_active', true)->count(),
            'tagalog' => Profanity::where('category', 'tagalog')->count(),
            'english' => Profanity::where('category', 'english')->count(),
            'custom' => Profanity::where('category', 'custom')->count(),
            'single' => Profanity::where('word', 'not like', '% %')->count(),
            'phrase' => Profanity::where('word', 'like', '% %')->count(),
        ];

        return [
            'profanities' => $query->paginate($this->perPage),
            'counts' => $counts,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl" level="1" class="font-bold tracking-tight">Profanity Filter Library</flux:heading>
                <flux:badge size="sm" color="zinc" class="font-mono">Live Moderation</flux:badge>
            </div>
            <flux:subheading class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                Manage prohibited and vulgar terms filtered in student and peer evaluations. Updates take effect immediately.
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
            <flux:button variant="outline" size="sm" icon="arrow-path" wire:click="refreshCache" title="Reload active dictionary cache">
                Flush Cache
            </flux:button>
            <flux:button variant="primary" size="sm" icon="plus" wire:click="openAddModal" class="flex-1 sm:flex-none">
                Add Profanity Words
            </flux:button>
        </div>
    </div>

    <!-- Stat Metric Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 w-full">
        <!-- Total Words -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block mb-1">Total Words</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    {{ number_format($counts['total']) }}
                </span>
                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                    {{ $counts['active'] }} active
                </span>
            </div>
        </div>

        <!-- Tagalog Terms -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block mb-1">Tagalog Terms</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    {{ number_format($counts['tagalog']) }}
                </span>
                <flux:badge size="sm" color="amber">PH Dialect</flux:badge>
            </div>
        </div>

        <!-- English Terms -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block mb-1">English Terms</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    {{ number_format($counts['english']) }}
                </span>
                <flux:badge size="sm" color="blue">Global</flux:badge>
            </div>
        </div>

        <!-- Custom / Admin Added -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block mb-1">Custom / Added</span>
            <div class="flex items-baseline justify-between">
                <span class="text-2xl sm:text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    {{ number_format($counts['custom']) }}
                </span>
                <flux:badge size="sm" color="purple">Admin Defined</flux:badge>
            </div>
        </div>
    </div>

    <!-- Live Testing Sandbox Widget -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-100 dark:border-zinc-800/80 pb-3">
            <div class="flex items-center gap-2">
                <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <flux:icon icon="beaker" class="size-4" />
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-900 dark:text-zinc-100">Interactive Filter Sandbox</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Type or paste student feedback to test regex normalization, leetspeak detection, and word stripping in real time.</p>
                </div>
            </div>

            @if($testInput !== '')
                <div>
                    @if($testHasProfanity)
                        <flux:badge color="rose" size="sm" icon="shield-exclamation">
                            Profanity Flagged ({{ count($testMatchedWords) }} words)
                        </flux:badge>
                    @else
                        <flux:badge color="emerald" size="sm" icon="shield-check">
                            Clean Content (Passed)
                        </flux:badge>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Input Area -->
            <div class="flex flex-col gap-2">
                <label class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                    Sample Feedback Input
                </label>
                <flux:textarea 
                    wire:model.live.debounce.250ms="testInput" 
                    rows="3" 
                    placeholder="Type sample text (e.g., 'Sobrang g@go magturo pero mabait si sir')..." 
                    class="font-mono text-xs"
                />
                <div class="flex items-center justify-between text-[11px] text-zinc-400">
                    <span>Supports Taglish, leetspeak (@ → a, 0 → o), and spaces</span>
                    @if($testInput !== '')
                        <button type="button" wire:click="$set('testInput', '')" class="text-xs text-rose-500 hover:underline">
                            Clear
                        </button>
                    @endif
                </div>
            </div>

            <!-- Real-time Result Sandbox -->
            <div class="flex flex-col gap-2 bg-zinc-50 dark:bg-zinc-800/40 rounded-lg p-3 border border-zinc-200/80 dark:border-zinc-800">
                <label class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 flex items-center justify-between">
                    <span>Sanitized Feedback Preview</span>
                    <span class="text-[11px] font-normal text-zinc-400">What gets saved</span>
                </label>

                <div class="flex-1 min-h-[50px] p-2.5 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 font-mono text-xs text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap break-words">
                    @if($testInput === '')
                        <span class="text-zinc-400 italic">Preview will appear here as you type in the sandbox...</span>
                    @elseif($testCleanedText === '')
                        <span class="text-rose-500 italic">[All content was stripped due to profanity]</span>
                    @else
                        {{ $testCleanedText }}
                    @endif
                </div>

                <!-- Matched Terms Indicator -->
                @if(!empty($testMatchedWords))
                    <div class="flex flex-wrap items-center gap-1.5 pt-1">
                        <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">Detected terms:</span>
                        @foreach($testMatchedWords as $term)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-900/50">
                                {{ $term }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <!-- Search -->
            <div class="flex-1 min-w-[200px]">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search profanity words..." 
                    icon="magnifying-glass" 
                    clearable 
                />
            </div>

            <!-- Category Filter -->
            <div class="w-full sm:w-40">
                <flux:select wire:model.live="categoryFilter" placeholder="All Categories">
                    <flux:select.option value="">All Categories</flux:select.option>
                    <flux:select.option value="tagalog">Tagalog</flux:select.option>
                    <flux:select.option value="english">English</flux:select.option>
                    <flux:select.option value="custom">Custom</flux:select.option>
                </flux:select>
            </div>

            <!-- Status Filter -->
            <div class="w-full sm:w-36">
                <flux:select wire:model.live="statusFilter" placeholder="All Status">
                    <flux:select.option value="">All Status</flux:select.option>
                    <flux:select.option value="active">Active Only</flux:select.option>
                    <flux:select.option value="inactive">Inactive Only</flux:select.option>
                </flux:select>
            </div>

            <!-- Structure Type Filter -->
            <div class="w-full sm:w-36">
                <flux:select wire:model.live="typeFilter" placeholder="All Types">
                    <flux:select.option value="">All Types</flux:select.option>
                    <flux:select.option value="single">Single Words</flux:select.option>
                    <flux:select.option value="phrase">Phrases Only</flux:select.option>
                </flux:select>
            </div>

            <!-- Sort By -->
            <div class="w-full sm:w-44">
                <flux:select wire:model.live="sortBy">
                    <flux:select.option value="latest">Recently Added</flux:select.option>
                    <flux:select.option value="oldest">Oldest First</flux:select.option>
                    <flux:select.option value="alpha_asc">Alphabetical (A-Z)</flux:select.option>
                    <flux:select.option value="alpha_desc">Alphabetical (Z-A)</flux:select.option>
                    <flux:select.option value="length_desc">Longest Phrases</flux:select.option>
                </flux:select>
            </div>

            @if($search !== '' || $categoryFilter !== '' || $statusFilter !== '' || $typeFilter !== '' || $sortBy !== 'latest')
                <flux:button variant="ghost" size="sm" wire:click="clearFilters" icon="x-mark">
                    Reset
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-800/40 text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                        <th class="py-3 px-4">Prohibited Word / Phrase</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Added By / Date</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse($profanities as $item)
                        <tr wire:key="profanity-{{ $item->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                            <!-- Word -->
                            <td class="py-3.5 px-4 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                <div class="flex items-center gap-2">
                                    <span>{{ $item->word }}</span>
                                    @if(str_contains($item->word, ' '))
                                        <flux:badge size="sm" color="zinc">phrase</flux:badge>
                                    @endif
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="py-3.5 px-4">
                                @if($item->category === 'tagalog')
                                    <flux:badge size="sm" color="amber">Tagalog</flux:badge>
                                @elseif($item->category === 'english')
                                    <flux:badge size="sm" color="blue">English</flux:badge>
                                @else
                                    <flux:badge size="sm" color="purple">Custom</flux:badge>
                                @endif
                            </td>

                            <!-- Status Toggle -->
                            <td class="py-3.5 px-4">
                                <button 
                                    type="button" 
                                    wire:click="toggleStatus({{ $item->id }})"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium cursor-pointer transition-opacity hover:opacity-80"
                                    title="Click to toggle active state"
                                >
                                    @if($item->is_active)
                                        <span class="size-2 rounded-full bg-emerald-500"></span>
                                        <span class="text-emerald-700 dark:text-emerald-400">Active</span>
                                    @else
                                        <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-600"></span>
                                        <span class="text-zinc-500 dark:text-zinc-400">Inactive</span>
                                    @endif
                                </button>
                            </td>

                            <!-- Added By & Date -->
                            <td class="py-3.5 px-4 text-xs text-zinc-500 dark:text-zinc-400">
                                <div>{{ $item->creator ? $item->creator->name : 'System Baseline' }}</div>
                                <div class="text-[11px] text-zinc-400 dark:text-zinc-500">{{ $item->created_at?->format('M d, Y') }}</div>
                            </td>

                            <!-- Actions -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center justify-end gap-1">
                                    <flux:button 
                                        variant="ghost" 
                                        size="xs" 
                                        icon="pencil-square" 
                                        wire:click="openEditModal({{ $item->id }})"
                                        title="Edit word"
                                    />
                                    <flux:button 
                                        variant="ghost" 
                                        size="xs" 
                                        icon="trash" 
                                        wire:click="confirmDelete({{ $item->id }})"
                                        class="text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                        title="Delete word"
                                    />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-zinc-400 dark:text-zinc-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon icon="shield-check" class="size-10 text-zinc-300 dark:text-zinc-700" />
                                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">No profanity words found</p>
                                    <p class="text-xs text-zinc-400">Try adjusting your search criteria or add new prohibited terms.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($profanities->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-800">
                {{ $profanities->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Bulk Add Modal -->
    <flux:modal wire:model="showAddModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Add Profanity Words</flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Register new curse words, insults, or harassment terms into the dynamic filter library.
                </flux:subheading>
            </div>

            <!-- Tab Switcher -->
            <div class="flex border-b border-zinc-200 dark:border-zinc-700 text-xs font-semibold">
                <button 
                    type="button" 
                    wire:click="$set('addTab', 'single')"
                    class="py-2 px-4 border-b-2 transition-colors {{ $addTab === 'single' ? 'border-primary-600 text-primary-600 dark:text-primary-400 dark:border-primary-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}"
                >
                    Single Term
                </button>
                <button 
                    type="button" 
                    wire:click="$set('addTab', 'bulk')"
                    class="py-2 px-4 border-b-2 transition-colors {{ $addTab === 'bulk' ? 'border-primary-600 text-primary-600 dark:text-primary-400 dark:border-primary-400 font-bold' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}"
                >
                    Bulk Import
                </button>
            </div>

            <!-- Single Term Tab Form -->
            @if($addTab === 'single')
                <form wire:submit="createSingle" class="space-y-4">
                    <flux:input 
                        wire:model="newWord" 
                        label="Prohibited Word or Multi-word Phrase" 
                        placeholder="e.g. kupal or pak yu" 
                        required 
                        autofocus
                    />

                    <flux:select wire:model="newCategory" label="Language Category" required>
                        <flux:select.option value="custom">Custom / Campus Specific</flux:select.option>
                        <flux:select.option value="tagalog">Tagalog Dialect</flux:select.option>
                        <flux:select.option value="english">English Language</flux:select.option>
                    </flux:select>

                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            type="checkbox" 
                            id="newIsActive" 
                            wire:model="newIsActive" 
                            class="rounded border-zinc-300 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800"
                        />
                        <label for="newIsActive" class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                            Enable word immediately in active evaluation filter
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button size="sm" variant="ghost" wire:click="$set('showAddModal', false)">Cancel</flux:button>
                        <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="createSingle">Add Word</span>
                            <span wire:loading wire:target="createSingle" class="inline-flex items-center gap-1.5">
                                <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                <span>Saving…</span>
                            </span>
                        </flux:button>
                    </div>
                </form>
            @else
                <!-- Bulk Import Tab Form -->
                <form wire:submit="createBulk" class="space-y-4">
                    <div>
                        <flux:textarea 
                            wire:model="bulkWords" 
                            label="Words or Phrases List" 
                            rows="5" 
                            placeholder="Enter words separated by commas or new lines, e.g.:
term1, term2
sample bad phrase
term3" 
                            required 
                        />
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1">
                            Paste terms separated by commas, semicolons, or newlines. Existing duplicates will be automatically skipped.
                        </p>
                    </div>

                    <flux:select wire:model="bulkCategory" label="Assign Language Category" required>
                        <flux:select.option value="custom">Custom / Campus Specific</flux:select.option>
                        <flux:select.option value="tagalog">Tagalog Dialect</flux:select.option>
                        <flux:select.option value="english">English Language</flux:select.option>
                    </flux:select>

                    <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                        <flux:button size="sm" variant="ghost" wire:click="$set('showAddModal', false)">Cancel</flux:button>
                        <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="createBulk">Import Words</span>
                            <span wire:loading wire:target="createBulk" class="inline-flex items-center gap-1.5">
                                <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                                <span>Importing…</span>
                            </span>
                        </flux:button>
                    </div>
                </form>
            @endif
        </div>
    </flux:modal>

    <!-- Edit Word Modal -->
    <flux:modal wire:model="showEditModal" class="w-[calc(100vw-2rem)] sm:w-full max-w-lg !p-4 sm:!p-6">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Edit Prohibited Word</flux:heading>
                <flux:subheading class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    Update word spelling, language category, or active moderation state.
                </flux:subheading>
            </div>

            <form wire:submit="updateProfanity" class="space-y-4">
                <flux:input 
                    wire:model="editWord" 
                    label="Prohibited Word or Phrase" 
                    required 
                />

                <flux:select wire:model="editCategory" label="Language Category" required>
                    <flux:select.option value="custom">Custom / Campus Specific</flux:select.option>
                    <flux:select.option value="tagalog">Tagalog Dialect</flux:select.option>
                    <flux:select.option value="english">English Language</flux:select.option>
                </flux:select>

                <div class="flex items-center gap-2 pt-1">
                    <input 
                        type="checkbox" 
                        id="editIsActive" 
                        wire:model="editIsActive" 
                        class="rounded border-zinc-300 text-indigo-600 shadow-xs focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-800"
                    />
                    <label for="editIsActive" class="text-xs font-medium text-zinc-700 dark:text-zinc-300">
                        Active in real-time evaluation filter
                    </label>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:button size="sm" variant="ghost" wire:click="$set('showEditModal', false)">Cancel</flux:button>
                    <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="updateProfanity">Save Changes</span>
                        <span wire:loading wire:target="updateProfanity" class="inline-flex items-center gap-1.5">
                            <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                            <span>Updating…</span>
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal && $deletingProfanityId)
        <x-confirmation-modal 
            title="Delete Prohibited Word" 
            on-confirm="deleteProfanity" 
            on-cancel="$set('showDeleteModal', false)"
            confirm-text="Delete Term"
            variant="danger"
        >
            Are you sure you want to permanently remove this word from the profanity library? It will no longer be filtered in student evaluations.

            <x-slot:details>
                <div class="flex flex-col gap-2 text-xs">
                    <div>
                        <span class="text-zinc-500 uppercase font-semibold">Flagged Term:</span>
                        <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100 ml-1">{{ $deletingWord }}</span>
                    </div>
                    <div>
                        <span class="text-zinc-500 uppercase font-semibold">Category:</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200 ml-1">{{ ucfirst($deletingCategory) }}</span>
                    </div>
                </div>
            </x-slot:details>
        </x-confirmation-modal>
    @endif
</div>
