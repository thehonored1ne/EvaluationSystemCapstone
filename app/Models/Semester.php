<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'is_active',
        'is_evaluation_open',
        'student_max_points',
        'peer_max_points',
        'self_max_points',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_evaluation_open' => 'boolean',
        'student_max_points' => 'float',
        'peer_max_points' => 'float',
        'self_max_points' => 'float',
    ];

    /**
     * Get the academic year this semester belongs to.
     */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the classes scheduled for this semester.
     */
    public function classes()
    {
        return $this->hasMany(AcademicClass::class, 'semester_id');
    }
}
