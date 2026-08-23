<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Student extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('student');
    }

    protected $fillable = [
        'student_number',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'program_id',
        'year_level',
        'section',
        'status',
    ];

    /**
     * Get the user account associated with the student.
     */
    public function user()
    {
        return $this->hasOne(User::class, 'student_id');
    }

    /**
     * Get the academic program the student is enrolled in.
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Get the classes the student is enrolled in.
     */
    public function classes()
    {
        return $this->belongsToMany(AcademicClass::class, 'class_student', 'student_id', 'class_id');
    }

    /**
     * Get the student's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->middle_name
            ? "{$this->first_name} {$this->middle_name} {$this->last_name}"
            : "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get formatted full name: Last Name, First Name Middle Name Suffix
     */
    public function getFormattedNameAttribute(): string
    {
        $firstMiddleSuffix = trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->suffix])));

        return "{$this->last_name}, {$firstMiddleSuffix}";
    }
}
