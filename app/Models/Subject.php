<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
            Log::info('Subject created', $model->toArray());
        });
        static::updated(function ($model) {
            Log::info('Subject updated', $model->toArray());
        });
        static::deleted(function ($model) {
            Log::info('Subject deleted', $model->toArray());
        });
    }    
}
