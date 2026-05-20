<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'question_id',
        'rating',
    ];

    /**
     * Get the evaluation this answer belongs to.
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Get the question this answer is rating.
     */
    public function question()
    {
        return $this->belongsTo(EvaluationQuestion::class);
    }
}
