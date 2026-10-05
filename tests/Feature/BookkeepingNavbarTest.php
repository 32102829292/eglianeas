<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The period-based trackers are reachable from the dashboard sidebar for
 * operational accounts only, next to the weekly tracker they sit beside.
 */
class BookkeepingNavbarTest extends TestCase
{
    use RefreshDatabase;

    private function acknowledged(array $attributes): User
    {
        return User::factory()->create($attributes + [
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function test_operational_accounts_see_both_new_trackers(): void
    {
        foreach ([
            $this->acknowledged(['role' => User::ROLE_ADMIN]),
            $this->acknowledged(['role' => User::ROLE_STAFF]),
            $this->acknowledged(['role' => User::ROLE_SUPERVISOR]),
        ] as $user) {
            $response = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

            $this->assertStringContainsString(
                route('admin.monthly-bookkeeping.index'),
                $response->getContent()
            );
            $this->assertStringContainsString(
                route('admin.quarterly-bookkeeping.index'),
                $response->getContent()
            );
        }
    }

    public function test_clients_do_not_see_the_new_trackers(): void
    {
        $client = $this->acknowledged([
            'role' => User::ROLE_CLIENT,
            'approved_at' => now(),
        ]);

        // The client portal sits behind the survey gate.
        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'submitted_at' => now(),
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'Test survey',
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.monthly-bookkeeping.index'), false)
            ->assertDontSee(route('admin.quarterly-bookkeeping.index'), false);
    }

    public function test_the_new_trackers_are_grouped_with_the_weekly_one(): void
    {
        $staff = $this->acknowledged(['role' => User::ROLE_STAFF]);

        $content = $this->actingAs($staff)->get(route('admin.dashboard'))->assertOk()->getContent();

        $weekly = strpos($content, route('admin.weekly-bookkeeping.index'));
        $monthly = strpos($content, route('admin.monthly-bookkeeping.index'));
        $quarterly = strpos($content, route('admin.quarterly-bookkeeping.index'));

        $this->assertNotFalse($weekly, 'The weekly tracker must still be linked');
        $this->assertNotFalse($monthly);
        $this->assertNotFalse($quarterly);

        // All three belong to the same Operational section of the sidebar.
        $this->assertLessThan($monthly, $weekly);
        $this->assertLessThan($quarterly, $monthly);
    }
}
