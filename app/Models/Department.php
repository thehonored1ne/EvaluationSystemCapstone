<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'dean_id',
    ];

    /**
     * Get the Dean managing this department.
     */
    public function dean()
    {
        return $this->belongsTo(Employee::class, 'dean_id');
    }

    /**
     * Get the programs belonging to this department.
     */
    public function programs()
    {
        return $this->hasMany(Program::class);
    }
}
