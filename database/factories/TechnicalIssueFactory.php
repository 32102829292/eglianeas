<?php

namespace Database\Factories;

use App\Models\TechnicalIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicalIssue>
 */
class TechnicalIssueFactory extends Factory
{
    protected $model = TechnicalIssue::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state([
                'role' => User::ROLE_STAFF,
            ]),
            'user_role' => User::ROLE_STAFF,
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(array_keys(TechnicalIssue::CATEGORIES)),
            'description' => fake()->paragraph(),
            'page_url' => fake()->url(),
            'priority' => TechnicalIssue::PRIORITY_MEDIUM,
            'status' => TechnicalIssue::STATUS_OPEN,
        ];
    }
}
