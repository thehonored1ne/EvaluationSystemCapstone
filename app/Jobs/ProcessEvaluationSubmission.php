<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationQuestion;
use App\Models\EvaluationSentiment;
use App\Models\EvaluationSummary;
use App\Models\Semester;
use App\Models\User;
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

            // Calculate question-level points using formula:
            // Question Points = (Rating / 5.0) * (Criterion Max Points / Number of Questions in Criterion)
            $rawScore = 0.0;
            $questionIds = array_keys($this->answers);
            $questions = EvaluationQuestion::with('criterion')->whereIn('id', $questionIds)->get();
            $questionsByCriterion = $questions->groupBy('criterion_id');

            foreach ($this->answers as $qId => $rating) {
                $question = $questions->firstWhere('id', $qId);
                if ($question && $question->criterion) {
                    $cMax = (float) $question->criterion->max_points;
                    $qCount = $questionsByCriterion[$question->criterion_id]->count() ?? 1;
                    $qPoints = ($rating / 5.0) * ($cMax / $qCount);
                    $rawScore += $qPoints;
                } else {
                    $rawScore += $rating;
                }
            }
            $rawScore = round($rawScore, 2);

            // Fetch Active Semester & Category Max / Weight
            $semester = Semester::find($this->semesterId);
            $maxScore = $semester ? $semester->getCategoryMaxPoints($this->evaluationType) : 100.0;
            $categoryWeight = $semester ? $semester->getCategoryWeight($this->evaluationType) : 100.0;
            $weightedScore = $maxScore > 0 ? round(($rawScore / $maxScore) * $categoryWeight, 2) : 0.0;

            // Create evaluation
            $evaluation = Evaluation::create([
                'evaluator_id' => $this->evaluatorId,
                'evaluatee_id' => $this->evaluateeId,
                'semester_id' => $this->semesterId,
                'class_id' => $this->classId,
                'evaluation_type' => $this->evaluationType,
                'rating_average' => $ratingAverage,
                'raw_score' => $rawScore,
                'max_score' => $maxScore,
                'weighted_score' => $weightedScore,
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

            // Update evaluation_summaries table for evaluatee
            $evaluateeUser = User::find($this->evaluateeId);
            if ($evaluateeUser && $evaluateeUser->employee_id && $this->semesterId) {
                $empId = $evaluateeUser->employee_id;
                $evaluations = Evaluation::where('evaluatee_id', $this->evaluateeId)
                    ->where('semester_id', $this->semesterId)
                    ->get();

                $byType = $evaluations->groupBy('evaluation_type');

                $studentAvg = isset($byType['student']) || isset($byType['upward_student'])
                    ? ($byType['student'] ?? $byType['upward_student'])->avg('weighted_score')
                    : null;

                $deanAvg = isset($byType['dean'])
                    ? $byType['dean']->avg('weighted_score')
                    : null;

                $phDhAvg = isset($byType['ph_dh']) || isset($byType['downward'])
                    ? ($byType['ph_dh'] ?? $byType['downward'])->avg('weighted_score')
                    : null;

                $peerAvg = isset($byType['peer'])
                    ? $byType['peer']->avg('weighted_score')
                    : null;

                $selfAvg = isset($byType['self'])
                    ? $byType['self']->avg('weighted_score')
                    : null;

                $superiorAvg = isset($byType['superior']) || isset($byType['upward_employee'])
                    ? ($byType['superior'] ?? $byType['upward_employee'])->avg('weighted_score')
                    : null;

                $scores = array_filter([$studentAvg, $deanAvg, $phDhAvg, $peerAvg, $selfAvg, $superiorAvg], fn ($v) => ! is_null($v));
                $overallRating = ! empty($scores) ? array_sum($scores) : 0.0;

                EvaluationSummary::updateOrCreate(
                    [
                        'evaluatee_id' => $empId,
                        'semester_id' => $this->semesterId,
                    ],
                    [
                        'student_score' => $studentAvg !== null ? round($studentAvg, 2) : null,
                        'dean_score' => $deanAvg !== null ? round($deanAvg, 2) : null,
                        'ph_dh_score' => $phDhAvg !== null ? round($phDhAvg, 2) : null,
                        'peer_score' => $peerAvg !== null ? round($peerAvg, 2) : null,
                        'self_score' => $selfAvg !== null ? round($selfAvg, 2) : null,
                        'superior_score' => $superiorAvg !== null ? round($superiorAvg, 2) : null,
                        'overall_rating' => round($overallRating, 2),
                        'total_submissions' => $evaluations->count(),
                    ]
                );
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
