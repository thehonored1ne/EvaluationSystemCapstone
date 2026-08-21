<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Evaluation extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('evaluation');
    }

    protected $fillable = [
        'evaluator_id',
        'evaluatee_id',
        'semester_id',
        'class_id',
        'evaluation_type',
        'rating_average',
        'raw_score',
        'max_score',
        'weighted_score',
        'comments',
    ];

    protected $casts = [
        'rating_average' => 'float',
        'raw_score' => 'float',
        'max_score' => 'float',
        'weighted_score' => 'float',
    ];

    /**
     * Get the evaluator (User) who submitted this evaluation.
     */
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /**
     * Get the evaluatee (User) who was evaluated.
     */
    public function evaluatee()
    {
        return $this->belongsTo(User::class, 'evaluatee_id');
    }

    /**
     * Get the class being evaluated.
     */
    public function class()
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    /**
     * Get the semester when this evaluation was submitted.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the answers submitted in this evaluation.
     */
    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class);
    }

    /**
     * In-memory per-request cache for evaluation statuses.
     */
    protected static array $statusCache = [];

    public static function flushStatusCache(): void
    {
        self::$statusCache = [];
    }

    /**
     * Get the current status of an evaluation (completed, processing, or pending).
     */
    public static function getStatus(int $evaluatorId, int $evaluateeId, int $semesterId, ?int $classId = null, string $type = 'upward_student'): string
    {
        $cacheKey = "{$evaluatorId}_{$semesterId}";

        if (! isset(self::$statusCache[$cacheKey])) {
            $map = [];

            // 1. Single batch query for all completed evaluations by this evaluator in this semester
            $completed = self::where('evaluator_id', $evaluatorId)
                ->where('semester_id', $semesterId)
                ->select(['evaluatee_id', 'class_id', 'evaluation_type'])
                ->get();

            foreach ($completed as $eval) {
                $cId = $eval->class_id ?? 'null';
                $key = "{$eval->evaluatee_id}_{$cId}_{$eval->evaluation_type}";
                $map[$key] = 'completed';
            }

            // 2. Single batch query for any pending queue jobs for this evaluator
            try {
                $jobs = DB::table('jobs')
                    ->where('queue', 'default')
                    ->where('payload', 'like', '%ProcessEvaluationSubmission%')
                    ->where('payload', 'like', '%evaluatorId%i:'.$evaluatorId.';%')
                    ->where('payload', 'like', '%semesterId%i:'.$semesterId.';%')
                    ->get(['payload']);

                foreach ($jobs as $job) {
                    $payload = $job->payload;
                    if (preg_match('/evaluateeId%i:(\d+);/', $payload, $mEval) &&
                        preg_match('/evaluationType%s:\d+:%"([^"]+)"%|evaluationType%s:\d+:%([^%]+)%/', $payload, $mType)) {
                        $targetEvalId = $mEval[1];
                        $targetType = ! empty($mType[1]) ? $mType[1] : $mType[2];
                        $targetClassId = 'null';
                        if (preg_match('/classId%i:(\d+);/', $payload, $mClass)) {
                            $targetClassId = $mClass[1];
                        }
                        $jobKey = "{$targetEvalId}_{$targetClassId}_{$targetType}";
                        if (! isset($map[$jobKey])) {
                            $map[$jobKey] = 'processing';
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Gracefully ignore if jobs table does not exist
            }

            self::$statusCache[$cacheKey] = $map;
        }

        $lookupClassId = $classId ?? 'null';
        $itemKey = "{$evaluateeId}_{$lookupClassId}_{$type}";

        return self::$statusCache[$cacheKey][$itemKey] ?? 'pending';
    }

    /**
     * Get the sentiment analysis details associated with this evaluation.
     */
    public function sentiment()
    {
        return $this->hasOne(EvaluationSentiment::class);
    }
}
