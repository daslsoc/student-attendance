<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the audit trail. Written only through App\Support\ActivityLogger
 * so the format stays consistent; read by the Admin -> Audit log page.
 */
class ActivityLogEntry extends Model
{
    public const TYPE_ADMIN_ACTION = 'admin_action';

    public const TYPE_SYSTEM_EVENT = 'system_event';

    /** @var list<string> */
    protected $fillable = [
        'type',
        'user_id',
        'user_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }
}
