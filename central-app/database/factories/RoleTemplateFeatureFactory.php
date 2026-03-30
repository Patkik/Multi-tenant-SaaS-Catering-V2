<?php

namespace Database\Factories;

use App\Models\Feature;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTemplateFeature>
 */
class RoleTemplateFeatureFactory extends Factory
{
    protected $model = RoleTemplateFeature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_template_id' => RoleTemplate::factory(),
            'role_name' => 'EventSupervisor',
            'feature_key' => static fn (): string => Feature::factory()->create()->name,
            'is_enabled' => true,
        ];
    }
}
