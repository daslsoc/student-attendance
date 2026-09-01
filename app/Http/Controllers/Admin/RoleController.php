<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\ActivityLogger;
use App\Support\AdminSafety;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Roles admin. Gated by `can:manage_roles` on the routes.
 *
 * The edit screen renders a checkbox grid straight from config/permissions.php,
 * so adding an atom to that file is all it takes for it to become tickable here.
 * Role::atomsToCsv drops anything not in that file, so a hand-crafted post
 * can't invent a permission.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'modules' => config('permissions'),
            'current' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = new Role;
        $role->name = $data['name'];
        $role->description = $data['description'] ?? null;
        // permission_list is guarded against mass assignment on purpose.
        $role->permission_list = Role::atomsToCsv($data['atoms'] ?? []);
        $role->save();

        ActivityLogger::adminAction(
            'role.created',
            "Created role {$role->name}",
            $role,
            ['permissions' => $role->atoms()],
        );

        return redirect()->route('admin.roles.index')->with('message', "Role {$role->name} created.");
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', [
            'role' => $role,
            'modules' => config('permissions'),
            // The grid ticks a box when the atom is a key of this array.
            'current' => array_flip($role->atoms()),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role);
        $newCsv = Role::atomsToCsv($data['atoms'] ?? []);

        if (AdminSafety::roleUpdateLocksEveryoneOut($role, $newCsv)) {
            return back()->withErrors([
                'atoms' => 'Removing "Add, edit & deactivate teachers" from this role would leave nobody able to manage accounts. Grant it to another role first.',
            ])->withInput();
        }

        $before = [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->atoms(),
        ];

        $role->name = $data['name'];
        $role->description = $data['description'] ?? null;
        $role->permission_list = $newCsv;
        $role->save();

        ActivityLogger::adminAction(
            'role.updated',
            "Updated role {$role->name}",
            $role,
            [
                'before' => $before,
                'after' => [
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions' => $role->atoms(),
                ],
            ],
        );

        return redirect()->route('admin.roles.index')->with('message', "Role {$role->name} updated.");
    }

    /**
     * A role is only deletable once it's empty — otherwise its members would
     * silently end up with no permissions at all.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => 'Move its members to another role before deleting it.',
            ]);
        }

        $name = $role->name;
        $atoms = $role->atoms();
        $role->delete();

        ActivityLogger::adminAction(
            'role.deleted',
            "Deleted role {$name}",
            null,
            ['name' => $name, 'permissions' => $atoms],
        );

        return redirect()->route('admin.roles.index')->with('message', "Role {$name} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'atoms' => ['array'],
            'atoms.*' => ['string', Rule::in(Role::knownAtoms())],
        ]);
    }
}
