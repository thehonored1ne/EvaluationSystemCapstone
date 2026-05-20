<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'student_id',
        'semester_id',
        'rating_average',
        'comments',
    ];

    protected $casts = [
        'rating_average' => 'decimal:2',
    ];

    /**
     * Get the class being evaluated.
     */
    public function class()
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    /**
     * Get the student who filled out this evaluation.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the semester when this evaluation was submitted.
     */
    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the answers submitted in this evaluation.
     */
    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
}
