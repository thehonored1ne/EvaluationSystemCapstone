<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EvaluationQuestion extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('evaluation_question');
    }

    protected $fillable = [
        'criterion_id',
        'question_text',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the criterion this question belongs to.
     */
    public function criterion()
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }

    /**
     * Get the answers submitted for this question.
     */
    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class, 'question_id');
    }
}
