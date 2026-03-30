<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\FeatureOverride;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureOverride>
 */
class FeatureOverrideFactory extends Factory
{
    protected $model = FeatureOverride::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'feature_id' => Feature::factory(),
            'is_enabled' => true,
            'reason' => fake()->sentence(),
            'set_by_admin' => fake()->safeEmail(),
            'set_at' => now(),
            'expires_at' => null,
        ];
    }
}
