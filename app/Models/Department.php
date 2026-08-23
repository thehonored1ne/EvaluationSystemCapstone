<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Department extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('department');
    }

    protected $fillable = [
        'name',
        'code',
        'type',
        'dean_id',
        'program_head_id',
        'department_head_id',
    ];

    /**
     * Get the Dean managing this department.
     */
    public function dean()
    {
        return $this->belongsTo(Employee::class, 'dean_id');
    }

    /**
     * Get the Program Head managing this department.
     */
    public function programHead()
    {
        return $this->belongsTo(Employee::class, 'program_head_id');
    }

    /**
     * Get the Department Head managing this department.
     */
    public function departmentHead()
    {
        return $this->belongsTo(Employee::class, 'department_head_id');
    }

    /**
     * Get all Department Heads belonging to this department.
     */
    public function departmentHeads()
    {
        return $this->hasMany(Employee::class)->where('role', 'department head');
    }

    /**
     * Get all Program Heads belonging to this department.
     */
    public function programHeads()
    {
        return $this->hasMany(Employee::class)->where('role', 'program head');
    }

    /**
     * Get the programs belonging to this department.
     */
    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    /**
     * Get the employees belonging to this department.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get all departments ordered by type and name, cached in memory.
     */
    public static function getCachedList()
    {
        return Cache::remember('departments_all', 300, function () {
            return self::orderBy('type')->orderBy('name')->get();
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('departments_all'));
        static::deleted(fn () => Cache::forget('departments_all'));
    }
}
