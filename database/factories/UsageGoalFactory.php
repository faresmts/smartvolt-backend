<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageGoal>
 */
class UsageGoalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'target_kwh' => $this->faker->randomFloat(2, 50, 500),
            'period' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
        ];
    }
}
