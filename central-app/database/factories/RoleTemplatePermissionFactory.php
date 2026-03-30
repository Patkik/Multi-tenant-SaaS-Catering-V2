<?php

namespace Database\Factories;

use App\Models\RoleTemplate;
use App\Models\RoleTemplatePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTemplatePermission>
 */
class RoleTemplatePermissionFactory extends Factory
{
    protected $model = RoleTemplatePermission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_template_id' => RoleTemplate::factory(),
            'role_name' => 'EventSupervisor',
            'permission' => fake()->randomElement([
                'events.view',
                'events.update',
                'staff.assign',
            ]),
        ];
    }
}
