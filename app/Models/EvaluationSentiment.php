<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluationSentiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'vader_score',
        'vader_label',
        'dt_label',
        'manual_label',
    ];

    protected $casts = [
        'vader_score' => 'float',
    ];

    /**
     * Get the active sentiment label, prioritizing manual overrides.
     */
    public function getActiveLabelAttribute(): string
    {
        return $this->manual_label ?? $this->dt_label;
    }

    /**
     * Get the evaluation that owns the sentiment.
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }
}
