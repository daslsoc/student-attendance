<?php

namespace Tests\Feature;

use App\Models\ActivityLogEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Teacher administration: who can reach it, what it writes, and the two things
 * it must refuse to do (deactivate yourself, or remove the last account that
 * can manage teachers).
 */
class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function teacherRole(): Role
    {
        return Role::where('name', 'Teacher')->firstOrFail();
    }

    public function test_a_teacher_without_manage_users_cannot_see_the_list(): void
    {
        $this->actingAsTeacher(User::factory()->inRole($this->teacherRole())->create());

        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_an_administrator_can_see_the_list(): void
    {
        $this->actingAsTeacher();
        User::factory()->create(['name' => 'Nimal Perera']);

        $this->get(route('admin.users.index'))->assertOk()->assertSee('Nimal Perera');
    }

    public function test_adding_a_teacher_needs_no_password_and_is_audit_logged(): void
    {
        $admin = $this->actingAsTeacher();

        $this->post(route('admin.users.store'), [
            'name' => 'Sunil Silva',
            'email' => 'sunil@example.test',
            'role_id' => $this->teacherRole()->id,
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'sunil@example.test')->first();
        $this->assertNotNull($created);
        $this->assertSame($this->teacherRole()->id, $created->role_id);
        $this->assertTrue($created->isActive());

        $this->assertDatabaseHas('activity_log_entries', [
            'action' => 'user.created',
            'user_id' => $admin->id,
            'subject_id' => $created->id,
        ]);
    }

    public function test_a_newly_added_teacher_can_request_a_login_link(): void
    {
        $this->actingAsTeacher();

        $this->post(route('admin.users.store'), [
            'name' => 'Sunil Silva',
            'email' => 'sunil@example.test',
            'role_id' => $this->teacherRole()->id,
        ]);

        // A brand new account has no password at all — the magic link is the
        // only way in, and it must work immediately.
        $this->post(route('login.send'), ['email' => 'sunil@example.test'])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('email', 'sunil@example.test')->value('login_token'));
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $this->actingAsTeacher();
        User::factory()->create(['email' => 'taken@example.test']);

        $this->post(route('admin.users.store'), [
            'name' => 'Dup',
            'email' => 'taken@example.test',
            'role_id' => $this->teacherRole()->id,
        ])->assertSessionHasErrors('email');
    }

    public function test_deactivating_a_teacher_stops_login_links_and_keeps_the_row(): void
    {
        $this->actingAsTeacher();
        $victim = User::factory()->inRole($this->teacherRole())->create([
            'email' => 'gone@example.test',
        ]);

        $this->post(route('admin.users.deactivate', $victim))->assertSessionHasNoErrors();

        // The row survives so the audit log still names a real person.
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
        $this->assertNotNull($victim->fresh()->deactivated_at);

        $this->post(route('login.send'), ['email' => 'gone@example.test'])
            ->assertSessionHasErrors();

        $this->assertNull($victim->fresh()->login_token);
    }

    public function test_a_login_link_issued_before_removal_stops_working(): void
    {
        $victim = User::factory()->inRole($this->teacherRole())->create();

        // Get a real, still-valid link first.
        $this->post(route('login.send'), ['email' => $victim->email]);
        $token = $victim->fresh()->login_token;
        $this->assertNotNull($token);

        $this->actingAsTeacher();
        $this->post(route('admin.users.deactivate', $victim));

        // Drop the acting admin's session so the link is clicked by an
        // anonymous browser, the way the removed teacher would.
        $this->flushSession();

        $this->get(route('login.token', ['token' => $token]))
            ->assertRedirect('login');

        $this->assertFalse(session()->has('teacher_logged_in'));
    }

    public function test_a_live_session_is_dropped_as_soon_as_the_account_is_deactivated(): void
    {
        $teacher = $this->actingAsTeacher();

        $this->get(route('attendance.selection'))->assertOk();

        // Set explicitly: deactivated_at is guarded against mass assignment,
        // like role_id, so ->update([...]) would silently do nothing.
        $teacher->deactivated_at = now();
        $teacher->save();

        $this->get(route('attendance.selection'))->assertRedirect(route('login.form'));
    }

    public function test_a_teacher_cannot_deactivate_themselves(): void
    {
        $admin = $this->actingAsTeacher();

        $this->post(route('admin.users.deactivate', $admin))->assertSessionHasErrors('user');

        $this->assertNull($admin->fresh()->deactivated_at);
    }

    public function test_a_deactivated_teacher_can_be_reactivated(): void
    {
        $this->actingAsTeacher();
        $victim = User::factory()->deactivated()->create();

        $this->post(route('admin.users.reactivate', $victim))->assertSessionHasNoErrors();

        $this->assertNull($victim->fresh()->deactivated_at);
        $this->assertDatabaseHas('activity_log_entries', ['action' => 'user.reactivated']);
    }

    public function test_changing_a_role_is_logged_separately_from_a_rename(): void
    {
        $this->actingAsTeacher();
        $coordinator = Role::where('name', 'Coordinator')->firstOrFail();
        $target = User::factory()->inRole($this->teacherRole())->create();

        $this->put(route('admin.users.update', $target), [
            'name' => 'Renamed Teacher',
            'email' => $target->email,
            'role_id' => $coordinator->id,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame($coordinator->id, $target->fresh()->role_id);
        $this->assertDatabaseHas('activity_log_entries', ['action' => 'user.updated']);
        $this->assertDatabaseHas('activity_log_entries', ['action' => 'user.role_changed']);
    }

    public function test_the_last_administrator_cannot_be_moved_out_of_their_role(): void
    {
        $admin = $this->actingAsTeacher();
        $teacherRole = $this->teacherRole();

        $this->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $teacherRole->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertNotSame($teacherRole->id, $admin->fresh()->role_id);
    }

    public function test_role_id_cannot_be_smuggled_in_through_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'role_id' => Role::where('name', 'Administrator')->value('id'),
        ]);

        $this->assertNull($user->role_id);
        $this->assertFalse($user->hasPermission('manage_users'));
    }

    public function test_the_audit_page_needs_its_own_permission(): void
    {
        ActivityLogEntry::create([
            'type' => ActivityLogEntry::TYPE_ADMIN_ACTION,
            'user_name' => 'Someone',
            'action' => 'user.created',
            'description' => 'Added teacher Test Person',
        ]);

        $this->actingAsTeacher(User::factory()->withAtoms(['manage_users'])->create());
        $this->get(route('admin.audit'))->assertForbidden();

        $this->actingAsTeacher(User::factory()->withAtoms(['view_audit_log'])->create());
        $this->get(route('admin.audit'))->assertOk()->assertSee('Added teacher Test Person');
    }
}
