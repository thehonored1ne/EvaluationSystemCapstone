<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationSentiment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessEvaluationSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $evaluatorId;

    public int $evaluateeId;

    public int $semesterId;

    public ?int $classId;

    public string $evaluationType;

    public array $answers; // [question_id => rating]

    public ?string $comments;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $evaluatorId,
        int $evaluateeId,
        int $semesterId,
        ?int $classId,
        string $evaluationType,
        array $answers,
        ?string $comments = null
    ) {
        $this->evaluatorId = $evaluatorId;
        $this->evaluateeId = $evaluateeId;
        $this->semesterId = $semesterId;
        $this->classId = $classId;
        $this->evaluationType = $evaluationType;
        $this->answers = $answers;
        $this->comments = $comments;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            // Idempotency check: verify if this exact evaluation has already been created
            $exists = Evaluation::where([
                'semester_id' => $this->semesterId,
                'evaluator_id' => $this->evaluatorId,
                'evaluatee_id' => $this->evaluateeId,
                'class_id' => $this->classId,
            ])->exists();

            if ($exists) {
                Log::info("Evaluation already exists for evaluator {$this->evaluatorId}, evaluatee {$this->evaluateeId}, class {$this->classId}. Skipping.");

                return;
            }

            // Calculate the rating average (average of all answer ratings)
            $totalQuestions = count($this->answers);
            $ratingAverage = $totalQuestions > 0 ? array_sum($this->answers) / $totalQuestions : 0.00;

            // Create evaluation
            $evaluation = Evaluation::create([
                'evaluator_id' => $this->evaluatorId,
                'evaluatee_id' => $this->evaluateeId,
                'semester_id' => $this->semesterId,
                'class_id' => $this->classId,
                'evaluation_type' => $this->evaluationType,
                'rating_average' => $ratingAverage,
                'comments' => $this->comments,
            ]);

            // Save individual answers
            foreach ($this->answers as $questionId => $rating) {
                EvaluationAnswer::create([
                    'evaluation_id' => $evaluation->id,
                    'question_id' => $questionId,
                    'rating' => $rating,
                ]);
            }

            // Perform AI sentiment analysis on comments if present
            if ($this->comments && trim($this->comments) !== '') {
                try {
                    $apiUrl = config('services.ai.url').'/analyze';
                    $apiKey = config('services.ai.key');

                    $response = Http::timeout(5)
                        ->withHeaders(['X-API-KEY' => $apiKey])
                        ->post($apiUrl, [
                            'comment' => $this->comments,
                            'rating' => $ratingAverage,
                        ]);

                    if ($response->successful()) {
                        $result = $response->json();
                        EvaluationSentiment::create([
                            'evaluation_id' => $evaluation->id,
                            'vader_score' => $result['vader_score'] ?? 0.0,
                            'vader_label' => $result['vader_label'] ?? 'neutral',
                            'dt_label' => $result['dt_label'] ?? 'neutral',
                        ]);
                    } else {
                        Log::warning('AI Sentiment API failed with status '.$response->status().' for evaluation '.$evaluation->id);
                    }
                } catch (\Throwable $e) {
                    Log::error('AI Sentiment API connection error for evaluation '.$evaluation->id.': '.$e->getMessage());
                }
            }
        });
    }
}
