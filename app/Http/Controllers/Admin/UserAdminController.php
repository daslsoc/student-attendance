<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\AdminSafety;
use App\Support\CurrentTeacher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Teacher accounts admin. Gated by `can:manage_users` on the routes.
 *
 * There are no passwords to set: a teacher signs in with a link emailed to the
 * address recorded here, so adding someone is just a name, an email and a role.
 *
 * Removing someone DEACTIVATES them rather than deleting the row — the audit
 * log has to keep naming a real person. Two things are deliberately impossible,
 * both enforced by AdminSafety: deactivating yourself, and taking the last
 * `manage_users` permission out of the system.
 */
class UserAdminController extends Controller
{
    public function index(Request $request): View
    {
        $roleFilter = $request->query('role');

        return view('admin.users.index', [
            'users' => User::query()
                ->with('role')
                ->when($roleFilter, fn ($query) => $query->where('role_id', $roleFilter))
                ->orderBy('name')
                ->get(),
            'roles' => Role::orderBy('name')->get(),
            'roleFilter' => $roleFilter,
            'currentTeacherId' => CurrentTeacher::get()?->id,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        // role_id is guarded against mass assignment, so it's set explicitly.
        $user->role_id = $data['role_id'];
        $user->save();

        ActivityLogger::adminAction(
            'user.created',
            "Added teacher {$user->name}",
            $user,
            ['email' => $user->email, 'role' => $user->role?->name],
        );

        return redirect()->route('admin.users.index')
            ->with('message', "{$user->name} added. They sign in by requesting a login link with this email address.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
            'currentTeacherId' => CurrentTeacher::get()?->id,
        ]);
    }

    /**
     * Profile fields and role live on one form, but they are logged as two
     * different things — a rename and a privilege change are not the same
     * event, and only the second one matters when reading the audit log.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ]);

        $newRoleId = (int) $data['role_id'];
        $roleChanged = $newRoleId !== (int) $user->role_id;

        if ($roleChanged && AdminSafety::isLastAdministrator($user)) {
            $newRole = Role::find($newRoleId);

            if ($newRole === null || ! in_array(AdminSafety::ATOM, $newRole->atoms(), true)) {
                return back()->withErrors([
                    'role_id' => 'This is the only account that can manage teachers. Give someone else that permission first.',
                ])->withInput();
            }
        }

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
        ];

        DB::transaction(function () use ($user, $data, $newRoleId) {
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->role_id = $newRoleId;
            $user->save();
        });

        $user->refresh()->load('role');

        if ($before['name'] !== $user->name || $before['email'] !== $user->email) {
            ActivityLogger::adminAction(
                'user.updated',
                "Updated teacher {$user->name}",
                $user,
                ['before' => $before, 'after' => ['name' => $user->name, 'email' => $user->email]],
            );
        }

        if ($roleChanged) {
            ActivityLogger::adminAction(
                'user.role_changed',
                "Moved {$user->name} from {$before['role']} to {$user->role?->name}",
                $user,
                ['before' => $before['role'], 'after' => $user->role?->name],
            );
        }

        return redirect()->route('admin.users.index')->with('message', "{$user->name} updated.");
    }

    /**
     * "Remove" a teacher: no login link will be sent to them, any link already
     * in their inbox stops working, and a live session is dropped on its next
     * request (EnsureTeacherAuthenticated). The row stays.
     */
    public function deactivate(User $user): RedirectResponse
    {
        if ((int) $user->id === (int) CurrentTeacher::get()?->id) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        if (! $user->isActive()) {
            return back()->with('message', "{$user->name} is already deactivated.");
        }

        if (AdminSafety::isLastAdministrator($user)) {
            return back()->withErrors([
                'user' => 'This is the only account that can manage teachers. Give someone else that permission first.',
            ]);
        }

        $user->deactivated_at = now();
        // Kill any live magic link as well, so an email already sitting in an
        // inbox can't be used.
        $user->login_token = null;
        $user->login_token_expires_at = null;
        $user->save();

        ActivityLogger::adminAction(
            'user.deactivated',
            "Deactivated teacher {$user->name}",
            $user,
            ['email' => $user->email],
        );

        return back()->with('message', "{$user->name} has been deactivated.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        if ($user->isActive()) {
            return back()->with('message', "{$user->name} is already active.");
        }

        $user->deactivated_at = null;
        $user->save();

        ActivityLogger::adminAction(
            'user.reactivated',
            "Reactivated teacher {$user->name}",
            $user,
            ['email' => $user->email],
        );

        return back()->with('message', "{$user->name} has been reactivated.");
    }
}
