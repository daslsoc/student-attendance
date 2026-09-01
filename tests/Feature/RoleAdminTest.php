<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Role administration: the checkbox grid, what it may write, and the guard that
 * stops an edit locking every administrator out.
 */
class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_roles_is_required_to_reach_the_role_editor(): void
    {
        $this->actingAsTeacher(User::factory()->withAtoms(['manage_users'])->create());

        $this->get(route('admin.roles.index'))->assertForbidden();
        $this->get(route('admin.roles.create'))->assertForbidden();
    }

    public function test_the_seeded_roles_carry_the_expected_permissions(): void
    {
        $administrator = Role::where('name', 'Administrator')->firstOrFail();
        $coordinator = Role::where('name', 'Coordinator')->firstOrFail();
        $teacher = Role::where('name', 'Teacher')->firstOrFail();

        $this->assertContains('manage_users', $administrator->atoms());
        $this->assertContains('manage_roles', $administrator->atoms());

        $this->assertContains('edit_attendance', $coordinator->atoms());
        $this->assertNotContains('manage_users', $coordinator->atoms());

        // The default rollout role: marks their class, sees today's summary,
        // nothing else.
        $this->assertContains('mark_attendance', $teacher->atoms());
        $this->assertContains('view_summary', $teacher->atoms());
        $this->assertNotContains('edit_attendance', $teacher->atoms());
        $this->assertNotContains('run_registration_sync', $teacher->atoms());
    }

    public function test_the_teacher_role_can_mark_but_not_edit_or_sync(): void
    {
        $this->actingAsTeacher(
            User::factory()->inRole(Role::where('name', 'Teacher')->firstOrFail())->create()
        );

        $this->get(route('attendance.selection'))->assertOk();
        $this->get(route('book_distribution.selection'))->assertOk();
        $this->get(route('attendance.summary'))->assertOk();
        $this->get(route('attendance.edit'))->assertForbidden();
        $this->get(route('attendance.report'))->assertForbidden();
        $this->get(route('integration.status'))->assertForbidden();
    }

    public function test_a_role_can_be_created_with_a_chosen_set_of_permissions(): void
    {
        $this->actingAsTeacher();

        $this->post(route('admin.roles.store'), [
            'name' => 'Report Reader',
            'description' => 'Reports only.',
            'atoms' => ['view_summary', 'view_reports'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'Report Reader')->firstOrFail();
        $this->assertEqualsCanonicalizing(['view_summary', 'view_reports'], $role->atoms());
        $this->assertDatabaseHas('activity_log_entries', ['action' => 'role.created']);
    }

    public function test_permissions_not_in_the_registry_are_rejected(): void
    {
        $this->actingAsTeacher();

        $this->post(route('admin.roles.store'), [
            'name' => 'Invented',
            'atoms' => ['view_summary', 'become_superuser'],
        ])->assertSessionHasErrors('atoms.1');

        $this->assertDatabaseMissing('roles', ['name' => 'Invented']);
    }

    public function test_editing_a_role_changes_what_its_members_can_do_immediately(): void
    {
        $role = Role::factory()->withAtoms(['mark_attendance'])->create(['name' => 'Helper']);
        $member = User::factory()->inRole($role)->create();

        $this->actingAsTeacher($member);
        $this->get(route('attendance.selection'))->assertOk();

        $this->actingAsTeacher();
        $this->put(route('admin.roles.update', $role), [
            'name' => 'Helper',
            'atoms' => ['view_summary'],
        ])->assertRedirect(route('admin.roles.index'));

        // Same session, next request: permissions are read from the database
        // on every check, so the change lands without a re-login.
        $this->actingAsTeacher($member->fresh());
        $this->get(route('attendance.selection'))->assertForbidden();
        $this->get(route('attendance.summary'))->assertOk();

        $this->assertDatabaseHas('activity_log_entries', ['action' => 'role.updated']);
    }

    public function test_a_role_edit_cannot_leave_nobody_able_to_manage_teachers(): void
    {
        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $onlyAdmin = User::factory()->inRole($adminRole)->create();
        User::factory()->inRole(Role::where('name', 'Teacher')->firstOrFail())->create();

        $without = array_values(array_diff($adminRole->atoms(), ['manage_users']));

        $this->actingAsTeacher($onlyAdmin);
        $this->put(route('admin.roles.update', $adminRole), [
            'name' => $adminRole->name,
            'atoms' => $without,
        ])->assertSessionHasErrors('atoms');

        $this->assertContains('manage_users', $adminRole->fresh()->atoms());
    }

    public function test_the_same_edit_is_allowed_once_another_role_can_manage_teachers(): void
    {
        $adminRole = Role::where('name', 'Administrator')->firstOrFail();
        $onlyAdmin = User::factory()->inRole($adminRole)->create();

        $backupRole = Role::factory()->withAtoms(['manage_users', 'manage_roles'])->create(['name' => 'Backup']);
        User::factory()->inRole($backupRole)->create();

        $without = array_values(array_diff($adminRole->atoms(), ['manage_users']));

        $this->actingAsTeacher($onlyAdmin);
        $this->put(route('admin.roles.update', $adminRole), [
            'name' => $adminRole->name,
            'atoms' => $without,
        ])->assertSessionHasNoErrors();

        $this->assertNotContains('manage_users', $adminRole->fresh()->atoms());
    }

    public function test_a_role_with_members_cannot_be_deleted(): void
    {
        $this->actingAsTeacher();
        $role = Role::factory()->withAtoms(['mark_attendance'])->create(['name' => 'Occupied']);
        User::factory()->inRole($role)->create();

        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_an_empty_role_can_be_deleted(): void
    {
        $this->actingAsTeacher();
        $role = Role::factory()->create(['name' => 'Unused']);

        $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('activity_log_entries', ['action' => 'role.deleted']);
    }

    public function test_permission_matching_is_not_fooled_by_a_prefix(): void
    {
        $user = User::factory()->withAtoms(['mark_attendance'])->create();

        $this->assertTrue($user->hasPermission('mark_attendance'));
        $this->assertFalse($user->hasPermission('mark_att'));
    }

    public function test_a_teacher_with_no_role_can_do_nothing(): void
    {
        $user = User::factory()->roleless()->create();

        $this->assertFalse($user->hasPermission('mark_attendance'));

        $this->actingAsTeacher($user);
        $this->get(route('attendance.selection'))->assertForbidden();
    }
}
