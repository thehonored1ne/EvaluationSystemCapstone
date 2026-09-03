<?php

namespace App\Console\Commands;

use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TrainAI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:train';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Train the Decision Tree classifier using database comments and seed data, then backfill unanalyzed comments.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $this->info('Fetching comments from the database...');

        // 1. High-priority: Fetch all human-corrected sentiments (ground truth)
        $manualSamples = DB::table('evaluations')
            ->join('evaluation_sentiments', 'evaluations.id', '=', 'evaluation_sentiments.evaluation_id')
            ->whereNotNull('evaluation_sentiments.manual_label')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '')
            ->select([
                'evaluations.comments as comment',
                'evaluations.rating_average as rating',
                'evaluation_sentiments.manual_label as manual_label',
            ])
            ->get()
            ->map(fn ($r) => [
                'comment' => (string) $r->comment,
                'rating' => (float) $r->rating,
                'manual_label' => (string) $r->manual_label,
            ])
            ->all();

        // 2. Representative sample: Cap general comments to 2,000 to prevent HTTP memory exhaustion
        $sampleLimit = max(500, 2000 - count($manualSamples));
        $generalSamples = DB::table('evaluations')
            ->leftJoin('evaluation_sentiments', 'evaluations.id', '=', 'evaluation_sentiments.evaluation_id')
            ->whereNull('evaluation_sentiments.manual_label')
            ->whereNotNull('evaluations.comments')
            ->where('evaluations.comments', '!=', '')
            ->orderByDesc('evaluations.id')
            ->limit($sampleLimit)
            ->select([
                'evaluations.comments as comment',
                'evaluations.rating_average as rating',
            ])
            ->get()
            ->map(fn ($r) => [
                'comment' => (string) $r->comment,
                'rating' => (float) $r->rating,
                'manual_label' => null,
            ])
            ->all();

        $samples = array_merge($manualSamples, $generalSamples);

        $this->info('Sending '.count($samples).' database comments to Flask API `/train` endpoint...');

        try {
            $apiUrl = config('services.ai.url').'/train';
            $apiKey = config('services.ai.key');

            $response = Http::timeout(120)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->post($apiUrl, [
                    'samples' => $samples,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                $this->info('AI training completed successfully!');
                $this->line('Total samples trained: '.($result['samples_trained'] ?? 0));
                $this->line('Database samples used: '.($result['db_samples'] ?? 0));
                $this->line('Seed samples used: '.($result['seed_samples'] ?? 0));

                // Save metrics
                if (isset($result['metrics'])) {
                    $metricsDir = storage_path('app');
                    if (! is_dir($metricsDir)) {
                        @mkdir($metricsDir, 0755, true);
                    }
                    @file_put_contents(
                        $metricsDir.'/ai_metrics.json',
                        json_encode($result['metrics'], JSON_PRETTY_PRINT)
                    );
                }
            } else {
                $this->error('AI training failed: HTTP status '.$response->status());
                $this->error($response->body());

                return 1;
            }
        } catch (\Throwable $e) {
            $this->error('AI training failed: Could not connect to Flask API at '.config('services.ai.url'));
            $this->error($e->getMessage());

            return 1;
        }

        // Backfill missing sentiments using chunking to prevent memory bloat
        $this->info('Checking for unanalyzed comments to backfill...');

        $unanalyzedCount = Evaluation::whereNotNull('comments')
            ->where('comments', '!=', '')
            ->whereDoesntHave('sentiment')
            ->count();

        if ($unanalyzedCount === 0) {
            $this->info('No unanalyzed comments found. Everything is up to date!');

            return 0;
        }

        $this->info('Analyzing '.$unanalyzedCount.' comments...');
        $bar = $this->output->createProgressBar($unanalyzedCount);
        $bar->start();

        $successCount = 0;
        $apiUrl = config('services.ai.url').'/analyze';
        $apiKey = config('services.ai.key');

        Evaluation::whereNotNull('comments')
            ->where('comments', '!=', '')
            ->whereDoesntHave('sentiment')
            ->select(['id', 'comments', 'rating_average'])
            ->chunkById(100, function ($evaluations) use ($apiUrl, $apiKey, &$successCount, $bar) {
                foreach ($evaluations as $evaluation) {
                    try {
                        $response = Http::timeout(5)
                            ->withHeaders(['X-API-KEY' => $apiKey])
                            ->post($apiUrl, [
                                'comment' => $evaluation->comments,
                                'rating' => (float) $evaluation->rating_average,
                            ]);

                        if ($response->successful()) {
                            $res = $response->json();
                            EvaluationSentiment::create([
                                'evaluation_id' => $evaluation->id,
                                'vader_score' => $res['vader_score'] ?? 0.0,
                                'vader_label' => $res['vader_label'] ?? 'neutral',
                                'dt_label' => $res['dt_label'] ?? 'neutral',
                            ]);
                            $successCount++;
                        }
                    } catch (\Throwable $e) {
                        // Keep moving
                    }
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->line('');
        $this->info("Backfill complete! Analyzed $successCount of {$unanalyzedCount} comments successfully.");

        return 0;
    }
}
