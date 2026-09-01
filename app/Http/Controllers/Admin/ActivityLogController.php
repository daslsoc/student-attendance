<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLogEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only view of the audit trail. Gated by `can:view_audit_log`.
 *
 * There is no edit or delete action here by design: an audit trail you can
 * quietly rewrite isn't one.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $action = $request->query('action');

        return view('admin.audit', [
            'entries' => ActivityLogEntry::query()
                ->where('type', ActivityLogEntry::TYPE_ADMIN_ACTION)
                ->when($action, fn ($query) => $query->where('action', $action))
                ->latest()
                ->paginate(50)
                ->withQueryString(),
            'action' => $action,
            // Distinct codes actually present, so the filter never offers an
            // option that returns nothing.
            'actions' => ActivityLogEntry::query()
                ->where('type', ActivityLogEntry::TYPE_ADMIN_ACTION)
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }
}
