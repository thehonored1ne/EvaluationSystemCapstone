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
        'upward_student_max_points',
        'upward_employee_max_points',
        'downward_max_points',
        'peer_max_points',
        'self_max_points',
        'evaluation_starts_at',
        'evaluation_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_evaluation_open' => 'boolean',
        'upward_student_max_points' => 'float',
        'upward_employee_max_points' => 'float',
        'downward_max_points' => 'float',
        'peer_max_points' => 'float',
        'self_max_points' => 'float',
        'evaluation_starts_at' => 'datetime',
        'evaluation_ends_at' => 'datetime',
    ];

    /**
     * Determine if evaluations are currently open based on status and start/end dates.
     */
    public function getEvaluationStatusAttribute(): string
    {
        if (! $this->is_evaluation_open) {
            return 'locked';
        }

        if (! $this->evaluation_starts_at || ! $this->evaluation_ends_at) {
            return 'locked';
        }

        $now = now();

        if ($now->lt($this->evaluation_starts_at)) {
            return 'scheduled';
        }

        if ($now->gt($this->evaluation_ends_at)) {
            return 'expired';
        }

        return 'active';
    }

    public function isEvaluationWindowActive(): bool
    {
        return $this->evaluation_status === 'active';
    }

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
