<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EvaluationCriterion extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('criterion');
    }

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

    /**
     * Get criteria and active questions for given evaluation types cached in memory.
     *
     * @param  array<int, string>  $types
     */
    public static function getForTypes(array $types)
    {
        sort($types);
        $key = 'eval_criteria_'.implode('_', $types);

        return Cache::remember($key, 600, function () use ($types) {
            return self::whereIn('evaluation_type', $types)
                ->with(['questions' => fn ($q) => $q->where('is_active', true)->orderBy('order')])
                ->orderBy('order')
                ->get();
        });
    }

    public static function clearCache(): void
    {
        // Flush all evaluation criteria cache patterns
        Cache::flush();
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
