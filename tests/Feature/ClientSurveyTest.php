<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientSurveyTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $label = 'Survey User'): User
    {
        return User::create([
            'name' => $label,
            'email' => 'survey'.uniqid().'@gmail.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $label = 'Survey Client'): User
    {
        return $this->user(User::ROLE_CLIENT, $label);
    }

    private function validPayload(): array
    {
        return [
            'overall_rating' => '5',
            'service_rating' => '4',
            'portal_rating' => '5',
            'comments' => 'Very helpful team!',
        ];
    }

    public function test_client_without_survey_is_redirected_from_portal_to_survey(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client.survey.show'));

        $this->actingAs($client)
            ->get(route('client.billing.index'))
            ->assertRedirect(route('client.survey.show'));
    }

    public function test_survey_page_loads_for_due_client(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.survey.show'))
            ->assertOk()
            ->assertSee('Monthly satisfaction survey', false)
            ->assertSee('name="overall_rating"', false)
            ->assertSee('name="service_rating"', false)
            ->assertSee('name="portal_rating"', false);
    }

    public function test_client_can_submit_survey_and_passes_through_afterwards(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('client.survey.store'), $this->validPayload())
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('client_survey_responses', [
            'user_id' => $client->id,
            'overall_rating' => 5,
            'service_rating' => 4,
            'portal_rating' => 5,
            'comments' => 'Very helpful team!',
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk();
    }

    public function test_survey_is_mandatory_once_every_30_days(): void
    {
        $client = $this->client();

        Carbon::setTestNow(Carbon::now()->subDays(35));
        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'overall_rating' => 4,
            'service_rating' => 4,
            'portal_rating' => 4,
            'comments' => null,
            'submitted_at' => now(),
        ]);
        Carbon::setTestNow();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client.survey.show'));

        $newClient = $this->client();

        Carbon::setTestNow(Carbon::now()->subDays(5));
        ClientSurveyResponse::create([
            'user_id' => $newClient->id,
            'overall_rating' => 4,
            'service_rating' => 4,
            'portal_rating' => 4,
            'comments' => null,
            'submitted_at' => now(),
        ]);
        Carbon::setTestNow();

        $this->actingAs($newClient)
            ->get(route('client.dashboard'))
            ->assertOk();
    }

    public function test_client_who_already_answered_is_redirected_away_from_survey_page(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('client.survey.store'), $this->validPayload());

        $this->actingAs($client)
            ->get(route('client.survey.show'))
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_duplicate_submission_after_completing_is_rejected(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->post(route('client.survey.store'), $this->validPayload());

        $this->actingAs($client)
            ->post(route('client.survey.store'), $this->validPayload())
            ->assertRedirect(route('client.dashboard'));

        $this->assertSame(1, ClientSurveyResponse::where('user_id', $client->id)->count());
    }

    public function test_survey_validation_requires_ratings(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->from(route('client.survey.show'))
            ->post(route('client.survey.store'), [])
            ->assertSessionHasErrors(['overall_rating', 'service_rating', 'portal_rating']);

        $this->assertSame(0, ClientSurveyResponse::count());
    }

    public function test_staff_and_admin_are_never_redirected_to_survey(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Jr');
        $staff = $this->user(User::ROLE_STAFF, 'Staff Jr');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->actingAs($staff)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_survey_routes_require_client_role(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Jr');

        $this->actingAs($admin)
            ->get(route('client.survey.show'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('client.survey.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_admin_can_view_survey_responses_and_due_clients(): void
    {
        $client = $this->client();
        $admin = $this->user(User::ROLE_ADMIN, 'Admin Jr');

        $this->actingAs($client)
            ->post(route('client.survey.store'), $this->validPayload());

        $dued = $this->client('Due Client');

        $this->actingAs($admin)
            ->get(route('admin.surveys.index'))
            ->assertOk()
            ->assertSee($client->name, false)
            ->assertSee($dued->name, false);
    }
}
