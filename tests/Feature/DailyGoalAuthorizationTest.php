<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\DailyGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyGoalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?string $name = null): User
    {
        return User::create([
            'name' => $name ?? ucfirst($role).' Tester',
            'email' => $role.'.'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function goal(User $staff, User $admin, string $targetDate = '2026-11-16'): DailyGoal
    {
        $target = Carbon::parse($targetDate);

        return DailyGoal::create([
            'user_id' => $staff->id,
            'week_start_date' => $target->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'target_date' => $target->toDateString(),
            'title' => 'Prepare report',
            'status' => DailyGoal::STATUS_ACTIVE,
            'created_by' => $admin->id,
            'effective_from' => now(),
        ]);
    }

    public function test_authenticated_administrator_can_open_daily_goals_page(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get('/admin/daily-goals?week_start=2026-11-16')
            ->assertOk();
    }

    public function test_administrator_can_create_and_edit_goals(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($admin)
            ->post(route('admin.daily-goals.store'), [
                'user_id' => $staff->id,
                'week_start_date' => '2026-11-16',
                'target_date' => '2026-11-16',
                'title' => 'Draft memo',
                'description' => 'Details',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $goal = DailyGoal::query()->where('title', 'Draft memo')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.daily-goals.update', $goal), [
                'title' => 'Draft revised memo',
                'description' => 'Updated',
                'success_criteria' => null,
                'target_date' => '2026-11-16',
                'status' => DailyGoal::STATUS_ACTIVE,
                'change_reason' => 'Clarified scope',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('daily_goals', [
            'id' => $goal->id,
            'title' => 'Draft revised memo',
        ]);
    }

    public function test_administrator_can_access_work_plan_management(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.daily-work-plan.index', ['week_start' => '2026-11-16']))
            ->assertOk();
    }

    public function test_ordinary_staff_member_cannot_access_administrator_goal_management(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $targetStaff = $this->user(User::ROLE_STAFF, 'Target Staff');
        $goal = $this->goal($targetStaff, $admin);

        $this->actingAs($staff)->get(route('admin.daily-goals.index'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.daily-goals.store'), [
            'user_id' => $targetStaff->id,
            'week_start_date' => '2026-11-16',
            'target_date' => '2026-11-17',
            'title' => 'Nope',
        ])->assertForbidden();
        $this->actingAs($staff)->put(route('admin.daily-goals.update', $goal), [
            'title' => 'Nope',
            'description' => null,
            'success_criteria' => null,
            'target_date' => '2026-11-16',
            'status' => DailyGoal::STATUS_ACTIVE,
        ])->assertForbidden();
        $this->actingAs($staff)->delete(route('admin.daily-goals.destroy', $goal))->assertForbidden();
    }

    public function test_supervisor_receives_only_existing_application_permissions(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supervisor = $this->user(User::ROLE_SUPERVISOR);
        $goal = $this->goal($staff, $admin);

        $this->actingAs($supervisor)->get(route('admin.daily-goals.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-work-plan.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.daily-goals.for-date', [
            'user_id' => $staff->id,
            'date' => '2026-11-16',
        ]))->assertForbidden();
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('admin.daily-goals.index'))->assertRedirect(route('login'));
        $this->get(route('admin.daily-work-plan.index'))->assertRedirect(route('login'));
    }

    public function test_backend_ajax_endpoints_enforce_same_authorization_rules(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $other = $this->user(User::ROLE_STAFF, 'Other');

        $goal = $this->goal($other, $admin);

        $this->actingAs($admin)
            ->getJson(route('admin.daily-goals.edit', $goal))
            ->assertOk()
            ->assertJsonPath('id', $goal->id);

        $this->actingAs($admin)
            ->getJson(route('admin.daily-goals.for-date', [
                'user_id' => $other->id,
                'date' => '2026-11-16',
            ]))
            ->assertOk()
            ->assertJsonPath('goals.0.id', $goal->id);

        $this->actingAs($staff)->getJson(route('admin.daily-goals.edit', $goal))->assertForbidden();
        $this->actingAs($staff)->getJson(route('admin.daily-goals.for-date', [
            'user_id' => $other->id,
            'date' => '2026-11-16',
        ]))->assertForbidden();

        $this->getJson(route('admin.daily-goals.for-date', [
            'user_id' => $other->id,
            'date' => '2026-11-16',
        ]))->assertForbidden();
    }
}
