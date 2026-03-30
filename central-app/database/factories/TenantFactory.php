<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug();

        return [
            'name' => fake()->company(),
            'domain' => $slug.'.localhost:8080',
            'database_name' => 'tenant_'.$slug,
            'plan_code' => 'starter',
            'plan_entitlements' => ['starter'],
        ];
    }
}
