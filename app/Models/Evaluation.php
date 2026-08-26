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

    protected static function booted(): void
    {
        static::saved(function () {
            self::flushStatusCache();
        });

        static::deleted(function () {
            self::flushStatusCache();
        });
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

            self::$statusCache[$cacheKey] = $map;
        }

        $lookupClassId = $classId ?? 'null';
        $itemKey = "{$evaluateeId}_{$lookupClassId}_{$type}";

        if (isset(self::$statusCache[$cacheKey][$itemKey])) {
            return self::$statusCache[$cacheKey][$itemKey];
        }

        // 2. Check database queue jobs if not completed
        try {
            $jobExists = DB::table('jobs')
                ->where('queue', 'default')
                ->where('payload', 'like', '%ProcessEvaluationSubmission%')
                ->where('payload', 'like', '%evaluatorId\";i:'.$evaluatorId.';%')
                ->where('payload', 'like', '%evaluateeId\";i:'.$evaluateeId.';%')
                ->where('payload', 'like', '%semesterId\";i:'.$semesterId.';%')
                ->where('payload', 'like', '%evaluationType\";s:'.strlen($type).':\"'.$type.'\";%');

            if (is_null($classId)) {
                $jobExists->where('payload', 'like', '%classId\";N;%');
            } else {
                $jobExists->where('payload', 'like', '%classId\";i:'.$classId.';%');
            }

            if ($jobExists->exists()) {
                return 'processing';
            }
        } catch (\Throwable $e) {
            // Gracefully ignore if jobs table does not exist
        }

        return 'pending';
    }

    /**
     * Get the sentiment analysis details associated with this evaluation.
     */
    public function sentiment()
    {
        return $this->hasOne(EvaluationSentiment::class);
    }
}
