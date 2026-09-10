<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Subject extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('subject');
    }

    protected $fillable = [
        'code',
        'name',
        'year_level',
        'semester_offered',
        'description',
        'units',
    ];

    /**
     * Get the classes offered for this subject.
     */
    public function classes()
    {
        return $this->hasMany(AcademicClass::class, 'subject_id');
    }

    public static function clearDropdownCache(): void
    {
        Cache::forget('subjects_dropdown_list');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearDropdownCache());
        static::deleted(fn () => self::clearDropdownCache());
    }
}
