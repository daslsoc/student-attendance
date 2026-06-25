<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['name'];

    /**
     * Boot method to log creation/updates/deletions of child records.
     */
    protected static function booted()
    {
        static::created(function ($model) {
            Log::info('Class created', $model->toArray());
        });
        static::updated(function ($model) {
            Log::info('Class updated', $model->toArray());
        });
        static::deleted(function ($model) {
            Log::info('Class deleted', $model->toArray());
        });
    }
}
