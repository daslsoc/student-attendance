<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'subject_id', 'class_id', 'student_number', 'teacher_id'];

    /**
     * Boot method to log creation/updates/deletions of child records.
     */
    protected static function booted()
    {
        static::created(function ($model) {
            Log::info('Attendance created', $model->toArray());
        });
        static::updated(function ($model) {
            Log::info('Attendance updated', $model->toArray());
        });
        static::deleted(function ($model) {
            Log::info('Attendance deleted', $model->toArray());
        });
    }
}
