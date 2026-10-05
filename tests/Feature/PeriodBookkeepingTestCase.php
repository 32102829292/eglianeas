<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use App\Support\Bookkeeping\BookkeepingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One suite of behaviour for the period-based bookkeeping modules.
 *
 * Monthly and Quarterly Bookkeeping run the same workflow as the weekly one with
 * a month or a quarter in place of a week, so the rules live here once and each
 * module only declares which period it represents. Anything that genuinely
 * differs between the two is written in the concrete test class instead.
 */
abstract class PeriodBookkeepingTestCase extends TestCase
{
    use RefreshDatabase;

    /** Which period this module plans in: `monthly` or `quarterly`. */
    abstract protected function periodKind(): string;

    /** @return class-string<Model> */
    abstract protected function planModel(): string;

    /** @return class-string<Model> */
    abstract protected function targetModel(): string;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('supabase');
    }

    // -----------------------------------------------------------------
    // Module description, derived from the models themselves
    // -----------------------------------------------------------------

    /** @return class-string<Model> */
    protected function planClass(): string
    {
        return $this->planModel();
    }

    /** @return class-string<Model> */
    protected function targetClass(): string
    {
        return $this->targetModel();
    }

    protected function routePrefix(): string
    {
        return $this->planClass()::ROUTE_PREFIX;
    }

    protected function startField(): string
    {
        return $this->planClass()::PERIOD_START_FIELD;
    }

    protected function startColumn(): string
    {
        return $this->planClass()::PERIOD_START_COLUMN;
    }

    protected function queryKey(): string
    {
        return BookkeepingPeriod::current($this->periodKind())->queryName();
    }

    protected function activityPrefix(): string
    {
        return $this->planClass()::ACTIVITY_PREFIX;
    }

    protected function nounTitle(): string
    {
        return (new ($this->planClass()))->planNounTitle();
    }

    /** "Monthly Bookkeeping" / "Quarterly Bookkeeping". */
    protected function moduleTitle(): string
    {
        return $this->periodKind() === \App\Support\Bookkeeping\BookkeepingPeriod::MONTHLY
            ? 'Monthly Bookkeeping'
            : 'Quarterly Bookkeeping';
    }

    /** "Month" / "Quarter". */
    protected function periodUnit(): string
    {
        return (new ($this->planClass()))->periodUnit();
    }

    protected function foreignKey(): string
    {
        return $this->planClass()::FOREIGN_KEY;
    }

    protected function currentPeriod(): BookkeepingPeriod
    {
        return BookkeepingPeriod::current($this->periodKind());
    }

    protected function previousPeriod(): BookkeepingPeriod
    {
        return $this->currentPeriod()->previous();
    }

    // -----------------------------------------------------------------
    // Accounts
    // -----------------------------------------------------------------

    private function acknowledged(array $attributes): User
    {
        return User::factory()->create($attributes + [
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    protected function admin(): User
    {
        return $this->acknowledged(['role' => User::ROLE_ADMIN]);
    }

    protected function staff(string $name = 'Staff Member'): User
    {
        return $this->acknowledged(['role' => User::ROLE_STAFF, 'name' => $name]);
    }

    protected function supervisor(string $name = 'Supervisor Member'): User
    {
        return $this->acknowledged(['role' => User::ROLE_SUPERVISOR, 'name' => $name]);
    }

    protected function client(string $name = 'Test Client'): User
    {
        $client = $this->acknowledged([
            'role' => User::ROLE_CLIENT,
            'name' => $name,
            'business_name' => "{$name} Business",
            'approved_at' => now(),
        ]);

        // The client portal sits behind the survey gate, so a usable client
        // account needs a submitted survey response.
        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'submitted_at' => now(),
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'Test survey',
        ]);

        return $client;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Saves a period plan through the real endpoint. Each client may list several
     * task types and its own assignee map, mirroring how the planner form posts.
     */
    protected function createPlan(
        User $owner,
        array $clientsConfig,
        ?BookkeepingPeriod $period = null,
        array $planOptions = []
    ): Model {
        $period ??= $this->currentPeriod();

        $clients = [];
        $tasks = [];
        $dates = [];
        $assignee = [];

        foreach ($clientsConfig as $clientId => $config) {
            $clients[] = $clientId;
            $tasks[$clientId] = $config['tasks'];
            $dates[$clientId] = $config['date'] ?? $period->startDate();

            $configured = $config['assignee'] ?? $owner->id;
            $map = [];

            foreach ($config['tasks'] as $taskType) {
                $map[$taskType] = is_array($configured) ? ($configured[$taskType] ?? null) : $configured;
            }

            $assignee[$clientId] = $map;
        }

        $this->actingAs($owner)
            ->post(route($this->routePrefix().'.store'), array_filter([
                $this->startField() => $period->startDate(),
                'clients' => $clients,
                'tasks' => $tasks,
                'target_date' => $dates,
                'assignee' => $assignee,
            ]) + $planOptions)
            ->assertRedirect();

        return $this->findPlan($owner, $period);
    }

    protected function findPlan(User $owner, BookkeepingPeriod $period): Model
    {
        return $this->planClass()::query()
            ->where('staff_id', $owner->id)
            ->whereDate($this->startColumn(), $period->startDate())
            ->firstOrFail();
    }

    protected function target(Model $plan, ?string $taskType = null, ?int $clientId = null): Model
    {
        $query = $this->targetClass()::query()->where($this->foreignKey(), $plan->id);

        if ($taskType !== null) {
            $query->where('task_type', $taskType);
        }

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        return $query->firstOrFail();
    }

    protected function url(string $name, array|Model $parameters = []): string
    {
        return route($this->routePrefix().'.'.$name, $parameters instanceof Model ? [$parameters] : $parameters);
    }

    protected function planTable(): string
    {
        return (new ($this->planClass()))->getTable();
    }

    protected function targetTable(): string
    {
        return (new ($this->targetClass()))->getTable();
    }

    // -----------------------------------------------------------------
    // Planning
    // -----------------------------------------------------------------

    public function test_staff_can_create_target_with_multiple_clients(): void
    {
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Pedrigal, Marilou');
        $clientB = $this->client('Aguba, Samuel');
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $clientA->id => ['tasks' => ['pickup']],
            $clientB->id => ['tasks' => ['record']],
        ], $period);

        $this->assertSame($staff->id, $plan->staff_id);
        $this->assertSame(2, $plan->targets()->count());
        $this->assertTrue($plan->targets()->where('client_id', $clientA->id)->where('task_type', 'pickup')->exists());
        $this->assertTrue($plan->targets()->where('client_id', $clientB->id)->where('task_type', 'record')->exists());
        $this->assertSame(
            $period->startDate(),
            $this->target($plan, 'pickup', $clientA->id)->target_date->format('Y-m-d')
        );
        $this->assertSame($period->startDate(), $plan->{$this->startColumn()}->format('Y-m-d'));
    }

    public function test_plan_covers_the_whole_period(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->assertSame($period->startDate(), $plan->period()->startDate());
        $this->assertSame($period->endDate(), $plan->period()->endDate());
        $this->assertSame($period->key(), $plan->period()->key());
        $this->assertTrue($plan->period()->isCurrent());
    }

    public function test_a_client_can_have_multiple_target_tasks_in_the_same_period(): void
    {
        $staff = $this->staff();
        $client = $this->client('Villasin, Maricel');

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['return', 'payment']]]);

        $this->assertSame(2, $plan->targets()->count());
        $this->assertSame(1, $plan->targetClientCount());
        $this->assertTrue($plan->targets()->where('task_type', 'return')->exists());
        $this->assertTrue($plan->targets()->where('task_type', 'payment')->exists());
    }

    public function test_staff_can_save_all_targets_in_one_submission(): void
    {
        $staff = $this->staff();
        $clients = collect([
            $this->client('Client A'),
            $this->client('Client B'),
            $this->client('Client C'),
            $this->client('Client D'),
            $this->client('Client E'),
        ]);

        $plan = $this->createPlan($staff, [
            $clients[0]->id => ['tasks' => ['pickup']],
            $clients[1]->id => ['tasks' => ['pickup']],
            $clients[2]->id => ['tasks' => ['record']],
            $clients[3]->id => ['tasks' => ['return']],
            $clients[4]->id => ['tasks' => ['return', 'payment']],
        ]);

        $this->assertSame(5, $plan->targetClientCount());
        $this->assertSame(6, $plan->targets()->count());
    }

    public function test_duplicate_target_for_same_client_and_task_is_not_duplicated(): void
    {
        $staff = $this->staff();
        $client = $this->client();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);

        $this->assertSame(1, $plan->targets()->count());
    }

    public function test_target_date_outside_the_period_is_rejected(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();
        $outside = $this->previousPeriod()->startDate();

        $this->actingAs($staff)
            ->post($this->url('store'), [
                $this->startField() => $period->startDate(),
                'clients' => [$client->id],
                'tasks' => [$client->id => ['pickup']],
                'target_date' => [$client->id => $outside],
            ])
            ->assertSessionHasErrors('target_date.'.$client->id);

        $this->assertSame(0, $this->planClass()::query()->count());
    }

    public function test_plan_is_reused_for_the_same_period(): void
    {
        // One period holds one plan, exactly like the weekly tracker: whoever
        // saves second adds to the same plan instead of starting a rival one.
        $staff = $this->staff();
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $period = $this->currentPeriod();

        $first = $this->createPlan($staff, [$clientA->id => ['tasks' => ['pickup']]], $period);
        $second = $this->createPlan($staff, [$clientB->id => ['tasks' => ['record']]], $period);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $this->planClass()::query()->count());

        // Saving again replaces the still-pending selection rather than piling
        // a second set of targets onto the same period.
        $this->assertSame(1, $first->targets()->count());
        $this->assertTrue($first->targets()->where('client_id', $clientB->id)->exists());
        $this->assertFalse($first->targets()->where('client_id', $clientA->id)->exists());
    }

    public function test_each_period_gets_its_own_plan(): void
    {
        $staff = $this->staff();
        $client = $this->client();

        $current = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $this->currentPeriod());
        $previous = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $this->previousPeriod());

        $this->assertFalse($current->is($previous));
        $this->assertSame(2, $this->planClass()::query()->count());
        $this->assertSame($this->currentPeriod()->key(), $current->period()->key());
        $this->assertSame($this->previousPeriod()->key(), $previous->period()->key());
    }

    // -----------------------------------------------------------------
    // Actual work
    // -----------------------------------------------------------------

    public function test_staff_can_start_actual_work(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Pick-Up marked as in progress.');

        $target->refresh();
        $this->assertSame($this->targetClass()::ACTUAL_STATUS_IN_PROGRESS, $target->actual_status);
        $this->assertNotNull($target->started_at);
        $this->assertSame($staff->id, $target->performed_by_id);
        $this->assertSame('staff', $target->performed_by_role);
    }

    public function test_completion_requires_evidence(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->post($this->url('complete-target', [$plan, $target]))
            ->assertSessionHasErrors('action');

        $target->refresh();
        $this->assertSame($this->targetClass()::ACTUAL_STATUS_IN_PROGRESS, $target->actual_status);
    }

    public function test_completion_with_evidence_records_accountability(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup'], 'date' => now()->format('Y-m-d')],
        ], $period);

        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->post($this->url('complete-target', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Pick-Up On Time.');

        $target->refresh();
        // Pick-Up resolves to "On Time" in the comparison workbook, not a
        // generic Completed.
        $this->assertSame($this->targetClass()::ACTUAL_STATUS_ON_TIME, $target->actual_status);
        $this->assertTrue($target->isCompleted());
        $this->assertSame($this->targetClass()::TIMING_ON_TIME, $target->timing);
        $this->assertNotNull($target->ended_at);
        $this->assertNotNull($target->attachment_path);
        $this->assertSame($staff->id, $target->performed_by_id);
        $this->assertSame($staff->name, $target->performed_by_name);

        $plan->refresh();
        $this->assertSame($this->planClass()::STATUS_COMPLETED, $plan->status);
    }

    public function test_all_task_types_require_evidence_before_completion(): void
    {
        // A period holds one plan, so each task type is exercised in its own
        // period rather than fighting over the same plan.
        foreach ($this->fourTaskPeriods() as $taskType => $period) {
            $staff = $this->staff("Staff {$taskType}");
            $client = $this->client("Client {$taskType}");

            $plan = $this->createPlan($staff, [$client->id => ['tasks' => [$taskType]]], $period);
            $target = $this->target($plan);

            $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

            $this->actingAs($staff)
                ->post($this->url('complete-target', [$plan, $target]))
                ->assertSessionHasErrors('action');

            $target->refresh();
            $this->assertSame(
                $this->targetClass()::ACTUAL_STATUS_IN_PROGRESS,
                $target->actual_status,
                "{$taskType} must not close without evidence"
            );
        }
    }

    public function test_each_task_type_closes_with_its_own_outcome(): void
    {
        $expected = [
            'pickup' => 'on_time',
            'record' => 'completed',
            'return' => 'returned',
            'payment' => 'paid',
        ];

        foreach ($this->fourTaskPeriods() as $period) {
            $staff = $this->staff();
            $client = $this->client();

            $plan = $this->createPlan($staff, [$client->id => ['tasks' => array_keys($expected)]], $period);

            foreach ($expected as $taskType => $status) {
                $target = $this->target($plan, $taskType);

                $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

                $this->actingAs($staff)->post($this->url('complete-target', [$plan, $target]), [
                    'attachment' => UploadedFile::fake()->image('proof.jpg'),
                ])->assertRedirect();

                $target->refresh();
                $this->assertSame($status, $target->actual_status, "{$taskType} outcome");
                $this->assertTrue($target->isCompleted(), "{$taskType} should read as done");
            }

            $plan->refresh()->load('targets');
            $this->assertSame(100, $plan->completionPercent());
            $this->assertSame($this->planClass()::STATUS_COMPLETED, $plan->status);
        }
    }

    /**
     * Four consecutive periods, so each task type can own a plan of its own.
     *
     * @return array<string, BookkeepingPeriod>
     */
    private function fourTaskPeriods(): array
    {
        $current = $this->currentPeriod();

        return [
            'pickup' => $current,
            'record' => $current->previous(),
            'return' => $current->previous()->previous(),
            'payment' => $current->previous()->previous()->previous(),
        ];
    }

    public function test_payment_records_the_method_and_date(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['payment']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($staff)->post($this->url('complete-target', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('receipt.jpg'),
            'payment_method' => 'gcash',
            'paid_at' => now()->format('Y-m-d'),
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('gcash', $target->payment_method);
        $this->assertNotNull($target->payment_at);
        $this->assertStringContainsString('Paid GCash', (string) $target->paymentDetail());
    }

    public function test_duration_is_calculated_correctly(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['record']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $target]));

        $target->refresh();
        $target->update([
            'started_at' => now()->subMinutes(90),
            'ended_at' => now(),
        ]);

        $this->assertSame(90, $target->durationInMinutes());
        $this->assertSame('1 hour 30 minutes', $target->durationHuman());

        $target->update(['started_at' => now()->subMinutes(45)]);

        $this->assertSame('45 minutes', $target->durationHuman());
    }

    public function test_actual_performer_is_separate_from_target_owner(): void
    {
        $owner = $this->staff('Plan Owner');
        $worker = $this->staff('Actual Worker');
        $client = $this->client();

        $plan = $this->createPlan($owner, [$client->id => ['tasks' => ['record'], 'assignee' => $worker->id]]);
        $target = $this->target($plan);

        $this->assertSame($owner->id, $plan->staff_id);
        $this->assertSame($worker->id, $target->assigned_staff_id);

        $this->actingAs($worker)->post($this->url('start-target', [$plan, $target]));

        $target->refresh();
        $this->assertSame($worker->id, $target->performed_by_id);
        $this->assertSame('Actual Worker', $target->performedByDisplayName());
        $this->assertSame('Staff', $target->performedByRoleLabel());
        $this->assertSame($owner->name, $plan->fresh()->displayOwnerName());
    }

    public function test_a_task_assigned_to_someone_else_cannot_be_worked(): void
    {
        $owner = $this->staff('Plan Owner');
        $worker = $this->staff('Actual Worker');
        $other = $this->staff('Someone Else');
        $client = $this->client();

        $plan = $this->createPlan($owner, [$client->id => ['tasks' => ['record'], 'assignee' => $worker->id]]);
        $target = $this->target($plan);

        $this->actingAs($other)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertForbidden();

        $target->refresh();
        $this->assertSame($this->targetClass()::ACTUAL_STATUS_PENDING, $target->actual_status);
    }

    // -----------------------------------------------------------------
    // Editing targets
    // -----------------------------------------------------------------

    public function test_target_can_be_edited_before_work_starts(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $target = $this->target($plan);

        $newDate = $period->endDate();

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $target]), [
                'target_date' => $newDate,
                'notes' => 'Client asked for a later date.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $target->refresh();
        $this->assertSame($newDate, $target->target_date->format('Y-m-d'));
        $this->assertSame('Client asked for a later date.', $target->notes);
    }

    public function test_target_date_cannot_move_outside_the_period(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $target = $this->target($plan);
        $outside = $this->previousPeriod()->startDate();

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $target]), ['target_date' => $outside])
            ->assertSessionHasErrors('target_date');

        $target->refresh();
        $this->assertSame($period->startDate(), $target->target_date->format('Y-m-d'));
    }

    public function test_target_cannot_be_edited_after_work_starts(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $target]), [
                'target_date' => $period->endDate(),
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_the_task_sequence_offsets_run_pickup_record_return_payment(): void
    {
        $offsets = ($this->targetClass())::SEQUENCE_OFFSETS;

        $this->assertSame(['pickup' => 0, 'record' => 1, 'return' => 2, 'payment' => 3], $offsets);
    }

    public function test_changing_the_pickup_date_reschedules_the_later_stages(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment'], 'date' => $period->startDate()],
        ], $period);

        foreach (['record', 'return', 'payment'] as $taskType) {
            $this->target($plan, $taskType)->update([
                'target_date_auto' => true,
                'target_date' => $this->insidePeriod(1),
            ]);
        }

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $this->target($plan, 'pickup')]), [
                'target_date' => $this->insidePeriod(2),
            ])
            ->assertRedirect();

        $this->assertSame($this->insidePeriod(3), $this->target($plan, 'record')->target_date->format('Y-m-d'));
        $this->assertSame($this->insidePeriod(4), $this->target($plan, 'return')->target_date->format('Y-m-d'));
        $this->assertSame($this->insidePeriod(5), $this->target($plan, 'payment')->target_date->format('Y-m-d'));
    }

    public function test_a_reschedule_leaves_manually_edited_dates_alone(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'record', 'return'], 'date' => $period->startDate()],
        ], $period);

        $this->target($plan, 'record')->update([
            'target_date_auto' => false,
            'target_date' => $this->insidePeriod(6),
        ]);

        $this->target($plan, 'return')->update(['target_date_auto' => true]);

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $this->target($plan, 'pickup')]), [
                'target_date' => $this->insidePeriod(2),
            ]);

        $this->assertSame(
            $this->insidePeriod(6),
            $this->target($plan, 'record')->target_date->format('Y-m-d'),
            'A hand-edited Record date must survive a Pick-Up change.'
        );

        $this->assertSame($this->insidePeriod(4), $this->target($plan, 'return')->target_date->format('Y-m-d'));
    }

    public function test_a_reschedule_does_not_reach_a_stage_belonging_to_another_staff_member(): void
    {
        $staff = $this->staff();
        $colleague = $this->staff('Other Bookkeeper');
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => [
                'tasks' => ['pickup', 'record'],
                'date' => $period->startDate(),
                'assignee' => ['pickup' => $staff->id, 'record' => $colleague->id],
            ],
        ], $period);

        $this->target($plan, 'record')->update([
            'target_date_auto' => true,
            'target_date' => $this->insidePeriod(1),
        ]);

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $this->target($plan, 'pickup')]), [
                'target_date' => $this->insidePeriod(2),
            ]);

        $this->assertSame(
            $this->insidePeriod(1),
            $this->target($plan, 'record')->target_date->format('Y-m-d'),
            'A Pick-Up edit must not silently reschedule a colleague’s task.'
        );
    }

    public function test_suggested_dates_are_clamped_to_the_last_day_of_the_period(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $pickup = $this->target($plan, 'pickup');
        $lastDay = $period->endDate();

        /* Pick-Up on the final day leaves no room inside the period, so every later
           stage folds back onto it rather than escaping the plan. */
        $this->assertSame($lastDay, $pickup->suggestedDateFor('record', $lastDay)->format('Y-m-d'));
        $this->assertSame($lastDay, $pickup->suggestedDateFor('payment', $lastDay)->format('Y-m-d'));
    }

    public function test_remarks_and_a_balance_can_be_saved_on_a_target(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $this->target($plan, 'payment')]), [
                'notes' => 'Waiting on the client’s receipts.',
                'payment_status' => 'with_balance',
                'balance_amount' => '1800.50',
                'balance_note' => 'Outstanding filing fee.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $payment = $this->target($plan, 'payment');

        $this->assertSame('Waiting on the client’s receipts.', $payment->notes);
        $this->assertSame('with_balance', $payment->payment_status);
        $this->assertSame('1800.50', $payment->balance_amount);
        $this->assertSame('Outstanding filing fee.', $payment->balance_note);
        $this->assertSame('With Balance · ₱1,800.50', $payment->balanceSummary());
    }

    public function test_the_assigned_staff_can_add_remarks_once_the_work_has_started(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup'], 'date' => $period->startDate()],
        ], $period);

        $target = $this->target($plan, 'pickup');

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $target]), [
                'notes' => 'Logbook was short; the client is sending the rest.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $this->assertSame(
            'Logbook was short; the client is sending the rest.',
            $target->refresh()->notes
        );
    }

    public function test_a_remarks_only_save_does_not_touch_the_other_target_fields(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $payment = $this->target($plan, 'payment');

        $payment->update([
            'notes' => 'Awaiting the filing receipt.',
            'payment_status' => 'with_balance',
            'balance_amount' => '1800.50',
            'balance_note' => 'Outstanding filing fee.',
        ]);

        $dateBefore = $payment->target_date->format('Y-m-d');

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $payment]))
            ->assertRedirect();

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $payment]), [
                'notes' => 'Client called: payment lands this week.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $payment->refresh();
        $this->assertSame('Client called: payment lands this week.', $payment->notes);
        $this->assertSame($dateBefore, $payment->target_date->format('Y-m-d'));
        $this->assertSame($staff->id, $payment->assigned_staff_id);
        $this->assertSame('with_balance', $payment->payment_status);
        $this->assertSame('1800.50', $payment->balance_amount);
        $this->assertSame('Outstanding filing fee.', $payment->balance_note);
    }

    public function test_a_remarks_only_save_does_not_blank_existing_remarks(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup'], 'date' => $period->startDate()],
        ], $period);

        $target = $this->target($plan, 'pickup');
        $target->update(['notes' => 'Keep me.']);

        /* A date-only save must not wipe the remark. */
        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $target]), [
                'target_date' => $period->startDate(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $this->assertSame('Keep me.', $target->refresh()->notes);
    }

    public function test_remarks_cannot_be_edited_by_another_staff_member(): void
    {
        $owner = $this->staff();
        $other = $this->staff('Other Staff');
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($owner, [
            $client->id => ['tasks' => ['pickup'], 'date' => $period->startDate()],
        ], $period);

        $target = $this->target($plan, 'pickup');

        $this->actingAs($owner)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($other)
            ->patch($this->url('update-target', [$plan, $target]), [
                'notes' => 'Not my task.',
            ])
            ->assertForbidden();

        $this->assertNull($target->refresh()->notes);
    }

    public function test_a_supervisor_can_add_remarks_to_a_started_task(): void
    {
        $staff = $this->staff();
        $supervisor = $this->supervisor('Juan Dela Cruz');
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup'], 'date' => $period->startDate()],
        ], $period);

        $target = $this->target($plan, 'pickup');

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertRedirect();

        $this->actingAs($supervisor)
            ->patch($this->url('update-target', [$plan, $target]), [
                'notes' => 'Oversight note after a spot check.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target updated.');

        $this->assertSame('Oversight note after a spot check.', $target->refresh()->notes);
    }

    public function test_the_remarks_editor_submits_only_the_remarks(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        /* A payment stage, because that is the stage whose own form also carries
           the balance and payment fields the remarks editor must never touch. */
        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $payment = $this->target($plan, 'payment');

        $this->actingAs($staff)
            ->post($this->url('start-target', [$plan, $payment]))
            ->assertRedirect();

        $html = $this->actingAs($staff)
            ->get($this->url('show', [$plan]))
            ->assertOk()
            ->getContent();

        /* The remarks modal is rendered last inside the content section, so
           everything from its id onwards is the editor. */
        $start = strpos($html, 'id="bkRemarksModal"');
        $this->assertNotFalse($start, 'The remarks modal should be present on the plan page.');

        $editor = substr($html, $start);

        $this->assertStringContainsString('name="notes"', $editor);
        $this->assertStringNotContainsString('target_date', $editor);
        $this->assertStringNotContainsString('payment_status', $editor);
        $this->assertStringNotContainsString('balance_amount', $editor);
        $this->assertStringNotContainsString('balance_note', $editor);
        $this->assertStringNotContainsString('assigned_staff_id', $editor);
    }

    public function test_the_balance_is_cleared_once_the_status_is_no_longer_with_balance(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $this->target($plan, 'payment')->update([
            'payment_status' => 'with_balance',
            'balance_amount' => 1800,
            'balance_note' => 'Part payment.',
        ]);

        $this->actingAs($staff)
            ->patch($this->url('update-target', [$plan, $this->target($plan, 'payment')]), [
                'payment_status' => 'paid',
            ]);

        $payment = $this->target($plan, 'payment');

        $this->assertSame('paid', $payment->payment_status);
        $this->assertNull($payment->balance_amount);
        $this->assertNull($payment->balance_note);
        $this->assertFalse($payment->hasBalance());
    }

    public function test_a_balance_amount_is_required_and_must_not_be_negative(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $route = fn () => $this->url('update-target', [$plan, $this->target($plan, 'payment')]);

        $this->actingAs($staff)
            ->patch($route(), ['payment_status' => 'with_balance'])
            ->assertSessionHasErrors('balance_amount');

        $this->actingAs($staff)
            ->patch($route(), ['payment_status' => 'with_balance', 'balance_amount' => '-1'])
            ->assertSessionHasErrors('balance_amount');
    }

    public function test_the_plan_page_shows_the_sequence_with_an_editable_stage_form(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'record', 'return', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $this->actingAs($staff)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee('Target Schedule')
            ->assertSee('Pick-Up sets the sequence')
            ->assertSee('+1 day from Pick-Up', false)
            ->assertSee('+3 days from Pick-Up', false)
            ->assertSee('name="target_date"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('name="payment_status"', false)
            ->assertSee('name="balance_amount"', false)
            ->assertSee('name="balance_note"', false);
    }

    public function test_the_plan_page_shows_a_recorded_balance_and_remarks(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [
            $client->id => ['tasks' => ['pickup', 'payment'], 'date' => $period->startDate()],
        ], $period);

        $this->target($plan, 'payment')->update([
            'notes' => 'Half received this week.',
            'payment_status' => 'with_balance',
            'balance_amount' => 1250,
            'balance_note' => 'Rest due after the BIR filing.',
        ]);

        $this->actingAs($staff)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee('With Balance · ₱1,250.00')
            ->assertSee('Rest due after the BIR filing.')
            ->assertSee('Half received this week.');
    }

    public function test_pending_target_can_be_removed_and_history_recorded(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup', 'record']]]);

        $target = $this->target($plan, 'record');

        $this->actingAs($staff)
            ->delete($this->url('destroy-target', [$plan, $target]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Target removed.');

        $this->assertNull($this->targetClass()::query()->find($target->id));

        $this->assertDatabaseHas('activity_logs', [
            'action' => $this->activityPrefix().'.target_removed',
            $this->foreignKey() => $plan->id,
        ]);
    }

    public function test_unselecting_a_task_on_the_next_save_removes_it(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup', 'record']]], $period);

        // Re-saving with only Pick-Up drops the still-pending Record target.
        $this->actingAs($staff)->post($this->url('store'), [
            $this->startField() => $period->startDate(),
            'clients' => [$client->id],
            'tasks' => [$client->id => ['pickup']],
            'target_date' => [$client->id => $period->startDate()],
        ])->assertRedirect();

        $plan = $this->findPlan($staff, $period);
        $this->assertSame(1, $plan->targets()->count());
        $this->assertTrue($plan->targets()->where('task_type', 'pickup')->exists());
    }

    // -----------------------------------------------------------------
    // Visibility and permissions
    // -----------------------------------------------------------------

    public function test_staff_cannot_access_another_staffs_plan(): void
    {
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $client = $this->client();

        $plan = $this->createPlan($staffA, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staffB)
            ->get($this->url('show', $plan))
            ->assertForbidden();

        $this->actingAs($staffB)
            ->get($this->url('history', $plan))
            ->assertForbidden();

        $this->actingAs($staffB)
            ->post($this->url('start-target', [$plan, $target]))
            ->assertForbidden();
    }

    public function test_staff_index_only_shows_work_named_to_them(): void
    {
        $staffA = $this->staff('Maria Santos');
        $outsider = $this->staff('Juan Dela Cruz');
        $clientA = $this->client('Alpha Client');
        $clientB = $this->client('Beta Client');

        // Two separate periods keep the two plans apart.
        $planA = $this->createPlan($staffA, [$clientA->id => ['tasks' => ['pickup']]], $this->currentPeriod());
        $this->createPlan($staffA, [$clientB->id => ['tasks' => ['pickup']]], $this->previousPeriod());

        $this->actingAs($staffA)
            ->get($this->url('index', [$this->queryKey() => $this->currentPeriod()->key()]))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertDontSee('Beta Client');

        // Juan owns nothing and is named on nothing, so his tracker is empty.
        $this->actingAs($outsider)
            ->get($this->url('index', [$this->queryKey() => $this->currentPeriod()->key()]))
            ->assertOk()
            ->assertDontSee('Alpha Client')
            ->assertDontSee('Beta Client');

        $this->actingAs($outsider)
            ->get($this->url('show', $planA))
            ->assertForbidden();
    }

    public function test_staff_named_on_a_task_can_see_that_plan(): void
    {
        $owner = $this->staff('Plan Owner');
        $worker = $this->staff('Actual Worker');
        $client = $this->client();

        $plan = $this->createPlan($owner, [$client->id => ['tasks' => ['record'], 'assignee' => $worker->id]]);

        $this->actingAs($worker)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee($client->business_name);
    }

    public function test_supervisor_can_create_own_target(): void
    {
        $supervisor = $this->supervisor();
        $client = $this->client();

        $plan = $this->createPlan($supervisor, [$client->id => ['tasks' => ['pickup']]]);

        $this->assertSame($supervisor->id, $plan->staff_id);
    }

    public function test_supervisor_can_perform_staff_level_actual_work(): void
    {
        $supervisor = $this->supervisor();
        $client = $this->client();
        $plan = $this->createPlan($supervisor, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($supervisor)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($supervisor)->post($this->url('complete-target', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $target->refresh();
        $this->assertTrue($target->isCompleted());
        $this->assertSame('supervisor', $target->performed_by_role);
    }

    public function test_supervisor_can_monitor_staff_targets(): void
    {
        $staff = $this->staff('Maria Santos');
        $supervisor = $this->supervisor();
        $client = $this->client();
        $period = $this->currentPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);
        $target = $this->target($plan);

        $this->actingAs($supervisor)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee($client->business_name);

        $this->actingAs($supervisor)
            ->post($this->url('reassign-target', [$plan, $target]), ['assigned_staff_id' => $supervisor->id])
            ->assertRedirect();

        $target->refresh();
        $this->assertSame($supervisor->id, $target->assigned_staff_id);
    }

    public function test_admin_can_view_all_and_filter(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Alpha Client');
        $clientB = $this->client('Beta Client');
        $clientC = $this->client('Gamma Client');

        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        // Alpha and Beta share the earlier plan; Gamma sits in the current one.
        $this->createPlan($staff, [
            $clientA->id => ['tasks' => ['pickup']],
            $clientB->id => ['tasks' => ['record']],
        ], $previous);

        $this->createPlan($staff, [$clientC->id => ['tasks' => ['pickup']]], $current);

        // The admin reaches work owned by other staff.
        $this->actingAs($admin)
            ->get($this->url('index', [$this->queryKey() => $previous->key()]))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertSee('Beta Client')
            ->assertDontSee('Gamma Client');

        $this->actingAs($admin)
            ->get($this->url('index', [$this->queryKey() => $current->key()]))
            ->assertOk()
            ->assertSee('Gamma Client')
            ->assertDontSee('Alpha Client');

        // Task-type filter narrows the rows inside the selected period.
        $this->actingAs($admin)
            ->get($this->url('index', [
                $this->queryKey() => $previous->key(),
                'task_type' => 'record',
            ]))
            ->assertOk()
            ->assertSee('Beta Client')
            ->assertDontSee('Alpha Client');

        $this->actingAs($admin)
            ->get($this->url('index', [
                $this->queryKey() => $previous->key(),
                'q' => 'Alpha',
            ]))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertDontSee('Beta Client');
    }

    public function test_period_filtering_works(): void
    {
        $admin = $this->admin();
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $clientA = $this->client('Alpha Client');
        $clientB = $this->client('Beta Client');

        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        $this->createPlan($staffA, [$clientA->id => ['tasks' => ['pickup']]], $current);
        $this->createPlan($staffB, [$clientB->id => ['tasks' => ['pickup']]], $previous);

        $this->actingAs($admin)
            ->get($this->url('index', [$this->queryKey() => $current->key()]))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertDontSee('Beta Client');

        $this->actingAs($admin)
            ->get($this->url('index', [$this->queryKey() => $previous->key()]))
            ->assertOk()
            ->assertSee('Beta Client')
            ->assertDontSee('Alpha Client');
    }

    public function test_tracker_defaults_to_the_newest_planned_period(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        $client = $this->client('Only Client');

        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $previous);

        $this->actingAs($admin)
            ->get($this->url('index'))
            ->assertOk()
            ->assertSee($previous->rangeLabel());
    }

    public function test_staff_cannot_change_target_owner(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $other = $this->staff('Another Staff');

        $this->actingAs($staff)
            ->post($this->url('update-owner', $plan), ['staff_id' => $other->id])
            ->assertForbidden();

        $plan->refresh();
        $this->assertSame($staff->id, $plan->staff_id);
    }

    public function test_admin_can_change_target_owner(): void
    {
        $admin = $this->admin();
        $staff = $this->staff('Maria Santos');
        $newOwner = $this->staff('Juan Dela Cruz');
        $client = $this->client();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);

        $this->actingAs($admin)
            ->post($this->url('update-owner', $plan), ['staff_id' => $newOwner->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'Target owner updated.');

        $plan->refresh();
        $this->assertSame($newOwner->id, $plan->staff_id);
    }

    public function test_only_admin_or_supervisor_can_reassign(): void
    {
        $staff = $this->staff('Maria Santos');
        $other = $this->staff('Juan Dela Cruz');
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)
            ->post($this->url('reassign-target', [$plan, $target]), ['assigned_staff_id' => $other->id])
            ->assertForbidden();

        $target->refresh();
        $this->assertSame($staff->id, $target->assigned_staff_id);
    }

    public function test_reassigning_a_task_from_another_plan_is_rejected(): void
    {
        $admin = $this->admin();
        $staffA = $this->staff('Maria Santos');
        $staffB = $this->staff('Juan Dela Cruz');
        $clientA = $this->client('Alpha Client');
        $clientB = $this->client('Beta Client');

        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        $planA = $this->createPlan($staffA, [$clientA->id => ['tasks' => ['pickup']]], $current);
        $planB = $this->createPlan($staffB, [$clientB->id => ['tasks' => ['pickup']]], $previous);

        $targetB = $this->target($planB);

        // Oversight can reach both plans, but the task must belong to the plan
        // named in the URL.
        $this->actingAs($admin)
            ->post($this->url('reassign-target', [$planA, $targetB]), ['assigned_staff_id' => $admin->id])
            ->assertNotFound();

        $targetB->refresh();
        $this->assertSame($staffB->id, $targetB->assigned_staff_id);
    }

    public function test_only_admin_can_delete_plan(): void
    {
        $admin = $this->admin();
        $supervisor = $this->supervisor();
        $staff = $this->staff();
        $client = $this->client();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);

        $this->actingAs($supervisor)
            ->delete($this->url('destroy', $plan))
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete($this->url('destroy', $plan))
            ->assertRedirect();

        $this->assertNull($this->planClass()::query()->find($plan->id));
        $this->assertSame(0, $this->targetClass()::query()->count());
    }

    public function test_client_cannot_access_bookkeeping(): void
    {
        $client = $this->client();
        $staff = $this->staff();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $period = $this->currentPeriod();

        $this->actingAs($client)->get($this->url('index'))->assertForbidden();
        $this->actingAs($client)->get($this->url('create'))->assertForbidden();
        $this->actingAs($client)->get($this->url('show', $plan))->assertForbidden();
        $this->actingAs($client)->get($this->url('history', $plan))->assertForbidden();
        $this->actingAs($client)->get($this->url('report', [$this->queryKey() => $period->key()]))->assertForbidden();
        $this->actingAs($client)->post($this->url('store'))->assertForbidden();
    }

    public function test_client_does_not_see_sidebar_item(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee($this->moduleTitle())
            ->assertDontSee(route($this->routePrefix().'.index'), false);
    }

    public function test_operational_roles_see_the_sidebar_item(): void
    {
        foreach ([$this->admin(), $this->staff(), $this->supervisor()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee(route($this->routePrefix().'.index'), false);
        }
    }

    // -----------------------------------------------------------------
    // Accountability history
    // -----------------------------------------------------------------

    public function test_history_is_recorded_for_target_lifecycle(): void
    {
        $staff = $this->staff('Maria Santos');
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('complete-target', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        foreach ([
            $this->activityPrefix().'.target_added',
            $this->activityPrefix().'.targets_saved',
            $this->activityPrefix().'.target.started',
            $this->activityPrefix().'.target.completed',
        ] as $action) {
            $this->assertDatabaseHas('activity_logs', [
                'action' => $action,
                $this->foreignKey() => $plan->id,
                'user_id' => $staff->id,
            ]);
        }

        // One entry per recorded step, all filed under this plan.
        $this->assertSame(4, $plan->history()->count());
        $this->assertSame(4, $plan->history()->where('user_id', $staff->id)->count());

        $this->actingAs($staff)
            ->get($this->url('history', $plan))
            ->assertOk()
            ->assertSee('Accountability Timeline')
            ->assertSee('Actual work completed');
    }

    public function test_history_records_the_named_performer(): void
    {
        $owner = $this->staff('Plan Owner');
        $worker = $this->staff('Actual Worker');
        $client = $this->client();

        $plan = $this->createPlan($owner, [$client->id => ['tasks' => ['record'], 'assignee' => $worker->id]]);
        $target = $this->target($plan);

        $this->actingAs($worker)
            ->post($this->url('complete-target', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertSessionHasErrors('action');

        $this->actingAs($worker)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($worker)->post($this->url('complete-target', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'action' => $this->activityPrefix().'.target.completed',
            $this->foreignKey() => $plan->id,
            'user_id' => $worker->id,
        ]);

        $description = ActivityLog::query()
            ->where('action', $this->activityPrefix().'.target.completed')
            ->value('description');

        $this->assertStringContainsString('Actual Worker', (string) $description);
    }

    public function test_history_page_hides_other_periods_entries(): void
    {
        $staff = $this->staff('Maria Santos');
        $clientA = $this->client('Alpha Client');
        $clientB = $this->client('Beta Client');

        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        $plan = $this->createPlan($staff, [$clientA->id => ['tasks' => ['pickup']]], $current);
        $this->createPlan($staff, [$clientB->id => ['tasks' => ['pickup']]], $previous);

        $this->actingAs($staff)
            ->get($this->url('history', $plan))
            ->assertOk()
            ->assertSee('Alpha Client')
            ->assertDontSee('Beta Client');
    }

    // -----------------------------------------------------------------
    // Derived statuses once the period has closed
    // -----------------------------------------------------------------

    public function test_missed_task_shows_past_due_status_after_the_period(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->previousPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $target = $this->target($plan);
        $this->assertTrue($target->isPastDue());
        $this->assertSame('missed', $target->effectiveStatus());

        $this->actingAs($staff)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee('Missed');
    }

    public function test_unreturned_and_unpaid_statuses_derive_correctly(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->previousPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['return', 'payment']]], $period);

        $return = $this->target($plan, 'return');
        $payment = $this->target($plan, 'payment');

        $this->assertSame('unreturned', $return->effectiveStatus());
        $this->assertSame('unpaid', $payment->effectiveStatus());
        $this->assertTrue($payment->isUnpaid());

        $this->actingAs($staff)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee('Unreturned')
            ->assertSee('Unpaid');
    }

    public function test_completed_work_is_not_reported_as_past_due(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->previousPeriod();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['record']]], $period);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('complete-target', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $target->refresh();
        $this->assertFalse($target->isPastDue());
        $this->assertFalse($target->isUnfinished());
        $this->assertSame(100, $plan->completionPercent());
    }

    public function test_plan_completion_percent_counts_task_specific_outcomes(): void
    {
        $staff = $this->staff();
        $client = $this->client();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup', 'return', 'payment']]]);

        foreach (['pickup', 'return', 'payment'] as $taskType) {
            $target = $this->target($plan, $taskType);

            $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
            $this->actingAs($staff)->post($this->url('complete-target', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])->assertRedirect();
        }

        $plan->refresh()->load('targets');

        // On Time, Returned and Paid all count as done, not just "Completed".
        $this->assertSame(3, $plan->targets->filter(fn ($t) => $t->isCompleted())->count());
        $this->assertSame(100, $plan->completionPercent());
    }

    // -----------------------------------------------------------------
    // Evidence
    // -----------------------------------------------------------------

    public function test_evidence_upload_records_history(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));

        $this->actingAs($staff)
            ->post($this->url('upload-attachment', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertNotNull($target->attachment_path);

        Storage::disk('supabase')->assertExists($target->attachment_path);

        $this->assertDatabaseHas('activity_logs', [
            'action' => $this->activityPrefix().'.attachment_uploaded',
            $this->foreignKey() => $plan->id,
        ]);
    }

    public function test_evidence_can_be_replaced(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('upload-attachment', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('first.jpg'),
        ]);

        $target->refresh();
        $original = $target->attachment_path;

        $this->actingAs($staff)
            ->post($this->url('replace-attachment', [$plan, $target]), [
                'attachment' => UploadedFile::fake()->image('second.jpg'),
            ])
            ->assertRedirect();

        $target->refresh();
        $this->assertNotSame($original, $target->attachment_path);
        $this->assertSame('second.jpg', $target->attachment_name);

        $this->assertDatabaseHas('activity_logs', [
            'action' => $this->activityPrefix().'.attachment_replaced',
            $this->foreignKey() => $plan->id,
        ]);
    }

    public function test_evidence_view_redirects_to_signed_url_instead_of_erroring(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('upload-attachment', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $target->refresh();

        $this->actingAs($staff)
            ->get($this->url('view-attachment', [$plan, $target]))
            ->assertRedirect();
    }

    public function test_evidence_download_returns_the_file(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('upload-attachment', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $target->refresh();

        $this->actingAs($staff)
            ->get($this->url('download-attachment', [$plan, $target]))
            ->assertOk();
    }

    public function test_viewing_evidence_without_attachment_returns_404(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)
            ->get($this->url('view-attachment', [$plan, $target]))
            ->assertNotFound();

        $this->actingAs($staff)
            ->get($this->url('download-attachment', [$plan, $target]))
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_view_or_download_evidence(): void
    {
        $staff = $this->staff('Maria Santos');
        $other = $this->staff('Juan Dela Cruz');
        $client = $this->client();

        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]]);
        $target = $this->target($plan);

        $this->actingAs($staff)->post($this->url('start-target', [$plan, $target]));
        $this->actingAs($staff)->post($this->url('upload-attachment', [$plan, $target]), [
            'attachment' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $target->refresh();

        $this->actingAs($other)
            ->get($this->url('view-attachment', [$plan, $target]))
            ->assertForbidden();

        $this->actingAs($other)
            ->get($this->url('download-attachment', [$plan, $target]))
            ->assertForbidden();
    }

    // -----------------------------------------------------------------
    // Screens
    // -----------------------------------------------------------------

    public function test_target_planner_uses_compact_task_controls(): void
    {
        $staff = $this->staff();
        $client = $this->client();

        $this->actingAs($staff)
            ->get($this->url('create'))
            ->assertOk()
            ->assertSee('bk-t-chips', false)
            ->assertSee('bk-t-staff', false)
            ->assertSee('name="tasks['.$client->id.'][]"', false)
            ->assertSee('name="assignee['.$client->id.'][pickup]"', false)
            ->assertSee('name="target_date['.$client->id.']"', false);
    }

    public function test_target_planner_pages_render(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup']]], $period);

        $this->actingAs($staff)
            ->get($this->url('create', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee($period->rangeLabel())
            ->assertSee($client->business_name);

        $this->actingAs($staff)
            ->get($this->url('show', $plan))
            ->assertOk()
            ->assertSee($this->nounTitle().' Summary')
            ->assertSee($client->business_name)
            ->assertSee('Target vs Actual');

        $this->actingAs($staff)
            ->get($this->url('index', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee($period->rangeLabel())
            ->assertSee($client->business_name);
    }

    public function test_report_page_renders_with_period_summary(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $period = $this->currentPeriod();
        $plan = $this->createPlan($staff, [$client->id => ['tasks' => ['pickup', 'record']]], $period);

        $this->actingAs($staff)
            ->get($this->url('report', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee('Summary')
            ->assertSee('Uncollected / Unfinished')
            ->assertSee($client->business_name)
            ->assertSee($period->rangeLabel());
    }

    public function test_empty_period_renders_a_clear_state(): void
    {
        $staff = $this->staff();
        $period = $this->currentPeriod();

        $this->actingAs($staff)
            ->get($this->url('index', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee('No targets set for this', false);
    }

    public function test_module_titles_name_their_own_period(): void
    {
        $staff = $this->staff();
        $period = $this->currentPeriod();

        $this->actingAs($staff)
            ->get($this->url('index', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee($this->moduleTitle());

        $this->actingAs($staff)
            ->get($this->url('report', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee($this->moduleTitle());
    }

    // -----------------------------------------------------------------
    // Staff-centered assignment
    // -----------------------------------------------------------------

    /**
     * Saves one staff-centered batch through the real endpoint: a single staff
     * member, one target date, one task type and any number of clients.
     *
     * @param  array<int, int>  $clientIds
     */
    protected function bulkAssign(
        User $actor,
        User $assignee,
        string $taskType,
        array $clientIds,
        ?string $targetDate = null,
        ?BookkeepingPeriod $period = null,
        array $overrides = []
    ): Model {
        $period ??= $this->currentPeriod();

        $this->actingAs($actor)
            ->post(route($this->routePrefix().'.bulk-assign'), array_merge([
                $this->startField() => $period->startDate(),
                'assigned_staff_id' => $assignee->id,
                'task_type' => $taskType,
                'target_date' => $targetDate ?? $period->startDate(),
                'client_ids' => $clientIds,
            ], $overrides))
            ->assertRedirect();

        return $this->planClass()::query()
            ->whereDate($this->startColumn(), $period->startDate())
            ->firstOrFail();
    }

    /** A date inside the period but not on its first day. */
    protected function insidePeriod(int $daysIn): string
    {
        return \Illuminate\Support\Carbon::parse($this->currentPeriod()->startDate())
            ->addDays($daysIn)
            ->format('Y-m-d');
    }

    /** A date just outside the period, used to prove server-side range checks. */
    protected function outsidePeriod(): string
    {
        return \Illuminate\Support\Carbon::parse($this->currentPeriod()->startDate())
            ->subDay()
            ->format('Y-m-d');
    }

    /** The human label the planner shows for a task key. */
    protected function taskTypeLabel(string $key): string
    {
        return $this->targetClass()::TASK_TYPES[$key] ?? $key;
    }

    public function test_one_staff_member_can_be_assigned_many_clients_in_a_single_batch(): void
    {
        $angeli = $this->staff('Angeli');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $clientC = $this->client('Client C');
        $period = $this->currentPeriod();

        $plan = $this->bulkAssign(
            $angeli,
            $angeli,
            'pickup',
            [$clientA->id, $clientB->id, $clientC->id],
            $this->insidePeriod(4)
        );

        $this->assertSame(3, $plan->targets()->count());

        /* One selection of the staff member, three resulting assignments that all
           name her and carry the same batch target date. */
        foreach ([$clientA, $clientB, $clientC] as $client) {
            $target = $this->target($plan, 'pickup', $client->id);

            $this->assertSame($angeli->id, $target->assigned_staff_id);
            $this->assertSame($angeli->name, $target->assigned_staff_name);
            $this->assertSame(
                \Illuminate\Support\Carbon::parse($period->startDate())->addDays(4)->format('Y-m-d'),
                $target->target_date->format('Y-m-d')
            );
            $this->assertSame('pending', $target->actual_status);
        }
    }

    public function test_each_staff_member_keeps_an_independent_target_date_in_the_same_period(): void
    {
        $angeli = $this->staff('Angeli');
        $maria = $this->staff('Maria');
        $clientA = $this->client('Client A');
        $period = $this->currentPeriod();

        $angeliDate = \Illuminate\Support\Carbon::parse($period->startDate())->addDays(4)->format('Y-m-d');
        $mariaDate = \Illuminate\Support\Carbon::parse($period->startDate())->addDays(14)->format('Y-m-d');

        $this->bulkAssign($angeli, $angeli, 'pickup', [$clientA->id], $angeliDate);
        $plan = $this->bulkAssign($maria, $maria, 'record', [$clientA->id], $mariaDate);

        $pickup = $this->target($plan, 'pickup', $clientA->id);
        $record = $this->target($plan, 'record', $clientA->id);

        /* Same plan, same period, two different deadlines. */
        $this->assertSame($angeli->id, $pickup->assigned_staff_id);
        $this->assertSame($angeliDate, $pickup->target_date->format('Y-m-d'));

        $this->assertSame($maria->id, $record->assigned_staff_id);
        $this->assertSame($mariaDate, $record->target_date->format('Y-m-d'));

        $this->assertNotSame(
            $pickup->target_date->format('Y-m-d'),
            $record->target_date->format('Y-m-d')
        );
    }

    public function test_same_client_can_have_different_staff_for_different_tasks(): void
    {
        $angeli = $this->staff('Angeli');
        $maria = $this->staff('Maria');
        $john = $this->staff('John');
        $clientA = $this->client('Client A');
        $period = $this->currentPeriod();

        $this->bulkAssign($angeli, $angeli, 'pickup', [$clientA->id], $this->insidePeriod(3));
        $this->bulkAssign($maria, $maria, 'record', [$clientA->id], $this->insidePeriod(5));
        $plan = $this->bulkAssign($john, $john, 'return', [$clientA->id], $this->insidePeriod(7));

        $this->assertSame(3, $plan->targets()->where('client_id', $clientA->id)->count());

        $this->assertSame($angeli->id, $this->target($plan, 'pickup', $clientA->id)->assigned_staff_id);
        $this->assertSame($maria->id, $this->target($plan, 'record', $clientA->id)->assigned_staff_id);
        $this->assertSame($john->id, $this->target($plan, 'return', $clientA->id)->assigned_staff_id);
    }

    public function test_bulk_assignment_covers_every_task_type(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');
        $period = $this->currentPeriod();

        foreach (array_keys($this->targetClass()::TASK_TYPES) as $index => $taskType) {
            $this->bulkAssign($staff, $staff, $taskType, [$client->id], $this->insidePeriod($index + 1));
        }

        $plan = $this->findPlan($staff, $period);

        $this->assertSame(
            count($this->targetClass()::TASK_TYPES),
            $plan->targets()->where('client_id', $client->id)->count()
        );

        foreach (array_keys($this->targetClass()::TASK_TYPES) as $taskType) {
            $this->assertTrue(
                $plan->targets()->where('client_id', $client->id)->where('task_type', $taskType)->exists(),
                "Missing a target for the {$taskType} task."
            );
        }
    }

    public function test_bulk_assignment_updates_an_existing_target_instead_of_duplicating_it(): void
    {
        $angeli = $this->staff('Angeli');
        $maria = $this->staff('Maria');
        $client = $this->client('Client A');

        $this->bulkAssign($angeli, $angeli, 'pickup', [$client->id], $this->insidePeriod(2));
        $plan = $this->bulkAssign($maria, $maria, 'pickup', [$client->id], $this->insidePeriod(9));

        /* One client + one task type per period stays the rule, so the repeat
           submission moves the existing row rather than adding a second one. */
        $this->assertSame(1, $plan->targets()->where('client_id', $client->id)->where('task_type', 'pickup')->count());

        $target = $this->target($plan, 'pickup', $client->id);
        $this->assertSame($maria->id, $target->assigned_staff_id);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($this->currentPeriod()->startDate())->addDays(9)->format('Y-m-d'),
            $target->target_date->format('Y-m-d')
        );
    }

    public function test_bulk_assignment_rejects_a_target_date_outside_the_period(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $this->actingAs($staff)
            ->post(route($this->routePrefix().'.bulk-assign'), [
                $this->startField() => $this->currentPeriod()->startDate(),
                'assigned_staff_id' => $staff->id,
                'task_type' => 'pickup',
                'target_date' => $this->outsidePeriod(),
                'client_ids' => [$client->id],
            ])
            ->assertSessionHasErrors('target_date');

        $this->assertSame(0, $this->targetClass()::query()->count());
    }

    public function test_bulk_assignment_requires_at_least_one_client(): void
    {
        $staff = $this->staff('Angeli');

        $this->actingAs($staff)
            ->post(route($this->routePrefix().'.bulk-assign'), [
                $this->startField() => $this->currentPeriod()->startDate(),
                'assigned_staff_id' => $staff->id,
                'task_type' => 'pickup',
                'target_date' => $this->currentPeriod()->startDate(),
                'client_ids' => [],
            ])
            ->assertSessionHasErrors('client_ids');
    }

    public function test_bulk_assignment_will_not_name_a_client_account_as_the_owner(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $this->actingAs($staff)
            ->post(route($this->routePrefix().'.bulk-assign'), [
                $this->startField() => $this->currentPeriod()->startDate(),
                'assigned_staff_id' => $client->id,
                'task_type' => 'pickup',
                'target_date' => $this->currentPeriod()->startDate(),
                'client_ids' => [$client->id],
            ])
            ->assertSessionHasErrors('assigned_staff_id');
    }

    public function test_bulk_assignment_will_not_accept_a_staff_account_as_a_client(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $this->actingAs($staff)
            ->post(route($this->routePrefix().'.bulk-assign'), [
                $this->startField() => $this->currentPeriod()->startDate(),
                'assigned_staff_id' => $staff->id,
                'task_type' => 'pickup',
                'target_date' => $this->currentPeriod()->startDate(),
                'client_ids' => [$client->id, $staff->id],
            ])
            ->assertSessionHasErrors('client_ids');

        $this->assertSame(0, $this->targetClass()::query()->count());
    }

    public function test_a_client_cannot_use_the_staff_centered_assignment_endpoint(): void
    {
        $client = $this->client('Client A');

        $this->actingAs($client)
            ->post(route($this->routePrefix().'.bulk-assign'), [
                $this->startField() => $this->currentPeriod()->startDate(),
                'assigned_staff_id' => $client->id,
                'task_type' => 'pickup',
                'target_date' => $this->currentPeriod()->startDate(),
                'client_ids' => [$client->id],
            ])
            ->assertForbidden();
    }

    public function test_bulk_assignments_stay_inside_their_own_period(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');
        $current = $this->currentPeriod();
        $previous = $this->previousPeriod();

        $this->bulkAssign($staff, $staff, 'pickup', [$client->id], null, $current);

        $currentPlan = $this->findPlan($staff, $current);

        $this->assertTrue($currentPlan->targets()->exists());
        $this->assertNull(
            $this->planClass()::query()->whereDate($this->startColumn(), $previous->startDate())->first(),
            'Assigning in one period must not create a plan for another.'
        );
    }

    public function test_bulk_assignment_feeds_the_existing_summary_counts(): void
    {
        $staff = $this->staff('Angeli');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $period = $this->currentPeriod();

        $this->bulkAssign($staff, $staff, 'pickup', [$clientA->id, $clientB->id], $this->insidePeriod(3));

        $this->actingAs($staff)
            ->get($this->url('index', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertViewHas('stats', function (array $stats) {
                $this->assertSame(2, $stats['targets']);
                $this->assertSame(2, $stats['clients']);
                $this->assertSame(0, $stats['completed']);
                $this->assertSame(2, $stats['pending']);

                return true;
            });
    }

    public function test_bulk_assignment_is_recorded_in_the_plan_history(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');

        $plan = $this->bulkAssign($staff, $staff, 'pickup', [$client->id], $this->insidePeriod(4));

        $this->assertTrue(
            ActivityLog::query()
                ->where('action', $this->activityPrefix().'.target_added')
                ->where('description', 'like', '%Angeli%')
                ->exists()
        );

        $this->actingAs($staff)
            ->get($this->url('history', $plan))
            ->assertOk()
            ->assertSee('Target added');
    }

    public function test_the_planner_page_offers_the_staff_centered_assignment_panel(): void
    {
        $staff = $this->staff('Angeli');
        $client = $this->client('Client A');
        $url = $this->url('create', [$this->queryKey() => $this->currentPeriod()->key()]);

        $this->actingAs($staff)
            ->get($url)
            ->assertOk()
            ->assertSee(route($this->routePrefix().'.bulk-assign'), false)
            ->assertSee('Assign Bookkeeping Tasks')
            ->assertSee('Assign a staff member, target date, task, and multiple clients.')
            ->assertSee('Within this '.strtolower($this->periodUnit()).':')
            ->assertSee('Select Clients')
            ->assertSee('clients selected')
            ->assertSee('Add Assignment')
            ->assertSee('name="assigned_staff_id"', false)
            ->assertSee('name="target_date"', false)
            ->assertSee('name="task_type"', false)
            ->assertSee('name="client_ids[]"', false)
            ->assertSee((string) $client->id, false);

        /* The original client matrix is still on the page, untouched. */
        $this->actingAs($staff)
            ->get($url)
            ->assertOk()
            ->assertSee('Select Clients')
            ->assertSee('name="clients[]"', false)
            ->assertSee('name="tasks[', false);
    }

    public function test_the_planner_summarises_existing_assignments_in_the_app_table_style(): void
    {
        $staff = $this->staff('Angeli');
        $clientA = $this->client('Client A');
        $clientB = $this->client('Client B');
        $period = $this->currentPeriod();

        $this->bulkAssign($staff, $staff, 'pickup', [$clientA->id, $clientB->id], $this->insidePeriod(3));

        /* One row per staff + date + task batch, in the shared .table markup. */
        $this->actingAs($staff)
            ->get($this->url('create', [$this->queryKey() => $period->key()]))
            ->assertOk()
            ->assertSee('Current Assignments')
            ->assertSee('<th>Staff</th>', false)
            ->assertSee('<th>Target Date</th>', false)
            ->assertSee('<th>Task</th>', false)
            ->assertSee('<th>Clients</th>', false)
            ->assertSee('<th>Status</th>', false)
            ->assertSee('Angeli')
            ->assertSee($this->taskTypeLabel('pickup'));
    }

    public function test_the_planner_shows_only_one_assignment_panel(): void
    {
        $staff = $this->staff('Angeli');
        $this->client('Client A');
        $url = $this->url('create', [$this->queryKey() => $this->currentPeriod()->key()]);

        $html = $this->actingAs($staff)->get($url)->assertOk()->getContent();

        /* Exactly one staff-centered assignment panel: one heading, one form,
           one submit button. */
        $this->assertSame(1, substr_count($html, 'Assign Bookkeeping Tasks'));
        $this->assertSame(1, substr_count($html, 'id="assignForm"'));
        $this->assertSame(1, substr_count($html, 'id="assignSubmitBtn"'));

        /* The client matrix is still on the page and still posts to .store, but
           it is folded away behind its own toggle instead of reading as a second
           assignment panel. */
        $this->assertSame(1, substr_count($html, 'id="targetForm"'));
        $this->assertSame(1, substr_count($html, 'Client-by-client planner'));
        $this->assertStringContainsString('<details class="bk-t-adv">', $html);
        $this->assertStringNotContainsString('<details class="bk-t-adv" open', $html);
    }
}
