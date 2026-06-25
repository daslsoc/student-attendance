<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * Boot method to log creation/updates/deletions of child records.
     */
    protected static function booted()
    {
        static::created(function ($model) {
            Log::info('Subject created', $model->only(['id', 'name']));
        });
        static::updated(function ($model) {
            Log::info('Subject updated', $model->only(['id', 'name']));
        });
        static::deleted(function ($model) {
            Log::info('Subject deleted', $model->only(['id', 'name']));
        });
    }
}
