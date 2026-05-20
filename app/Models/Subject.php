<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'units',
    ];

    /**
     * Get the classes offered for this subject.
     */
    public function classes()
    {
        return $this->hasMany(AcademicClass::class, 'subject_id');
    }
}
