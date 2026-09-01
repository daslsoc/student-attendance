<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A teacher. There are no passwords in this app — sign-in is an emailed magic
 * link (see AuthController) — so what matters here is the login token, the role
 * that decides what they can do, and whether the account is still active.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `role_id` and `deactivated_at` are deliberately absent: privilege and
     * account status are only ever changed through UserAdminController, which
     * audit-logs each change.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'login_token',
        'login_token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'login_token',
        'login_token_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'login_token_expires_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The single role this teacher holds. Every permission they have comes from
     * here — there are no per-teacher grants.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The bottleneck every permission check funnels through — reached via
     * CurrentTeacher::can from the Gates in AuthServiceProvider.
     */
    public function hasPermission(string $atom): bool
    {
        // A deactivated account can do nothing, whatever its role says.
        if (! $this->isActive()) {
            return false;
        }

        if ($this->role === null) {
            return false;
        }

        // Comma-bounded on both sides so one atom can't match a longer one.
        return str_contains((string) $this->role->permission_list, ",{$atom},");
    }

    /**
     * Accounts are "removed" by deactivating them, never by deleting the row —
     * the audit log has to keep naming a real person.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deactivated_at');
    }
}
