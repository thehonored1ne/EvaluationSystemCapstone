<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $academic_year_id
 * @property string $name
 * @property bool $is_active
 * @property bool $is_evaluation_open
 * @property Carbon|null $evaluation_starts_at
 * @property Carbon|null $evaluation_ends_at
 * @property-read AcademicYear|null $academicYear
 */
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
            'ph_dh', 'downward', 'program_head', 'department_head' => (float) ($this->ph_dh_weight ?? 15.0),
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
     *
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the active semester with academic year cached in memory.
     */
    public static function getActive(): ?self
    {
        return Cache::remember('active_semester', 300, function () {
            return self::where('is_active', true)->with('academicYear')->first();
        });
    }

    /**
     * Clear the active semester cache.
     */
    public static function clearActiveCache(): void
    {
        Cache::forget('active_semester');
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::clearActiveCache());
        static::deleted(fn () => self::clearActiveCache());
    }

    /**
     * Get the classes scheduled for this semester.
     *
     * @return HasMany<AcademicClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'semester_id');
    }

    /**
     * Get the evaluations submitted for this semester.
     *
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'semester_id');
    }

    /**
     * Compute integer chronological sorting key: (start_year * 10) + term_order.
     * e.g. "2026-2027 1st Semester" => 20261
     *      "2026-2027 2nd Semester" => 20262
     *      "2027-2028 2nd Semester" => 20272
     */
    public function getChronologicalKey(): int
    {
        $ayName = $this->academicYear->name ?? '';
        preg_match('/\d{4}/', $ayName, $yearMatches);
        $startYear = ! empty($yearMatches) ? (int) $yearMatches[0] : 2000;

        $name = strtolower($this->name);
        $termOrder = 1;
        if (str_contains($name, '2nd') || str_contains($name, 'second')) {
            $termOrder = 2;
        } elseif (str_contains($name, '3rd') || str_contains($name, 'third') || str_contains($name, 'summer') || str_contains($name, 'midyear')) {
            $termOrder = 3;
        }

        return ($startYear * 10) + $termOrder;
    }

    /**
     * Find the immediately preceding semester that occurred chronologically before this one.
     */
    public function getPreviousSemester(bool $mustHaveEvaluations = true): ?self
    {
        $currentKey = $this->getChronologicalKey();

        $candidates = self::with('academicYear')
            ->where('id', '!=', $this->id)
            ->when($mustHaveEvaluations, fn ($q) => $q->whereHas('evaluations'))
            ->get()
            ->filter(fn (self $s) => $s->getChronologicalKey() < $currentKey)
            ->sortByDesc(fn (self $s) => $s->getChronologicalKey());

        return $candidates->first();
    }
}
