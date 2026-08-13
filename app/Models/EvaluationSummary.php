<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluatee_id',
        'semester_id',
        'student_score',
        'dean_score',
        'ph_dh_score',
        'peer_score',
        'self_score',
        'superior_score',
        'overall_rating',
        'total_submissions',
    ];

    protected function casts(): array
    {
        return [
            'student_score' => 'float',
            'dean_score' => 'float',
            'ph_dh_score' => 'float',
            'peer_score' => 'float',
            'self_score' => 'float',
            'superior_score' => 'float',
            'overall_rating' => 'float',
            'total_submissions' => 'integer',
        ];
    }

    /**
     * Get the evaluatee employee.
     */
    public function evaluatee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'evaluatee_id');
    }

    /**
     * Get the semester for this summary.
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
}
