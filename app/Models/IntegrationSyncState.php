<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row record of how far the app has synced from student-registration.
 */
class IntegrationSyncState extends Model
{
    protected $table = 'integration_sync_state';

    protected $fillable = ['last_synced_at', 'last_checked_at', 'last_count'];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'last_count' => 'integer',
    ];

    /**
     * The single state row, created on first use. Keyed on "the first row"
     * rather than a fixed id — the id column is guarded, so firstOrCreate(['id'
     * => 1]) wouldn't reliably round-trip to the same row.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
