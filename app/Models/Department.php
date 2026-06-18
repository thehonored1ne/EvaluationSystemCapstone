<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
