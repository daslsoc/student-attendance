<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        static::created(function ($model) {
            Log::info('Student created', $model->toArray());
        });
        static::updated(function ($model) {
            Log::info('Student updated', $model->toArray());
        });
        static::deleted(function ($model) {
            Log::info('Student deleted', $model->toArray());
        });
    }
}
