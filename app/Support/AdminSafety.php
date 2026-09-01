<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * Stops the app being locked out of itself.
 *
 * Every screen that can take `manage_users` away from somebody — deactivating
 * an account, moving it to another role, or untick-ing the atom in the role
 * editor — asks here first. If the change would leave no active teacher able to
 * manage accounts, it is refused.
 */
final class AdminSafety
{
    /** The atom that, if nobody holds it, nobody can ever grant it again. */
    public const ATOM = 'manage_users';

    /**
     * Ids of active users who can manage accounts, ignoring the given ids.
     *
     * @param  list<int>  $ignoreUserIds
     * @return list<int>
     */
    public static function activeAdminIds(array $ignoreUserIds = []): array
    {
        return User::query()
            ->active()
            ->whereNotIn('id', $ignoreUserIds)
            ->with('role')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission(self::ATOM))
            ->pluck('id')
            ->all();
    }

    /**
     * Is this the only active account that can manage users? Deactivating them,
     * or moving them to a role without the atom, would lock everyone out.
     */
    public static function isLastAdministrator(User $user): bool
    {
        return $user->hasPermission(self::ATOM)
            && self::activeAdminIds([$user->id]) === [];
    }

    /**
     * Would rewriting this role's permissions leave nobody able to manage
     * accounts? Only a concern when the role currently carries the atom and the
     * new CSV drops it.
     */
    public static function roleUpdateLocksEveryoneOut(Role $role, string $newCsv): bool
    {
        $hadAtom = in_array(self::ATOM, $role->atoms(), true);
        $keepsAtom = str_contains($newCsv, ','.self::ATOM.',');

        if (! $hadAtom || $keepsAtom) {
            return false;
        }

        // Everyone in this role is about to lose the atom, so see who is left.
        $memberIds = $role->users()->active()->pluck('id')->all();

        return self::activeAdminIds($memberIds) === [];
    }
}
