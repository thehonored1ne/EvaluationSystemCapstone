<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-ai-skeleton');
    }

    public string $search = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function setManualLabel(int $evaluationId, string $label)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $sentiment = $evaluation->sentiment;
        
        if (!$sentiment) {
            $sentiment = EvaluationSentiment::create([
                'evaluation_id' => $evaluationId,
                'vader_score' => 0.0,
                'vader_label' => 'neutral',
                'dt_label' => 'neutral',
            ]);
        }

        $newLabel = ($label === 'auto') ? null : $label;
        $sentiment->update([
            'manual_label' => $newLabel
        ]);

        \Flux::toast(
            heading: 'Sentiment Overridden',
            text: 'Comment sentiment has been manually updated.',
            variant: 'success'
        );
    }

    public function retrain()
    {
        try {
            $exitCode = Artisan::call('ai:train');
            
            if ($exitCode === 0) {
                \Flux::toast(
                    heading: 'Model Retrained',
                    text: 'The AI model was successfully retrained and validated.',
                    variant: 'success'
                );
            } else {
                \Flux::toast(
                    heading: 'Retraining Failed',
                    text: 'The training process encountered an error.',
                    variant: 'danger'
                );
            }
        } catch (\Throwable $e) {
            \Flux::toast(
                heading: 'Retraining Error',
                text: $e->getMessage(),
                variant: 'danger'
            );
        }
    }

    public function with(): array
    {
        $query = Evaluation::query()
            ->whereNotNull('comments')
            ->where('comments', '!=', '')
            ->with(['sentiment']);

        if ($this->search) {
            $query->where('comments', 'like', '%' . $this->search . '%');
        }

        $evaluations = $query->latest()->paginate(10);

        // Read cached metrics file
        $metricsPath = storage_path('app/ai_metrics.json');
        $metrics = null;
        if (File::exists($metricsPath)) {
            $metrics = json_decode(File::get($metricsPath), true);
        }

        return [
            'evaluations' => $evaluations,
            'metrics' => $metrics,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header Section -->
    <div class="flex justify-between items-start">
        <div>
            <flux:heading size="xl" level="1">AI Pipeline & Classifier</flux:heading>
            <flux:subheading>Manage Tagalog/English lexicon datasets, override comment predictions, and retrain classifier models.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="beaker" wire:click="retrain" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="retrain">Retrain Classifier</span>
            <span wire:loading wire:target="retrain">Retraining Model...</span>
        </flux:button>
    </div>

    <!-- Metrics and Correction Grid -->
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">
        
        <!-- Left: Model Metrics & Confusion Matrix -->
        <div class="w-full lg:w-[32%] flex flex-col gap-6 shrink-0">
            <flux:card class="p-6 flex flex-col gap-4">
                <flux:heading size="md" class="font-bold">Model Metrics</flux:heading>
                
                @if($metrics)
                    <div class="flex items-center gap-4 py-2">
                        <div class="size-16 rounded-full border-4 border-[#4338ca] dark:border-[#bcb6ec] flex items-center justify-center font-mono font-bold text-lg text-[#4338ca] dark:text-[#bcb6ec] bg-[#eef2ff] dark:bg-indigo-950/30">
                            {{ number_format(($metrics['accuracy'] ?? 0) * 100, 1) }}%
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Validation Accuracy</div>
                            <div class="text-xs text-zinc-500">Evaluated on a 20% validation split.</div>
                        </div>
                    </div>

                    <flux:separator />

                    <!-- Confusion Matrix Grid -->
                    <div>
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mb-2">Confusion Matrix</div>
                        <div class="text-[10px] text-zinc-400 mb-3 uppercase tracking-wider">Rows: Actual | Columns: Predicted</div>
                        
                        @if(isset($metrics['confusion_matrix']))
                            <div class="w-full overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-md">
                                <table class="w-full text-xs text-center border-collapse">
                                    <thead>
                                        <tr class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 font-mono text-[10px] uppercase">
                                            <th class="p-2 border-b border-r border-zinc-200 dark:border-zinc-700 text-left font-bold">Act \ Pred</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-[#035e44] dark:text-[#03dd9f] font-semibold">POS</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-[#843c06] dark:text-[#f7a15e] font-semibold">NEU</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-[#a30f34] dark:text-[#f89bb2] font-semibold">NEG</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900 font-mono">
                                        @foreach(['positive' => 'POS', 'neutral' => 'NEU', 'negative' => 'NEG'] as $actKey => $actLabel)
                                            <tr>
                                                <td class="p-2 font-semibold text-left bg-zinc-50 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700 text-[10px] uppercase">{{ $actLabel }}</td>
                                                @foreach(['positive', 'neutral', 'negative'] as $predKey)
                                                    @php
                                                        $count = $metrics['confusion_matrix'][$actKey][$predKey] ?? 0;
                                                        $isCorrect = $actKey === $predKey;
                                                        $cellClass = 'p-2 text-zinc-700 dark:text-zinc-300';
                                                        if ($isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-[#dffbee] dark:bg-emerald-950/20 text-[#035e44] dark:text-[#03dd9f] font-bold';
                                                        } elseif (!$isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-[#fff1f2] dark:bg-rose-950/20 text-[#a30f34] dark:text-[#f89bb2]';
                                                        }
                                                    @endphp
                                                    <td class="{{ $cellClass }}">
                                                        {{ $count }}
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-xs text-zinc-400 italic">Confusion matrix unavailable.</div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-6 text-zinc-500">
                        <flux:icon name="beaker" class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-xs">No metrics loaded. Click "Retrain Classifier" to generate validation scores.</p>
                    </div>
                @endif
            </flux:card>

        </div>

        <!-- Right: Sentiment Correction Table -->
        <div class="w-full lg:w-[68%] flex flex-col gap-4 min-w-0">
            <flux:card class="p-6 flex flex-col gap-4">
                <div class="flex justify-between items-center">
                    <flux:heading size="md" class="font-bold">Sentiment overrides & feedback</flux:heading>
                    <div class="w-64">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search comments..." size="sm" />
                    </div>
                </div>

                <div class="w-full overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full table-fixed divide-y divide-zinc-200 dark:divide-zinc-700 text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-850">
                            <tr>
                                <th class="w-[75%] px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">Evaluation Review</th>
                                <th class="w-[25%] px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100 text-right">Correct Sentiment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($evaluations as $eval)
                                @php
                                    $sentiment = $eval->sentiment;
                                    $vaderLabel = $sentiment ? $sentiment->vader_label : 'pending';
                                    $dtLabel = $sentiment ? $sentiment->dt_label : 'pending';
                                    $manualLabel = $sentiment ? $sentiment->manual_label : null;
                                    $activeLabel = $sentiment ? $sentiment->active_label : 'pending';
                                @endphp
                                <tr wire:key="eval-{{ $eval->id }}">

                                    <!-- Comment and Rating -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <flux:badge size="sm" color="zinc" class="font-mono">{{ number_format($eval->rating_average, 2) }}★</flux:badge>
                                            
                                            <!-- Sentiment labels indicator -->
                                            <div class="flex gap-1 items-center">
                                                <span class="text-[9px] uppercase font-semibold text-zinc-400">AI:</span>
                                                @if($dtLabel === 'positive')
                                                    <flux:badge size="sm" color="emerald" inset="top" class="text-[9px] uppercase px-1 py-0 font-bold">POS</flux:badge>
                                                @elseif($dtLabel === 'negative')
                                                    <flux:badge size="sm" color="rose" inset="top" class="text-[9px] uppercase px-1 py-0 font-bold">NEG</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="zinc" inset="top" class="text-[9px] uppercase px-1 py-0 font-bold">NEU</flux:badge>
                                                @endif

                                                @if($manualLabel)
                                                    <flux:badge size="sm" color="indigo" class="text-[9px] uppercase px-1 py-0 font-mono font-bold tracking-wide">Overridden</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="text-xs text-zinc-600 dark:text-zinc-350 italic leading-relaxed">
                                            "{{ $eval->comments }}"
                                        </p>
                                    </td>

                                    <!-- Override Sentiment selector -->
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-block w-full max-w-[140px]">
                                            <select 
                                                wire:change="setManualLabel({{ $eval->id }}, $event.target.value)"
                                                class="w-full text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 p-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                            >
                                                <option value="auto" @if(is_null($manualLabel)) selected @endif>Auto (Default)</option>
                                                <option value="positive" @if($manualLabel === 'positive') selected @endif>Positive</option>
                                                <option value="neutral" @if($manualLabel === 'neutral') selected @endif>Neutral</option>
                                                <option value="negative" @if($manualLabel === 'negative') selected @endif>Negative</option>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        No evaluations with comments found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $evaluations->links() }}
                </div>
            </flux:card>
        </div>

    </div>
</div>
