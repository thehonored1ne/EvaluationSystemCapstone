<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AcademicClass extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('academic_class');
    }

    // Explicitly target the classes table to avoid PHP reserved word conflicts
    protected $table = 'classes';

    protected $fillable = [
        'subject_id',
        'semester_id',
        'teacher_id',
        'section',
        'schedule',
        'room',
    ];

    /**
     * Get the subject taught in this class.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the semester this class belongs to.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the teacher (Employee) conducting this class.
     */
    public function teacher()
    {
        return $this->belongsTo(Employee::class, 'teacher_id');
    }

    /**
     * Get the students enrolled in this class.
     */
    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id');
    }

    /**
     * Get the evaluations submitted for this class.
     */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'class_id');
    }
}
