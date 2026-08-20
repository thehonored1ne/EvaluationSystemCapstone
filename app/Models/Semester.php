<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Semester extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('semester');
    }

    protected $fillable = [
        'academic_year_id',
        'name',
        'is_active',
        'is_evaluation_open',
        'overall_max_points',
        'student_weight',
        'dean_weight',
        'ph_dh_weight',
        'peer_weight',
        'self_weight',
        'superior_weight',
        'upward_student_max_points',
        'upward_employee_max_points',
        'dean_max_points',
        'program_head_max_points',
        'department_head_max_points',
        'downward_max_points',
        'peer_max_points',
        'self_max_points',
        'staff_max_points',
        'evaluation_starts_at',
        'evaluation_ends_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_evaluation_open' => 'boolean',
        'overall_max_points' => 'float',
        'student_weight' => 'float',
        'dean_weight' => 'float',
        'ph_dh_weight' => 'float',
        'peer_weight' => 'float',
        'self_weight' => 'float',
        'superior_weight' => 'float',
        'upward_student_max_points' => 'float',
        'upward_employee_max_points' => 'float',
        'dean_max_points' => 'float',
        'program_head_max_points' => 'float',
        'department_head_max_points' => 'float',
        'downward_max_points' => 'float',
        'peer_max_points' => 'float',
        'self_max_points' => 'float',
        'staff_max_points' => 'float',
        'evaluation_starts_at' => 'datetime',
        'evaluation_ends_at' => 'datetime',
    ];

    /**
     * Get percentage weight for a category type.
     */
    public function getCategoryWeight(string $type): float
    {
        return match ($type) {
            'student', 'upward_student' => (float) ($this->student_weight ?? 30.0),
            'dean' => (float) ($this->dean_weight ?? 15.0),
            'ph_dh', 'downward' => (float) ($this->ph_dh_weight ?? 15.0),
            'peer' => (float) ($this->peer_weight ?? 15.0),
            'self' => (float) ($this->self_weight ?? 5.0),
            'superior', 'upward_employee' => (float) ($this->superior_weight ?? 20.0),
            default => 15.0,
        };
    }

    /**
     * Calculate category max points dynamically: (weight % / 100) * overall_max_points.
     */
    public function getCategoryMaxPoints(string $type): float
    {
        $weight = $this->getCategoryWeight($type);
        $overall = (float) ($this->overall_max_points ?? 200.0);

        return round(($weight / 100.0) * $overall, 2);
    }

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
