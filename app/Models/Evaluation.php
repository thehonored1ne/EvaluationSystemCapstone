<?php

namespace App\Models;

use App\Jobs\ProcessEvaluationSubmission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * In-memory per-request cache for evaluation statuses and active queue jobs.
     */
    protected static array $statusCache = [];

    protected static ?array $activeJobsCache = null;

    public static function flushStatusCache(): void
    {
        self::$statusCache = [];
        self::$activeJobsCache = null;
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

        // 2. Check database queue jobs if not in completed map
        try {
            $activeJobs = DB::table('jobs')
                ->where('queue', 'default')
                ->where('payload', 'like', '%ProcessEvaluationSubmission%')
                ->pluck('payload');

            foreach ($activeJobs as $payloadStr) {
                $payloadData = json_decode($payloadStr, true);
                $command = $payloadData['data']['command'] ?? null;
                if ($command) {
                    $unserialized = unserialize($command, ['allowed_classes' => [ProcessEvaluationSubmission::class]]);
                    if ($unserialized instanceof ProcessEvaluationSubmission) {
                        if ($unserialized->evaluatorId === $evaluatorId
                            && $unserialized->evaluateeId === $evaluateeId
                            && $unserialized->semesterId === $semesterId
                            && (string) ($unserialized->classId ?? 'null') === (string) $lookupClassId
                            && $unserialized->evaluationType === $type) {
                            return 'processing';
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to inspect database queue jobs in Evaluation::getStatus', [
                'error' => $e->getMessage(),
            ]);
        }

        return 'pending';
    }

    /**
     * Get the sentiment analysis details associated with this evaluation.
     *
     * @return HasOne<EvaluationSentiment, $this>
     */
    public function sentiment(): HasOne
    {
        return $this->hasOne(EvaluationSentiment::class);
    }
}
