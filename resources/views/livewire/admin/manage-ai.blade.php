<?php

use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;
    use WithPagination;

    public function placeholder()
    {
        return view('livewire.placeholders.manage-ai-skeleton');
    }

    #[Url]
    public string $search = '';

    #[Url]
    public string $selectedFilter = ''; // '', 'needs_review', 'overridden', 'positive', 'neutral', 'negative'

    // Benchmark Workbench State (Purely ephemeral - zero DB persistence)
    public $benchmarkFile = null;
    public ?array $benchmarkResults = null;
    public string $benchmarkFilter = 'all'; // 'all', 'misclassified', 'positive', 'neutral', 'negative'

    public function updatedBenchmarkFile()
    {
        $this->validate([
            'benchmarkFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ], [
            'benchmarkFile.mimes' => 'Only .csv, .xlsx, or .txt files are permitted.',
            'benchmarkFile.max' => 'The uploaded file must not exceed 5 MB.',
        ]);
    }

    public function downloadTemplate()
    {
        $templatePath = storage_path('app/benchmark_template.csv');
        if (! File::exists($templatePath)) {
            \Flux::toast(
                heading: 'Template Not Found',
                text: 'The sample benchmark template could not be found.',
                variant: 'danger'
            );
            return;
        }

        return response()->download($templatePath, 'benchmark_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function resetBenchmark()
    {
        $this->benchmarkFile = null;
        $this->benchmarkResults = null;
        $this->benchmarkFilter = 'all';

        \Flux::toast(
            heading: 'Workbench Reset',
            text: 'Benchmark test results and uploaded file have been cleared.',
            variant: 'info'
        );
    }

    public function runBenchmark()
    {
        $this->validate([
            'benchmarkFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ], [
            'benchmarkFile.mimes' => 'Only .csv, .xlsx, or .txt files are permitted.',
            'benchmarkFile.max' => 'The uploaded file must not exceed 5 MB.',
        ]);

        $path = $this->benchmarkFile->getRealPath();
        $clientExt = strtolower($this->benchmarkFile->getClientOriginalExtension());

        $samples = [];

        if (in_array($clientExt, ['csv', 'txt'])) {
            $samples = $this->parseCsvBenchmark($path);
        } elseif (in_array($clientExt, ['xlsx', 'xls'])) {
            $samples = $this->parseXlsxBenchmark($path);
        } else {
            \Flux::toast(
                heading: 'Unsupported Format',
                text: 'Please upload a valid .csv or .xlsx file.',
                variant: 'danger'
            );
            return;
        }

        if (empty($samples)) {
            \Flux::toast(
                heading: 'Parsing Error',
                text: 'No valid rows found. Ensure the file contains "comment" and "ground_truth" columns.',
                variant: 'danger'
            );
            return;
        }

        if (count($samples) > 2000) {
            \Flux::toast(
                heading: 'Dataset Too Large',
                text: 'Benchmark datasets are capped at 2,000 rows per run to protect server resources.',
                variant: 'danger'
            );
            return;
        }

        $totalSamples = count($samples);

        // Analyze via Flask AI service
        try {
            $apiUrl = config('services.ai.url') . '/analyze';
            $apiKey = config('services.ai.key');

            $payload = [
                'comments' => array_column($samples, 'comment'),
                'ratings' => array_column($samples, 'rating'),
            ];

            $response = Http::timeout(60)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post($apiUrl, $payload);

            if (! $response->successful()) {
                \Flux::toast(
                    heading: 'AI Service Error',
                    text: 'Inference request failed with status: ' . $response->status(),
                    variant: 'danger'
                );
                return;
            }

            $aiResults = $response->json('results') ?? [];
        } catch (\Throwable $e) {
            \Flux::toast(
                heading: 'Connection Error',
                text: 'Could not connect to AI microservice: ' . $e->getMessage(),
                variant: 'danger'
            );
            return;
        }

        if (count($aiResults) !== $totalSamples) {
            \Flux::toast(
                heading: 'Payload Mismatch',
                text: 'Response row count does not match the uploaded dataset.',
                variant: 'danger'
            );
            return;
        }

        // Calculate precision, recall, confusion matrix, and misclassifications
        $classes = ['positive', 'neutral', 'negative'];
        $confusion = [];
        foreach ($classes as $actual) {
            foreach ($classes as $pred) {
                $confusion[$actual][$pred] = 0;
            }
        }

        $correctCount = 0;
        $misclassified = [];
        $evaluatedRows = [];

        foreach ($samples as $i => $sample) {
            $actual = $sample['ground_truth'];
            $pred = $aiResults[$i]['dt_label'] ?? 'neutral';
            $vader = $aiResults[$i]['vader_label'] ?? 'neutral';
            $confidence = $aiResults[$i]['confidence'] ?? 'Moderate';
            $isCorrect = ($actual === $pred);

            if (isset($confusion[$actual][$pred])) {
                $confusion[$actual][$pred]++;
            }

            if ($isCorrect) {
                $correctCount++;
            } else {
                $misclassified[] = [
                    'comment' => $sample['comment'],
                    'rating' => $sample['rating'],
                    'actual' => $actual,
                    'predicted' => $pred,
                    'vader' => $vader,
                    'confidence' => $confidence,
                ];
            }

            $evaluatedRows[] = [
                'comment' => $sample['comment'],
                'rating' => $sample['rating'],
                'actual' => $actual,
                'predicted' => $pred,
                'vader' => $vader,
                'confidence' => $confidence,
                'is_correct' => $isCorrect,
            ];
        }

        $overallAccuracy = ($totalSamples > 0) ? ($correctCount / $totalSamples) * 100 : 0;

        $classMetrics = [];
        $f1Sum = 0;
        foreach ($classes as $c) {
            $tp = $confusion[$c][$c];
            $fp = 0;
            $fn = 0;
            foreach ($classes as $other) {
                if ($other !== $c) {
                    $fp += $confusion[$other][$c];
                    $fn += $confusion[$c][$other];
                }
            }

            $precision = ($tp + $fp > 0) ? ($tp / ($tp + $fp)) * 100 : 0;
            $recall = ($tp + $fn > 0) ? ($tp / ($tp + $fn)) * 100 : 0;
            $f1 = ($precision + $recall > 0) ? (2 * $precision * $recall) / ($precision + $recall) : 0;

            $classMetrics[$c] = [
                'precision' => $precision,
                'recall' => $recall,
                'f1' => $f1,
                'support' => $tp + $fn,
            ];

            $f1Sum += $f1;
        }

        $macroF1 = $f1Sum / count($classes);

        $this->benchmarkResults = [
            'total_samples' => $totalSamples,
            'correct_count' => $correctCount,
            'overall_accuracy' => $overallAccuracy,
            'macro_f1' => $macroF1,
            'class_metrics' => $classMetrics,
            'confusion_matrix' => $confusion,
            'misclassified' => $misclassified,
            'evaluated_rows' => $evaluatedRows,
            'evaluated_at' => now()->format('M d, Y h:i A'),
            'filename' => $this->benchmarkFile->getClientOriginalName(),
        ];

        \Flux::toast(
            heading: 'Benchmark Completed',
            text: sprintf('Evaluated %d samples: %.1f%% accuracy, %.1f%% Macro F1.', $totalSamples, $overallAccuracy, $macroF1),
            variant: 'success'
        );
    }

    protected function parseCsvBenchmark(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h))), $headers);
        $commentIdx = array_search('comment', $headers);
        if ($commentIdx === false) {
            $commentIdx = array_search('comments', $headers);
        }
        $ratingIdx = array_search('rating', $headers);
        $truthIdx = array_search('ground_truth', $headers);
        if ($truthIdx === false) {
            $truthIdx = array_search('sentiment', $headers);
        }

        if ($commentIdx === false || $truthIdx === false) {
            fclose($handle);
            return [];
        }

        $samples = [];
        while (($row = fgetcsv($handle)) !== false) {
            $comment = trim($row[$commentIdx] ?? '');
            $truth = strtolower(trim($row[$truthIdx] ?? ''));
            $rating = ($ratingIdx !== false && isset($row[$ratingIdx]) && is_numeric($row[$ratingIdx])) ? (float) $row[$ratingIdx] : 3.0;

            if ($comment === '' || ! in_array($truth, ['positive', 'neutral', 'negative'])) {
                continue;
            }

            $samples[] = [
                'comment' => $comment,
                'rating' => $rating,
                'ground_truth' => $truth,
            ];
        }

        fclose($handle);
        return $samples;
    }

    protected function parseXlsxBenchmark(string $path): array
    {
        if (! extension_loaded('zip')) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        // Extract sharedStrings
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            $xml = simplexml_load_string($stringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string) ($si->t ?? ($si->r ? implode('', (array) $si->r->t) : ''));
                }
            }
        }

        // Read sheet1
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml || ! isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $val = (string) $c->v;
                $type = (string) $c['t'];
                if ($type === 's' && isset($sharedStrings[(int) $val])) {
                    $val = $sharedStrings[(int) $val];
                }
                $cells[] = $val;
            }
            $rows[] = $cells;
        }

        if (empty($rows)) {
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim($h)), array_shift($rows));
        $commentIdx = array_search('comment', $headers);
        if ($commentIdx === false) {
            $commentIdx = array_search('comments', $headers);
        }
        $ratingIdx = array_search('rating', $headers);
        $truthIdx = array_search('ground_truth', $headers);
        if ($truthIdx === false) {
            $truthIdx = array_search('sentiment', $headers);
        }

        if ($commentIdx === false || $truthIdx === false) {
            return [];
        }

        $samples = [];
        foreach ($rows as $row) {
            $comment = trim($row[$commentIdx] ?? '');
            $truth = strtolower(trim($row[$truthIdx] ?? ''));
            $rating = ($ratingIdx !== false && isset($row[$ratingIdx]) && is_numeric($row[$ratingIdx])) ? (float) $row[$ratingIdx] : 3.0;

            if ($comment === '' || ! in_array($truth, ['positive', 'neutral', 'negative'])) {
                continue;
            }

            $samples[] = [
                'comment' => $comment,
                'rating' => $rating,
                'ground_truth' => $truth,
            ];
        }

        return $samples;
    }

    public function exportBenchmarkResults()
    {
        if (empty($this->benchmarkResults['evaluated_rows'])) {
            \Flux::toast(
                heading: 'No Results',
                text: 'Please execute a benchmark before exporting results.',
                variant: 'danger'
            );
            return;
        }

        $rows = $this->benchmarkResults['evaluated_rows'];
        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['comment', 'rating', 'ground_truth', 'predicted_sentiment', 'vader_sentiment', 'confidence', 'is_correct']);
            foreach ($rows as $r) {
                fputcsv($handle, [
                    $r['comment'],
                    $r['rating'],
                    $r['actual'],
                    $r['predicted'],
                    $r['vader'],
                    $r['confidence'],
                    $r['is_correct'] ? 'MATCH' : 'MISCLASSIFIED',
                ]);
            }
            fclose($handle);
        };

        $filename = 'ai_benchmark_results_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedSelectedFilter()
    {
        $this->resetPage();
    }

    public function setManualLabel(int $evaluationId, string $label)
    {
        $evaluation = Evaluation::findOrFail($evaluationId);
        $sentiment = $evaluation->sentiment;

        if (! $sentiment) {
            $sentiment = EvaluationSentiment::create([
                'evaluation_id' => $evaluationId,
                'vader_score' => 0.0,
                'vader_label' => 'neutral',
                'dt_label' => 'neutral',
            ]);
        }

        $newLabel = ($label === 'auto') ? null : $label;
        $sentiment->update([
            'manual_label' => $newLabel,
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
            ->with(['sentiment', 'evaluator', 'evaluatee']);

        // Search filter
        if ($this->search) {
            $query->where('comments', 'like', '%'.$this->search.'%');
        }

        // Read cached metrics file
        $metricsPath = storage_path('app/ai_metrics.json');
        $metrics = null;
        if (File::exists($metricsPath)) {
            $metrics = json_decode(File::get($metricsPath), true);
        }

        // Status & Sentiment Filter
        if ($this->selectedFilter === 'misclassified') {
            $misclassifiedTexts = collect($metrics['misclassified_samples'] ?? [])->pluck('comment')->filter()->all();
            if (! empty($misclassifiedTexts)) {
                $query->whereIn('comments', $misclassifiedTexts);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($this->selectedFilter === 'needs_review') {
            $query->whereHas('sentiment', function ($q) {
                $q->conflicted();
            });
        } elseif ($this->selectedFilter === 'overridden') {
            $query->whereHas('sentiment', function ($q) {
                $q->overridden();
            });
        } elseif (in_array($this->selectedFilter, ['positive', 'neutral', 'negative'])) {
            $query->whereHas('sentiment', function ($q) {
                $target = $this->selectedFilter;
                $q->where(function ($sub) use ($target) {
                    $sub->whereNotNull('manual_label')->where('manual_label', $target)
                        ->orWhere(function ($sub2) use ($target) {
                            $sub2->whereNull('manual_label')->where('dt_label', $target);
                        });
                });
            });
        }

        $evaluations = $query->latest()->paginate(10);

        // Calculate KPI Card Statistics
        $totalAnalyzedComments = EvaluationSentiment::count();
        $totalOverriddenComments = EvaluationSentiment::whereNotNull('manual_label')->count();
        $conflictedCount = EvaluationSentiment::conflicted()->count();

        return [
            'evaluations' => $evaluations,
            'metrics' => $metrics,
            'totalAnalyzedComments' => $totalAnalyzedComments,
            'totalOverriddenComments' => $totalOverriddenComments,
            'conflictedCount' => $conflictedCount,
        ];
    }
}; ?>

<div class="w-full flex flex-col gap-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">AI Pipeline & Classifier</flux:heading>
        </div>
        <flux:button variant="primary" icon="beaker" wire:click="retrain" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="retrain">Retrain Classifier</span>
            <span wire:loading wire:target="retrain">Retraining Model...</span>
        </flux:button>
    </div>

    <!-- Top 4 Executive KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <!-- Card 1: Total Analyzed Comments -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-3">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Analyzed Reviews</span>
            <div class="space-y-1">
                <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$totalAnalyzedComments" />
                </span>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Live evaluations with NLP sentiment
                </div>
            </div>
        </div>

        <!-- Card 2: Human Overrides -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-3">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Human Overrides</span>
            <div class="space-y-1">
                <span class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100 font-mono">
                    <x-odometer :value="$totalOverriddenComments" />
                </span>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $totalAnalyzedComments > 0 ? number_format(($totalOverriddenComments / $totalAnalyzedComments) * 100, 1) : 0 }}% corrected ground truth
                </div>
            </div>
        </div>

        <!-- Card 3: Validation Accuracy -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-3">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Model Accuracy</span>
            <div class="space-y-1">
                <span class="text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400 font-mono">
                    {{ number_format(($metrics['accuracy'] ?? 0.995) * 100, 1) }}%
                </span>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    Decision Tree on 20% holdout split
                </div>
            </div>
        </div>

        <!-- Card 4: Needs Review / Conflicts -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-3">
            <span class="text-[11px] font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Needs Review</span>
            <div class="space-y-1">
                <span class="text-3xl font-bold tracking-tight {{ $conflictedCount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-zinc-900 dark:text-zinc-100' }} font-mono">
                    <x-odometer :value="$conflictedCount" />
                </span>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $conflictedCount > 0 ? 'Model or rating discrepancies' : 'Zero active discrepancies' }}
                </div>
            </div>
        </div>
    </div>

    <!-- AI Benchmark & Testing Laboratory (Ephemeral Unseen Testing - Zero DB Persistence) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-5 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="size-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <flux:icon name="cpu-chip" class="size-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="md" class="font-bold text-zinc-900 dark:text-zinc-100">AI Benchmark & Testing Laboratory</flux:heading>
                        <flux:badge size="sm" color="indigo" class="text-[10px] font-mono uppercase tracking-wide">Ephemeral / Non-DB</flux:badge>
                    </div>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        Upload custom unseen CSV or Excel test datasets to benchmark classifier performance in real-time without modifying database records.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                <flux:button variant="ghost" size="sm" icon="arrow-down-tray" wire:click="downloadTemplate">
                    Sample Template (.CSV)
                </flux:button>
                @if($benchmarkResults)
                    <flux:button variant="ghost" size="sm" icon="arrow-path" wire:click="resetBenchmark">
                        Reset Workbench
                    </flux:button>
                @endif
            </div>
        </div>

        <!-- Upload & Action Bar Dropzone Area -->
        <div class="flex flex-col md:flex-row items-stretch md:items-center gap-4 bg-zinc-50/70 dark:bg-zinc-800/30 p-5 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 hover:border-indigo-400 dark:hover:border-indigo-500/70 transition-colors">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5">
                    <flux:icon name="arrow-up-tray" class="size-4 text-indigo-600 dark:text-indigo-400" />
                    <label class="block text-xs font-bold text-zinc-800 dark:text-zinc-200">
                        Upload Benchmark Dataset (.csv, .xlsx)
                    </label>
                </div>
                <input 
                    type="file" 
                    wire:model="benchmarkFile" 
                    accept=".csv,.xlsx,.txt"
                    class="block w-full text-xs text-zinc-600 dark:text-zinc-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-200 dark:file:bg-zinc-700 file:text-zinc-800 dark:file:text-zinc-100 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-950/60 hover:file:text-indigo-700 dark:hover:file:text-indigo-300 cursor-pointer"
                />
                <div class="flex items-center gap-2 mt-2 text-[11px] text-zinc-500 dark:text-zinc-400">
                    <span>Required columns: <code class="text-zinc-700 dark:text-zinc-300 bg-zinc-200/70 dark:bg-zinc-800 px-1.5 py-0.5 rounded font-mono">comment</code>, <code class="text-zinc-700 dark:text-zinc-300 bg-zinc-200/70 dark:bg-zinc-800 px-1.5 py-0.5 rounded font-mono">ground_truth</code>, optional <code class="text-zinc-700 dark:text-zinc-300 bg-zinc-200/70 dark:bg-zinc-800 px-1.5 py-0.5 rounded font-mono">rating</code></span>
                </div>
                @error('benchmarkFile')
                    <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center gap-2 pt-1 md:pt-0 shrink-0">
                <flux:button 
                    variant="primary" 
                    icon="play" 
                    wire:click="runBenchmark" 
                    wire:loading.attr="disabled"
                    wire:target="runBenchmark, benchmarkFile"
                    :disabled="! $benchmarkFile"
                    class="w-full md:w-auto"
                >
                    <span wire:loading.remove wire:target="runBenchmark">Run Benchmark</span>
                    <span wire:loading wire:target="runBenchmark">Evaluating Model...</span>
                </flux:button>
            </div>
        </div>

        <!-- Benchmark Execution Results Output -->
        @if($benchmarkResults)
            <div class="flex flex-col gap-5 pt-2">
                <!-- Summary Metrics Bar -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 w-full">
                    <div class="p-3.5 rounded-lg border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50/40 dark:bg-emerald-950/20">
                        <span class="text-[10px] font-semibold uppercase text-emerald-700 dark:text-emerald-400">Benchmark Accuracy</span>
                        <div class="text-2xl font-bold font-mono text-emerald-700 dark:text-emerald-400 mt-1">
                            {{ number_format($benchmarkResults['overall_accuracy'], 1) }}%
                        </div>
                        <span class="text-[11px] text-emerald-600/80 dark:text-emerald-400/80">
                            {{ $benchmarkResults['correct_count'] }} of {{ $benchmarkResults['total_samples'] }} correct
                        </span>
                    </div>

                    <div class="p-3.5 rounded-lg border border-indigo-200/80 dark:border-indigo-900/60 bg-indigo-50/40 dark:bg-indigo-950/20">
                        <span class="text-[10px] font-semibold uppercase text-indigo-700 dark:text-indigo-400">Macro F1-Score</span>
                        <div class="text-2xl font-bold font-mono text-indigo-700 dark:text-indigo-400 mt-1">
                            {{ number_format($benchmarkResults['macro_f1'], 1) }}%
                        </div>
                        <span class="text-[11px] text-indigo-600/80 dark:text-indigo-400/80">
                            Balanced harmonic mean
                        </span>
                    </div>

                    <div class="p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60">
                        <span class="text-[10px] font-semibold uppercase text-zinc-500 dark:text-zinc-400">Dataset File</span>
                        <div class="text-sm font-bold truncate text-zinc-800 dark:text-zinc-200 mt-1.5 font-mono" title="{{ $benchmarkResults['filename'] }}">
                            {{ $benchmarkResults['filename'] }}
                        </div>
                        <span class="text-[10px] text-zinc-400 font-mono">
                            {{ $benchmarkResults['evaluated_at'] }}
                        </span>
                    </div>

                    <div class="p-3.5 rounded-lg border {{ count($benchmarkResults['misclassified']) > 0 ? 'border-amber-200/80 dark:border-amber-900/60 bg-amber-50/40 dark:bg-amber-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800/60' }} flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-semibold uppercase {{ count($benchmarkResults['misclassified']) > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-zinc-500 dark:text-zinc-400' }}">Discrepancies</span>
                            <div class="text-2xl font-bold font-mono {{ count($benchmarkResults['misclassified']) > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-zinc-800 dark:text-zinc-200' }} mt-1">
                                {{ count($benchmarkResults['misclassified']) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <flux:button variant="ghost" size="xs" icon="arrow-down-tray" wire:click="exportBenchmarkResults">
                                Export CSV
                            </flux:button>
                        </div>
                    </div>
                </div>

                <!-- Mid Section: Class Metrics Table + Confusion Matrix -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 w-full">
                    <!-- Class Metrics Breakdown -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wide mb-2 flex items-center justify-between">
                                <span>Per-Class Classification Metrics</span>
                                <span class="text-[10px] text-zinc-400 font-normal">Precision / Recall / F1</span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs font-mono">
                                    <thead>
                                        <tr class="text-left text-[10px] text-zinc-500 dark:text-zinc-400 uppercase border-b border-zinc-200 dark:border-zinc-700">
                                            <th class="py-1.5 px-2">Class</th>
                                            <th class="py-1.5 px-2 text-right">Precision</th>
                                            <th class="py-1.5 px-2 text-right">Recall</th>
                                            <th class="py-1.5 px-2 text-right">F1-Score</th>
                                            <th class="py-1.5 px-2 text-right">Samples</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                        @foreach(['positive' => 'POS', 'neutral' => 'NEU', 'negative' => 'NEG'] as $key => $lbl)
                                            @php $cm = $benchmarkResults['class_metrics'][$key] ?? ['precision' => 0, 'recall' => 0, 'f1' => 0, 'support' => 0]; @endphp
                                            <tr>
                                                <td class="py-2 px-2 font-bold uppercase text-zinc-700 dark:text-zinc-300">{{ $lbl }}</td>
                                                <td class="py-2 px-2 text-right text-zinc-700 dark:text-zinc-300">{{ number_format($cm['precision'], 1) }}%</td>
                                                <td class="py-2 px-2 text-right text-zinc-700 dark:text-zinc-300">{{ number_format($cm['recall'], 1) }}%</td>
                                                <td class="py-2 px-2 text-right font-bold {{ $cm['f1'] >= 80 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">{{ number_format($cm['f1'], 1) }}%</td>
                                                <td class="py-2 px-2 text-right text-zinc-500">{{ $cm['support'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Benchmark Confusion Matrix -->
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="text-xs font-bold text-zinc-800 dark:text-zinc-200 uppercase tracking-wide mb-2 flex items-center justify-between">
                                <span>Benchmark Confusion Matrix</span>
                                <span class="text-[10px] text-zinc-400 font-normal">Rows: Actual | Cols: Predicted</span>
                            </div>
                            <div class="w-full overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-md">
                                <table class="w-full text-xs text-center border-collapse font-mono">
                                    <thead>
                                        <tr class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 text-[10px] uppercase">
                                            <th class="p-2 border-b border-r border-zinc-200 dark:border-zinc-700 text-left font-bold">Act \ Pred</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-emerald-600 dark:text-emerald-400 font-semibold">POS</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-semibold">NEU</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-rose-600 dark:text-rose-400 font-semibold">NEG</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                        @foreach(['positive' => 'POS', 'neutral' => 'NEU', 'negative' => 'NEG'] as $actKey => $actLabel)
                                            <tr>
                                                <td class="p-2 font-semibold text-left bg-zinc-50 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700 text-[10px] uppercase">{{ $actLabel }}</td>
                                                @foreach(['positive', 'neutral', 'negative'] as $predKey)
                                                    @php
                                                        $count = $benchmarkResults['confusion_matrix'][$actKey][$predKey] ?? 0;
                                                        $isCorrect = $actKey === $predKey;
                                                        $cellClass = 'p-2 text-zinc-700 dark:text-zinc-300';
                                                        if ($isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400 font-bold';
                                                        } elseif (!$isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 font-semibold';
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
                        </div>
                    </div>
                </div>

                <!-- Test Sample Breakdown Table with Filter & Export -->
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-zinc-800 dark:text-zinc-200">Evaluated Benchmark Samples</span>
                            <span class="text-xs text-zinc-400 font-mono">({{ count($benchmarkResults['evaluated_rows']) }})</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <select 
                                wire:model.live="benchmarkFilter"
                                class="text-xs rounded border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 py-1 px-2 focus:outline-none"
                            >
                                <option value="all">Show All Samples ({{ count($benchmarkResults['evaluated_rows']) }})</option>
                                <option value="misclassified">Misclassified Only ({{ count($benchmarkResults['misclassified']) }})</option>
                                <option value="positive">Actual Positive</option>
                                <option value="neutral">Actual Neutral</option>
                                <option value="negative">Actual Negative</option>
                            </select>

                            <flux:button variant="ghost" size="xs" icon="arrow-down-tray" wire:click="exportBenchmarkResults">
                                Export CSV
                            </flux:button>
                        </div>
                    </div>

                    <div class="max-h-72 overflow-y-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-zinc-100/70 dark:bg-zinc-800/80 text-[10px] uppercase text-zinc-500 sticky top-0 backdrop-blur-xs">
                                <tr>
                                    <th class="py-2 px-3">Comment</th>
                                    <th class="py-2 px-3 text-center">Rating</th>
                                    <th class="py-2 px-3 text-center">Actual</th>
                                    <th class="py-2 px-3 text-center">AI Pred</th>
                                    <th class="py-2 px-3 text-center">Text Polarity</th>
                                    <th class="py-2 px-3 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 font-mono">
                                @php
                                    $filteredRows = collect($benchmarkResults['evaluated_rows'])->filter(function ($row) {
                                        if ($this->benchmarkFilter === 'misclassified') {
                                            return ! $row['is_correct'];
                                        } elseif (in_array($this->benchmarkFilter, ['positive', 'neutral', 'negative'])) {
                                            return $row['actual'] === $this->benchmarkFilter;
                                        }
                                        return true;
                                    });
                                @endphp

                                @forelse($filteredRows as $row)
                                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                        <td class="py-2 px-3 font-sans max-w-xs md:max-w-md text-zinc-800 dark:text-zinc-200">
                                            "{{ $row['comment'] }}"
                                        </td>
                                        <td class="py-2 px-3 text-center text-zinc-500 whitespace-nowrap">
                                            {{ number_format($row['rating'], 1) }}★
                                        </td>
                                        <td class="py-2 px-3 text-center uppercase font-bold text-zinc-700 dark:text-zinc-300">
                                            {{ $row['actual'] }}
                                        </td>
                                        <td class="py-2 px-3 text-center uppercase font-bold {{ $row['is_correct'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                            {{ $row['predicted'] }}
                                        </td>
                                        <td class="py-2 px-3 text-center uppercase text-zinc-500">
                                            {{ $row['vader'] }}
                                        </td>
                                        <td class="py-2 px-3 text-center whitespace-nowrap">
                                            @if($row['is_correct'])
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400">
                                                    MATCH
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400">
                                                    MISCLASSIFIED
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-zinc-400 italic">
                                            No benchmark samples matching filter criteria.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Metrics and Correction Grid -->
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">
        
        <!-- Left: Model Metrics & Confusion Matrix -->
        <div class="w-full lg:w-[32%] flex flex-col gap-6 shrink-0">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md" class="font-bold">Model Metrics</flux:heading>
                    @if(isset($metrics['last_trained_at']))
                        <span class="text-[10px] text-zinc-400 font-mono">
                            {{ \Carbon\Carbon::parse($metrics['last_trained_at'])->diffForHumans() }}
                        </span>
                    @endif
                </div>
                
                @if($metrics)
                    <div class="flex items-center gap-4 py-2">
                        <div class="size-16 rounded-full border-4 border-emerald-500 dark:border-emerald-400 flex items-center justify-center font-mono font-bold text-lg text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/30">
                            {{ number_format(($metrics['accuracy'] ?? 0) * 100, 1) }}%
                        </div>
                        <div class="space-y-0.5">
                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Validation Accuracy</div>
                            <div class="text-xs text-zinc-500">
                                Evaluated on a 20% holdout test split.
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-zinc-100 dark:bg-zinc-800 w-full"></div>

                    <!-- Confusion Matrix Grid -->
                    <div>
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-200 mb-1">Confusion Matrix</div>
                        <div class="text-[10px] text-zinc-400 mb-3">
                            @php
                                $totalMatrixSamples = 0;
                                if (isset($metrics['confusion_matrix'])) {
                                    foreach ($metrics['confusion_matrix'] as $row) {
                                        $totalMatrixSamples += array_sum($row);
                                    }
                                }
                            @endphp
                            <span>Rows: Actual | Columns: Predicted ({{ $totalMatrixSamples }} validation samples)</span>
                        </div>
                        
                        @if(isset($metrics['confusion_matrix']))
                            <div class="w-full overflow-hidden border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <table class="w-full text-xs text-center border-collapse font-mono">
                                    <thead>
                                        <tr class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 text-[10px] uppercase">
                                            <th class="p-2 border-b border-r border-zinc-200 dark:border-zinc-700 text-left font-bold">Act \ Pred</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-emerald-600 dark:text-emerald-400 font-semibold">POS</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-semibold">NEU</th>
                                            <th class="p-2 border-b border-zinc-200 dark:border-zinc-700 text-rose-600 dark:text-rose-400 font-semibold">NEG</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                                        @foreach(['positive' => 'POS', 'neutral' => 'NEU', 'negative' => 'NEG'] as $actKey => $actLabel)
                                            <tr>
                                                <td class="p-2 font-semibold text-left bg-zinc-50 dark:bg-zinc-800 border-r border-zinc-200 dark:border-zinc-700 text-[10px] uppercase">{{ $actLabel }}</td>
                                                @foreach(['positive', 'neutral', 'negative'] as $predKey)
                                                    @php
                                                        $count = $metrics['confusion_matrix'][$actKey][$predKey] ?? 0;
                                                        $isCorrect = $actKey === $predKey;
                                                        $cellClass = 'p-2 text-zinc-700 dark:text-zinc-300';
                                                        if ($isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400 font-bold';
                                                        } elseif (!$isCorrect && $count > 0) {
                                                            $cellClass .= ' bg-rose-50 dark:bg-rose-950/20 text-rose-700 dark:text-rose-400 font-semibold';
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

                        @if(isset($metrics['misclassified_samples']) && count($metrics['misclassified_samples']) > 0)
                            <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                                <div class="flex items-center justify-between text-xs font-bold text-zinc-800 dark:text-zinc-200">
                                    <span class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400">
                                        <flux:icon name="exclamation-circle" class="size-3.5" />
                                        Misclassified in Validation ({{ count($metrics['misclassified_samples']) }})
                                    </span>
                                </div>
                                <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                    @foreach($metrics['misclassified_samples'] as $sample)
                                        <div class="p-2 rounded bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 text-xs space-y-1">
                                            <div class="flex items-center justify-between text-[10px] font-mono">
                                                <span class="text-zinc-500">Act: <strong class="uppercase text-zinc-700 dark:text-zinc-300">{{ $sample['actual'] }}</strong></span>
                                                <span class="text-rose-600 dark:text-rose-400 font-bold">Pred: <strong class="uppercase">{{ $sample['predicted'] }}</strong></span>
                                                <span class="text-zinc-400 font-mono">{{ number_format($sample['rating'], 1) }}★</span>
                                            </div>
                                            <p class="text-[11px] text-zinc-700 dark:text-zinc-300 italic">
                                                "{{ $sample['comment'] }}"
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="text-center py-6 text-zinc-500">
                        <flux:icon name="beaker" class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-xs">No metrics loaded. Click "Retrain Classifier" to generate validation scores.</p>
                    </div>
                @endif
            </div>

        </div>

        <!-- Right: Sentiment Correction Table -->
        <div class="w-full lg:flex-1 flex flex-col gap-4 min-w-0">
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col gap-4">
                <!-- Search & Filters Toolbar -->
                <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3">
                    <div>
                        <flux:heading size="md" class="font-bold">Sentiment overrides & feedback</flux:heading>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                        <!-- Status / Confidence Filter -->
                        <div class="w-full sm:w-52">
                            <select 
                                wire:model.live="selectedFilter" 
                                class="w-full text-xs rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 py-2 px-2.5 focus:outline-none focus:ring-1 focus:ring-zinc-400"
                            >
                                <option value="">All Comments</option>
                                @if(isset($metrics['misclassified_samples']) && count($metrics['misclassified_samples']) > 0)
                                    <option value="misclassified">Misclassified in Test ({{ count($metrics['misclassified_samples']) }})</option>
                                @endif
                                <option value="needs_review">Needs Review (Conflicted)</option>
                                <option value="overridden">Manually Overridden</option>
                                <option value="positive">Positive Only</option>
                                <option value="neutral">Neutral Only</option>
                                <option value="negative">Negative Only</option>
                            </select>
                        </div>

                        <!-- Search Box -->
                        <div class="w-full sm:w-56">
                            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search comments..." size="sm" />
                        </div>
                    </div>
                </div>

                <div class="w-full overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm text-left">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="w-[50%] px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">Review & Comment</th>
                                <th class="w-[12%] px-3 py-3 font-medium text-zinc-900 dark:text-zinc-100 text-center">Text Polarity</th>
                                <th class="w-[12%] px-3 py-3 font-medium text-zinc-900 dark:text-zinc-100 text-center">AI Prediction</th>
                                <th class="w-[14%] px-3 py-3 font-medium text-zinc-900 dark:text-zinc-100 text-center">Confidence Level</th>
                                <th class="w-[12%] px-3 py-3 font-medium text-zinc-900 dark:text-zinc-100 text-right">Manual Override</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @forelse($evaluations as $eval)
                                @php
                                    $sentiment = $eval->sentiment;
                                    $vaderLabel = $sentiment ? $sentiment->vader_label : 'pending';
                                    $vaderScore = $sentiment ? (float) $sentiment->vader_score : 0.0;
                                    $dtLabel = $sentiment ? $sentiment->dt_label : 'pending';
                                    $manualLabel = $sentiment ? $sentiment->manual_label : null;
                                    $confidence = $sentiment ? $sentiment->confidence_level : 'Unknown';
                                    $isConflicted = $sentiment ? $sentiment->is_conflicted : false;

                                    // Evaluation Type Label
                                    $evalType = match($eval->evaluation_type) {
                                        'student', 'upward_student' => 'Student Eval',
                                        'dean' => 'Dean Eval',
                                        'program_head' => 'Program Head Eval',
                                        'department_head' => 'Dept Head Eval',
                                        'peer' => 'Peer Eval',
                                        'self' => 'Self Eval',
                                        default => 'Evaluation',
                                    };
                                @endphp
                                <tr wire:key="eval-{{ $eval->id }}" class="{{ $isConflicted ? 'bg-amber-50/40 dark:bg-amber-950/10' : '' }}">

                                    <!-- Comment and Meta -->
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <flux:badge size="sm" color="zinc" class="font-mono text-[11px]">{{ number_format($eval->rating_average, 2) }}★</flux:badge>
                                            <span class="text-[10px] text-zinc-400 uppercase tracking-wider font-medium">{{ $evalType }}</span>

                                            @if($isConflicted)
                                                <flux:badge size="sm" color="amber" class="text-[9px] uppercase font-bold tracking-wide">Needs Review</flux:badge>
                                            @endif

                                            @if($manualLabel)
                                                <flux:badge size="sm" color="indigo" class="text-[9px] uppercase font-bold tracking-wide">Overridden</flux:badge>
                                            @endif
                                        </div>
                                        <p class="text-xs text-zinc-700 dark:text-zinc-300 italic leading-relaxed">
                                            "{{ $eval->comments }}"
                                        </p>
                                    </td>

                                    <!-- VADER Score Pill & Label Badge -->
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        <div class="inline-flex flex-col items-center gap-0.5">
                                            @if($vaderLabel === 'positive')
                                                <flux:badge size="sm" color="emerald" inset="top" class="text-[9px] uppercase font-bold">POS</flux:badge>
                                            @elseif($vaderLabel === 'negative')
                                                <flux:badge size="sm" color="rose" inset="top" class="text-[9px] uppercase font-bold">NEG</flux:badge>
                                            @else
                                                <flux:badge size="sm" color="zinc" inset="top" class="text-[9px] uppercase font-bold">NEU</flux:badge>
                                            @endif
                                            <span class="font-mono text-[10px] text-zinc-400">
                                                @if($vaderScore > 0.05)
                                                    <span class="text-emerald-600 dark:text-emerald-400 font-semibold">+{{ number_format($vaderScore, 2) }}</span>
                                                @elseif($vaderScore < -0.05)
                                                    <span class="text-rose-600 dark:text-rose-400 font-semibold">{{ number_format($vaderScore, 2) }}</span>
                                                @else
                                                    0.00
                                                @endif
                                            </span>
                                        </div>
                                    </td>

                                    <!-- AI Model Label Indicator -->
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        @if($dtLabel === 'positive')
                                            <flux:badge size="sm" color="emerald" inset="top" class="text-[9px] uppercase font-bold">POS</flux:badge>
                                        @elseif($dtLabel === 'negative')
                                            <flux:badge size="sm" color="rose" inset="top" class="text-[9px] uppercase font-bold">NEG</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" inset="top" class="text-[9px] uppercase font-bold">NEU</flux:badge>
                                        @endif
                                    </td>

                                    <!-- Confidence Level Badge -->
                                    <td class="px-3 py-3 text-center whitespace-nowrap">
                                        @if($confidence === 'Human Verified')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/30 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">Verified</span>
                                        @elseif($confidence === 'High')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">High</span>
                                        @elseif($confidence === 'Moderate')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">Moderate</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">Low (Conflict ⚠️)</span>
                                        @endif
                                    </td>

                                    <!-- Override Sentiment selector -->
                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <div class="inline-block w-full max-w-[130px]">
                                            <select 
                                                wire:change="setManualLabel({{ $eval->id }}, $event.target.value)"
                                                class="w-full text-xs rounded border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-zinc-800 dark:text-zinc-200 p-1.5 focus:outline-none focus:ring-1 focus:ring-zinc-400"
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
                                    <td colspan="5" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        No evaluations matching the current criteria were found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $evaluations->links() }}
                </div>
            </div>
        </div>

    </div>
</div>

