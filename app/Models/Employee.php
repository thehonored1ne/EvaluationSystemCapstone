<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'middle_name',
        'role',
        'status',
        'department_id',
    ];

    /**
     * Get the user account associated with the employee.
     */
    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the classes taught by this employee.
     */
    public function classes()
    {
        return $this->hasMany(AcademicClass::class, 'teacher_id');
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the department managed by this employee (if they are a Dean).
     */
    public function managedDepartment()
    {
        return $this->hasOne(Department::class, 'dean_id');
    }

    /**
     * Get the program managed by this employee (if they are a Program Head).
     */
    public function managedProgram()
    {
        return $this->hasOne(Program::class, 'program_head_id');
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
}
