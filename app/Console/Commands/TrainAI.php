<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Evaluation;
use App\Models\EvaluationSentiment;
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
        $this->info("Fetching comments from the database...");

        $evaluationsWithComments = Evaluation::whereNotNull('comments')
            ->where('comments', '!=', '')
            ->get();

        $comments = $evaluationsWithComments->pluck('comments')->filter()->values()->toArray();

        $this->info("Sending " . count($comments) . " database comments to Flask API `/train` endpoint...");

        try {
            $response = Http::timeout(60)->post('http://127.0.0.1:5001/train', [
                'comments' => $comments,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $this->info("AI training completed successfully!");
                $this->line("Total samples trained: " . ($result['samples_trained'] ?? 0));
                $this->line("Database samples used: " . ($result['db_samples'] ?? 0));
                $this->line("Seed samples used: " . ($result['seed_samples'] ?? 0));
            } else {
                $this->error("AI training failed: HTTP status " . $response->status());
                $this->error($response->body());
                return 1;
            }
        } catch (\Throwable $e) {
            $this->error("AI training failed: Could not connect to Flask API. Ensure the Flask server is running at http://127.0.0.1:5001.");
            $this->error($e->getMessage());
            return 1;
        }

        // Backfill missing sentiments
        $this->info("Checking for unanalyzed comments to backfill...");
        
        $unanalyzed = Evaluation::whereNotNull('comments')
            ->where('comments', '!=', '')
            ->whereDoesntHave('sentiment')
            ->get();

        if ($unanalyzed->isEmpty()) {
            $this->info("No unanalyzed comments found. Everything is up to date!");
            return 0;
        }

        $this->info("Analyzing " . $unanalyzed->count() . " comments...");
        $bar = $this->output->createProgressBar($unanalyzed->count());
        $bar->start();

        $successCount = 0;
        foreach ($unanalyzed as $evaluation) {
            try {
                $response = Http::timeout(5)->post('http://127.0.0.1:5001/analyze', [
                    'comment' => $evaluation->comments,
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
                // Keep moving, print error later
            }
            $bar->advance();
        }

        $bar->finish();
        $this->line("");
        $this->info("Backfill complete! Analyzed $successCount of " . $unanalyzed->count() . " comments successfully.");

        return 0;
    }
}
