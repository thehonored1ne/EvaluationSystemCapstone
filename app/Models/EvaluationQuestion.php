<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $criterion_id
 * @property string $question_text
 * @property int $order
 * @property bool $is_active
 * @property-read EvaluationCriterion $criterion
 * @property-read Collection<int, EvaluationAnswer> $answers
 */
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
     *
     * @return BelongsTo<EvaluationCriterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'criterion_id');
    }

    /**
     * Get the answers submitted for this question.
     *
     * @return HasMany<EvaluationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class, 'question_id');
    }

    protected static function booted(): void
    {
        static::saved(fn () => EvaluationCriterion::clearCache());
        static::deleted(fn () => EvaluationCriterion::clearCache());
    }
}
