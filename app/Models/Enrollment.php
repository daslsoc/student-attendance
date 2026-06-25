<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = ['student_number', 'class_id', 'subject_id'];

    /**
     * Boot method to log creation/updates/deletions of child records.
     */
    protected static function booted()
    {
        static::created(function ($model) {
            Log::info('Enrollment created', $model->toArray());
        });
        static::updated(function ($model) {
            Log::info('Enrollment updated', $model->toArray());
        });
        static::deleted(function ($model) {
            Log::info('Enrollment deleted', $model->toArray());
        });
    }
}