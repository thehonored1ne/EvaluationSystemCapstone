<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EvaluationExemption extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'evaluator_id',
        'evaluatee_id',
        'semester_id',
        'evaluation_type',
        'reason',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            Evaluation::flushStatusCache();
        });

        static::deleted(function () {
            Evaluation::flushStatusCache();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('evaluation_exemption');
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function evaluatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluatee_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
