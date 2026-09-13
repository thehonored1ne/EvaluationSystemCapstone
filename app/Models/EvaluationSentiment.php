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
     * Check if the sentiment is conflicted or low-confidence.
     */
    public function getIsConflictedAttribute(): bool
    {
        // If already manually overridden, it has been resolved by human ground truth
        if (! is_null($this->manual_label)) {
            return false;
        }

        // 1. Model Disagreement (VADER vs Decision Tree)
        if ($this->vader_label !== $this->dt_label) {
            return true;
        }

        // 2. Agreement Gate Contradiction (Rating vs Sentiment)
        $rating = (float) ($this->evaluation?->rating_average ?? 3.0);
        if ($rating >= 4.2 && $this->dt_label === 'negative') {
            return true;
        }
        if ($rating <= 2.2 && $this->dt_label === 'positive') {
            return true;
        }

        return false;
    }

    /**
     * Get the confidence level descriptor: High, Moderate, Low, or Human Verified.
     */
    public function getConfidenceLevelAttribute(): string
    {
        if (! is_null($this->manual_label)) {
            return 'Human Verified';
        }

        if ($this->is_conflicted) {
            return 'Low (Conflict)';
        }

        if ($this->vader_label === 'neutral' || $this->dt_label === 'neutral') {
            return 'Moderate';
        }

        return 'High';
    }

    /**
     * Scope query to only conflicted or low-confidence evaluations needing review.
     */
    public function scopeConflicted($query)
    {
        return $query->whereNull('manual_label')
            ->where(function ($q) {
                $q->whereColumn('vader_label', '!=', 'dt_label')
                    ->orWhereHas('evaluation', function ($sub) {
                        $sub->where(function ($subQ) {
                            $subQ->where('rating_average', '>=', 4.2)
                                ->where('evaluation_sentiments.dt_label', 'negative');
                        })->orWhere(function ($subQ) {
                            $subQ->where('rating_average', '<=', 2.2)
                                ->where('evaluation_sentiments.dt_label', 'positive');
                        });
                    });
            });
    }

    /**
     * Scope query to manually overridden sentiments.
     */
    public function scopeOverridden($query)
    {
        return $query->whereNotNull('manual_label');
    }

    /**
     * Get the evaluation that owns the sentiment.
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }
}
