<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $word
 * @property string $category
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
class Profanity extends Model
{
    use HasFactory, LogsActivity;

    public const CACHE_KEY_ACTIVE = 'profanity_words_active';

    protected $table = 'profanities';

    protected $fillable = [
        'word',
        'category',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('profanity');
    }

    /**
     * Get the user who registered this profanity word.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Retrieve cached list of active profane words, ordered by length descending.
     * Longer multi-word phrases must be matched before single-word substrings.
     *
     * @return array<int, string>
     */
    public static function getActiveWords(): array
    {
        return Cache::rememberForever(self::CACHE_KEY_ACTIVE, function () {
            return self::query()
                ->where('is_active', true)
                ->orderByRaw('LENGTH(word) DESC')
                ->pluck('word')
                ->toArray();
        });
    }

    /**
     * Invalidate cached active profanity words.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_ACTIVE);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearCache());
        static::deleted(fn () => self::clearCache());
    }
}
