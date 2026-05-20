<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationCriterion extends Model
{
    use HasFactory;

    protected $table = 'evaluation_criteria';

    protected $fillable = [
        'evaluation_type',
        'name',
        'order',
        'max_points',
    ];

    protected $casts = [
        'max_points' => 'float',
    ];

    /**
     * Get the questions belonging to this criterion.
     */
    public function questions()
    {
        return $this->hasMany(EvaluationQuestion::class, 'criterion_id');
    }
}
