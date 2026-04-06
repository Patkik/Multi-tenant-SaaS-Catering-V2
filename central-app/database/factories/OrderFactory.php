<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'customer_name' => fake()->name(),
            'items_count' => fake()->numberBetween(1, 120),
            'total_amount' => fake()->randomFloat(2, 10, 5000),
            'order_type' => fake()->randomElement(['Delivery', 'Pickup', 'Catering']),
            'status' => fake()->randomElement(['Pending', 'Preparing', 'Ready', 'Delivered']),
            'ordered_at' => now()->subHours(fake()->numberBetween(0, 72)),
        ];
    }
}