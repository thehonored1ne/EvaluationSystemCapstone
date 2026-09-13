<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class BenchmarkAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:benchmark 
                            {file? : Path to the benchmark CSV file (defaults to storage/app/benchmark_template.csv)}
                            {--json : Output metrics in JSON format for academic documentation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate AI pipeline accuracy against an unseen Gold-Standard benchmark test dataset';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! $filePath) {
            $filePath = storage_path('app/benchmark_template.csv');
        }

        if (! File::exists($filePath)) {
            $this->error("Benchmark file not found at: {$filePath}");
            $this->line('You can create a CSV with columns: comment, rating, ground_truth');

            return 1;
        }

        $this->info("Loading benchmark dataset from: {$filePath}");

        // Parse CSV
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);

        if (! $headers) {
            $this->error('The provided CSV file is empty.');
            fclose($handle);

            return 1;
        }

        // Normalize header names
        $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);
        $commentIdx = array_search('comment', $headers);
        $ratingIdx = array_search('rating', $headers);
        $truthIdx = array_search('ground_truth', $headers);

        if ($commentIdx === false || $truthIdx === false) {
            $this->error("CSV must contain at least 'comment' and 'ground_truth' columns.");
            fclose($handle);

            return 1;
        }

        $samples = [];
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $comment = trim($row[$commentIdx] ?? '');
            $truth = strtolower(trim($row[$truthIdx] ?? ''));
            $rating = ($ratingIdx !== false && isset($row[$ratingIdx])) ? (float) $row[$ratingIdx] : 3.0;

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

        $totalSamples = count($samples);
        if ($totalSamples === 0) {
            $this->error('No valid benchmark rows found in CSV.');

            return 1;
        }

        $this->info("Evaluating {$totalSamples} benchmark samples against Flask AI `/analyze` endpoint...");

        // Send to Flask AI /analyze endpoint
        try {
            $apiUrl = config('services.ai.url').'/analyze';
            $apiKey = config('services.ai.key');

            $payload = [
                'comments' => array_column($samples, 'comment'),
                'ratings' => array_column($samples, 'rating'),
            ];

            $response = Http::timeout(60)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post($apiUrl, $payload);

            if (! $response->successful()) {
                $this->error('Flask API request failed with status: '.$response->status());
                $this->error($response->body());

                return 1;
            }

            $results = $response->json('results') ?? [];
        } catch (\Throwable $e) {
            $this->error('Could not connect to Flask AI: '.$e->getMessage());

            return 1;
        }

        if (count($results) !== $totalSamples) {
            $this->error('Result count mismatch from AI service.');

            return 1;
        }

        // Metrics Calculation
        $classes = ['positive', 'neutral', 'negative'];
        $confusion = [];
        foreach ($classes as $actual) {
            foreach ($classes as $pred) {
                $confusion[$actual][$pred] = 0;
            }
        }

        $correctCount = 0;
        $misclassified = [];

        foreach ($samples as $i => $sample) {
            $actual = $sample['ground_truth'];
            $pred = $results[$i]['dt_label'] ?? 'neutral';
            $vader = $results[$i]['vader_label'] ?? 'neutral';
            $confidence = $results[$i]['confidence'] ?? 'unknown';

            if (isset($confusion[$actual][$pred])) {
                $confusion[$actual][$pred]++;
            }

            if ($actual === $pred) {
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
        }

        $overallAccuracy = ($totalSamples > 0) ? ($correctCount / $totalSamples) * 100 : 0;

        // Calculate Precision, Recall, F1 for each class
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

        // Output Report
        $this->newLine();
        $this->line('================================================================');
        $this->info("   AI PIPELINE BENCHMARK REPORT (Total: {$totalSamples} Unseen Samples)");
        $this->line('================================================================');
        $this->line(sprintf(' Overall Accuracy : <fg=green;options=bold>%.2f%%</> (%d/%d)', $overallAccuracy, $correctCount, $totalSamples));
        $this->line(sprintf(' Macro F1-Score   : <fg=cyan;options=bold>%.2f%%</>', $macroF1));
        $this->newLine();

        $tableRows = [];
        foreach ($classes as $c) {
            $m = $classMetrics[$c];
            $tableRows[] = [
                strtoupper($c),
                sprintf('%.2f%%', $m['precision']),
                sprintf('%.2f%%', $m['recall']),
                sprintf('%.2f%%', $m['f1']),
                $m['support'],
            ];
        }

        $this->table(['Class', 'Precision', 'Recall', 'F1-Score', 'Support'], $tableRows);

        $this->newLine();
        $this->line('----------------------------------------------------------------');
        $this->line('   CONFUSION MATRIX (Rows: Ground Truth | Columns: AI Predicted)');
        $this->line('----------------------------------------------------------------');

        $matrixRows = [
            ['POS (Actual)', $confusion['positive']['positive'], $confusion['positive']['neutral'], $confusion['positive']['negative']],
            ['NEU (Actual)', $confusion['neutral']['positive'], $confusion['neutral']['neutral'], $confusion['neutral']['negative']],
            ['NEG (Actual)', $confusion['negative']['positive'], $confusion['negative']['neutral'], $confusion['negative']['negative']],
        ];
        $this->table(['Actual \ Pred', 'POS (Pred)', 'NEU (Pred)', 'NEG (Pred)'], $matrixRows);

        // Print Misclassifications
        if (! empty($misclassified)) {
            $this->newLine();
            $this->warn(sprintf('   MISCLASSIFIED SAMPLES (%d)', count($misclassified)));
            $misRows = [];
            foreach ($misclassified as $m) {
                $misRows[] = [
                    mb_strimwidth($m['comment'], 0, 45, '...'),
                    number_format($m['rating'], 1).'★',
                    strtoupper($m['actual']),
                    strtoupper($m['predicted']),
                    strtoupper($m['vader']),
                    $m['confidence'],
                ];
            }
            $this->table(['Comment', 'Rating', 'Actual', 'Predicted', 'VADER', 'Confidence'], $misRows);
        } else {
            $this->info('Zero misclassifications found on this benchmark.');
        }

        // Optional JSON output for Thesis Appendix
        if ($this->option('json')) {
            $jsonPayload = [
                'total_samples' => $totalSamples,
                'overall_accuracy' => $overallAccuracy,
                'macro_f1' => $macroF1,
                'class_metrics' => $classMetrics,
                'confusion_matrix' => $confusion,
                'misclassified' => $misclassified,
                'evaluated_at' => now()->toIso8601String(),
            ];
            File::put(storage_path('app/benchmark_results.json'), json_encode($jsonPayload, JSON_PRETTY_PRINT));
            $this->line('Saved JSON report to storage/app/benchmark_results.json');
        }

        return 0;
    }
}
