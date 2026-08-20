<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
}
