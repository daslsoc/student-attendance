<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Student extends Model
{
    use HasFactory;

    protected $primaryKey = 'student_number';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['student_number', 'first_name', 'last_name'];

    /**
     * Boot method to log creation/updates/deletions of child records.
     */
    protected static function booted()
    {
        // Log identifiers only — never the student's name (PII) into the
        // application log. The student_number is enough to trace a change.
        static::created(function ($model) {
            Log::info('Student created', ['student_number' => $model->student_number]);
        });
        static::updated(function ($model) {
            Log::info('Student updated', ['student_number' => $model->student_number]);
        });
        static::deleted(function ($model) {
            Log::info('Student deleted', ['student_number' => $model->student_number]);
        });
    }
}
