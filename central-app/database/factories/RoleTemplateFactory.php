<?php

namespace Database\Factories;

use App\Models\RoleTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTemplate>
 */
class RoleTemplateFactory extends Factory
{
    protected $model = RoleTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'is_system_default' => false,
            'created_by_admin' => fake()->safeEmail(),
            'metadata' => null,
        ];
    }
}
