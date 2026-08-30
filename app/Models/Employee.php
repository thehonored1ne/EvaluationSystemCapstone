<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $employee_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $middle_name
 * @property string|null $suffix
 * @property string $role
 * @property string $status
 * @property int|null $department_id
 * @property-read User|null $user
 * @property-read Department|null $department
 * @property-read string $full_name
 * @property-read string $formatted_name
 */
class Employee extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['updated_at'])
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName('employee');
    }

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'role',
        'status',
        'department_id',
    ];

    /**
     * Get the user account associated with the employee.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the classes taught by this employee.
     *
     * @return HasMany<AcademicClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(AcademicClass::class, 'teacher_id');
    }

    /**
     * Get the department this employee belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the department managed by this employee (if they are a Dean).
     *
     * @return HasOne<Department, $this>
     */
    public function managedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'dean_id');
    }

    /**
     * Get all departments supervised by this employee (if they are a Dean).
     *
     * @return HasMany<Department, $this>
     */
    public function supervisedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'dean_id');
    }

    /**
     * Get the program managed by this employee (if they are a Program Head).
     *
     * @return HasOne<Program, $this>
     */
    public function managedProgram(): HasOne
    {
        return $this->hasOne(Program::class, 'program_head_id');
    }

    /**
     * Get the department managed by this employee (if they are a Department Head).
     *
     * @return HasOne<Department, $this>
     */
    public function managedAdminDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'department_head_id');
    }

    /**
     * Get the employee's full name.
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
