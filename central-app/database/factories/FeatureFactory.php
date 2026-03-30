<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feature>
 */
class FeatureFactory extends Factory
{
    protected $model = Feature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'category' => 'Core',
            'default_enabled' => true,
            'requires_plan' => null,
            'deprecated_at' => null,
        ];
    }
}
