<?php

namespace App\Support;

use App\Models\ActivityLogEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * The single place that WRITES to the audit trail.
 *
 *   ActivityLogger::adminAction('user.created', 'Added teacher Jane Doe', $user);
 *   ActivityLogger::systemEvent('login.link_sent', '...');
 *
 * Static so it can be called from anywhere without wiring up DI — fine for an
 * append-only log. Mirrors the same-named helper in the tea-roster and
 * registration apps; the only difference is where "who did this" comes from,
 * since this app has no Auth user (see CurrentTeacher).
 */
class ActivityLogger
{
    /**
     * Record something a signed-in teacher did.
     *
     * @param  string  $action  short code, e.g. 'user.deactivated'
     * @param  string  $description  human sentence shown in the UI
     * @param  Model|null  $subject  the record this is about
     * @param  array<string, mixed>  $properties  extra detail, e.g. ['before' => ..., 'after' => ...]
     */
    public static function adminAction(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): ActivityLogEntry {
        $actor = CurrentTeacher::get();

        return ActivityLogEntry::create([
            'type' => ActivityLogEntry::TYPE_ADMIN_ACTION,
            'user_id' => $actor?->getKey(),
            // Snapshot the name so the entry still makes sense after a rename.
            'user_name' => $actor?->name,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
        ]);
    }

    /**
     * Record something the system did — no teacher attached.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function systemEvent(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): ActivityLogEntry {
        return ActivityLogEntry::create([
            'type' => ActivityLogEntry::TYPE_SYSTEM_EVENT,
            'user_id' => null,
            'user_name' => null,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
        ]);
    }
}
