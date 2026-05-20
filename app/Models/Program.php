<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'department_id',
        'program_head_id',
    ];

    /**
     * Get the department this program belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the Program Head managing this program.
     */
    public function programHead()
    {
        return $this->belongsTo(Employee::class, 'program_head_id');
    }

    /**
     * Get the students enrolled in this program.
     */
    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
