<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluator_id',
        'evaluatee_id',
        'semester_id',
        'class_id',
        'evaluation_type',
        'rating_average',
        'comments',
    ];

    protected $casts = [
        'rating_average' => 'decimal:2',
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
     * Get the current status of an evaluation (completed, processing, or pending).
     */
    public static function getStatus(int $evaluatorId, int $evaluateeId, int $semesterId, ?int $classId = null, string $type = 'student'): string
    {
        // 1. Check database
        $exists = self::where([
            'evaluator_id' => $evaluatorId,
            'evaluatee_id' => $evaluateeId,
            'semester_id' => $semesterId,
            'class_id' => $classId,
            'evaluation_type' => $type,
        ])->exists();

        if ($exists) {
            return 'completed';
        }

        // 2. Check database queue jobs
        try {
            $pending = \Illuminate\Support\Facades\DB::table('jobs')
                ->where('queue', 'default')
                ->where(function ($query) use ($evaluatorId, $evaluateeId, $semesterId, $classId, $type) {
                    $query->where('payload', 'like', '%ProcessEvaluationSubmission%')
                        ->where('payload', 'like', '%evaluatorId%i:' . $evaluatorId . ';%')
                        ->where('payload', 'like', '%evaluateeId%i:' . $evaluateeId . ';%')
                        ->where('payload', 'like', '%semesterId%i:' . $semesterId . ';%')
                        ->where('payload', 'like', '%evaluationType%s:' . strlen($type) . ':%' . $type . '%');

                    if (is_null($classId)) {
                        $query->where('payload', 'like', '%classId%N;%');
                    } else {
                        $query->where('payload', 'like', '%classId%i:' . $classId . ';%');
                    }
                })
                ->exists();

            if ($pending) {
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
