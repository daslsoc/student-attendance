<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions. Every teacher belongs to exactly one role
 * (users.role_id), and that role's `permission_list` is the CSV of atoms the
 * permission check reads.
 *
 * The CSV is stored comma-bounded (",a,b,c,") so a substring check for
 * ",{$atom}," can never match a partial atom name — see User::hasPermission.
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * `permission_list` is deliberately NOT fillable: permissions are only ever
     * granted through RoleController::update, which audit-logs the before/after.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'description'];

    /**
     * Members of this role.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The atoms this role carries, as a plain array.
     *
     * @return list<string>
     */
    public function atoms(): array
    {
        $csv = (string) $this->permission_list;

        return array_values(array_filter(
            explode(',', $csv),
            fn ($atom) => $atom !== '',
        ));
    }

    /**
     * Compose an atom array back into the comma-bounded CSV. Unknown atoms are
     * dropped, so a hand-crafted form post can't smuggle in a permission that
     * config/permissions.php doesn't define.
     *
     * @param  array<int, string>  $atoms
     */
    public static function atomsToCsv(array $atoms): string
    {
        $known = static::knownAtoms();

        $clean = array_values(array_unique(array_filter(
            array_map('trim', $atoms),
            fn ($atom) => $atom !== '' && in_array($atom, $known, true),
        )));

        if ($clean === []) {
            return ',';
        }

        return ','.implode(',', $clean).',';
    }

    /**
     * Every atom defined in config/permissions.php, flattened.
     *
     * @return list<string>
     */
    public static function knownAtoms(): array
    {
        $atoms = [];

        foreach (config('permissions', []) as $module) {
            foreach (array_keys($module['atoms'] ?? []) as $atom) {
                $atoms[] = $atom;
            }
        }

        return $atoms;
    }
}
