<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceReading>
 */
class DeviceReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_on' => $this->faker->boolean(),
            'voltage' => $this->faker->randomFloat(2, 100, 250),
            'consumption' => $this->faker->randomFloat(4, 0, 1000),
        ];
    }
}
