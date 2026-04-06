<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\OrdersController;
use App\Models\Order;
use App\Models\Tenant;
use Database\Seeders\TenantRBACSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantOrdersCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TenantRBACSeeder::class);
    }

    public function test_index_is_tenant_scoped_and_supports_filters(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-a.localhost']);
        $otherTenant = Tenant::factory()->create(['domain' => 'orders-b.localhost']);

        $targetOrder = Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'order_number' => 'ORD-20260406-0001',
            'customer_name' => 'Acme Catering',
            'status' => 'Pending',
            'order_type' => 'Delivery',
            'ordered_at' => now(),
        ]);

        Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Yesterday Delivery',
            'status' => 'Pending',
            'order_type' => 'Delivery',
            'ordered_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'tenant_id' => (string) $otherTenant->getKey(),
            'customer_name' => 'Other Tenant Order',
            'status' => 'Pending',
            'order_type' => 'Delivery',
            'ordered_at' => now(),
        ]);

        $response = $this->withSession($this->tenantSession($tenant, 'manager'))
            ->get('http://orders-a.localhost/orders?search=Acme&status=Pending&type=Delivery&today=1');

        $response
            ->assertOk()
            ->assertSeeText('Acme Catering')
            ->assertSeeText($targetOrder->order_number)
            ->assertDontSeeText('Other Tenant Order')
            ->assertDontSeeText('Yesterday Delivery');
    }

    public function test_tenant_staff_can_create_but_cannot_update_or_delete_orders(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-flow.localhost']);

        $this->withSession($this->tenantSession($tenant, 'staff'))->post('http://orders-flow.localhost/orders', [
            'customer_name' => 'Maria Santos',
            'items_count' => 12,
            'total_amount' => 480.50,
            'order_type' => 'Delivery',
            'status' => 'Pending',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect('http://orders-flow.localhost/orders');

        $order = Order::query()->where('tenant_id', (string) $tenant->getKey())->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Maria Santos',
            'status' => 'Pending',
        ]);

        $this->withSession($this->tenantSession($tenant, 'staff'))->put("http://orders-flow.localhost/orders/{$order->id}", [
            'customer_name' => 'Maria Santos Updated',
            'items_count' => 16,
            'total_amount' => 600,
            'order_type' => 'Catering',
            'status' => 'Preparing',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->withSession($this->tenantSession($tenant, 'staff'))
            ->delete("http://orders-flow.localhost/orders/{$order->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Maria Santos',
            'status' => 'Pending',
        ]);

        $this->withSession($this->tenantSession($tenant, 'staff'))
            ->get('http://orders-flow.localhost/orders')
            ->assertOk()
            ->assertDontSee('title="Edit"', false);

        $this->withSession($this->tenantSession($tenant, 'staff'))
            ->get("http://orders-flow.localhost/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('>Edit<', false);
    }

    public function test_manager_can_update_orders(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-manager.localhost']);
        $order = Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Manager Update Baseline',
            'status' => 'Pending',
            'order_type' => 'Delivery',
        ]);

        $this->withSession($this->tenantSession($tenant, 'manager'))->put("http://orders-manager.localhost/orders/{$order->id}", [
            'customer_name' => 'Manager Updated Order',
            'items_count' => 16,
            'total_amount' => 600,
            'order_type' => 'Catering',
            'status' => 'Preparing',
            'ordered_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect("http://orders-manager.localhost/orders/{$order->id}");

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Manager Updated Order',
            'status' => 'Preparing',
            'order_type' => 'Catering',
        ]);
    }

    public function test_only_admin_can_delete_order(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-delete.localhost']);
        $order = Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
        ]);

        $this->withSession($this->tenantSession($tenant, 'manager'))
            ->delete("http://orders-delete.localhost/orders/{$order->id}")
            ->assertForbidden();

        $this->withSession($this->tenantSession($tenant, 'admin'))
            ->delete("http://orders-delete.localhost/orders/{$order->id}")
            ->assertRedirect('http://orders-delete.localhost/orders');

        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
        ]);
    }

    public function test_cashier_can_create_but_cannot_update_or_delete_orders(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-cashier.localhost']);
        $order = Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
        ]);

        $this->withSession($this->tenantSession($tenant, 'cashier'))
            ->post('http://orders-cashier.localhost/orders', [
                'customer_name' => 'Cashier Create Attempt',
                'items_count' => 2,
                'total_amount' => 100,
                'order_type' => 'Delivery',
                'status' => 'Pending',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect('http://orders-cashier.localhost/orders');

        $this->assertDatabaseHas('orders', [
            'tenant_id' => (string) $tenant->getKey(),
            'customer_name' => 'Cashier Create Attempt',
            'status' => 'Pending',
        ]);

        $this->withSession($this->tenantSession($tenant, 'cashier'))
            ->put("http://orders-cashier.localhost/orders/{$order->id}", [
                'customer_name' => 'Cashier Update Attempt',
                'items_count' => 3,
                'total_amount' => 120,
                'order_type' => 'Pickup',
                'status' => 'Preparing',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->withSession($this->tenantSession($tenant, 'cashier'))
            ->delete("http://orders-cashier.localhost/orders/{$order->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
        ]);

        $this->withSession($this->tenantSession($tenant, 'cashier'))
            ->get('http://orders-cashier.localhost/orders')
            ->assertOk()
            ->assertDontSee('title="Edit"', false);

        $this->withSession($this->tenantSession($tenant, 'cashier'))
            ->get("http://orders-cashier.localhost/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('>Edit<', false);
    }

    public function test_unknown_role_is_denied_by_default_for_create_update_and_delete(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-unknown-role.localhost']);
        $order = Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
        ]);

        $this->withSession($this->tenantSession($tenant, 'unknown-role'))
            ->post('http://orders-unknown-role.localhost/orders', [
                'customer_name' => 'Unknown Role Create Attempt',
                'items_count' => 2,
                'total_amount' => 100,
                'order_type' => 'Delivery',
                'status' => 'Pending',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->withSession($this->tenantSession($tenant, 'unknown-role'))
            ->put("http://orders-unknown-role.localhost/orders/{$order->id}", [
                'customer_name' => 'Unknown Role Update Attempt',
                'items_count' => 3,
                'total_amount' => 120,
                'order_type' => 'Pickup',
                'status' => 'Preparing',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertForbidden();

        $this->withSession($this->tenantSession($tenant, 'unknown-role'))
            ->delete("http://orders-unknown-role.localhost/orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_cross_tenant_order_access_is_blocked_for_show_edit_update_delete(): void
    {
        $tenantA = Tenant::factory()->create(['domain' => 'orders-a-scope.localhost']);
        $tenantB = Tenant::factory()->create(['domain' => 'orders-b-scope.localhost']);
        $tenantBOrder = Order::factory()->create([
            'tenant_id' => (string) $tenantB->getKey(),
        ]);

        $baseUrl = 'http://orders-a-scope.localhost/orders/'.$tenantBOrder->id;

        $this->withSession($this->tenantSession($tenantA, 'manager'))
            ->get($baseUrl)
            ->assertNotFound();

        $this->withSession($this->tenantSession($tenantA, 'manager'))
            ->get($baseUrl.'/edit')
            ->assertNotFound();

        $this->withSession($this->tenantSession($tenantA, 'manager'))
            ->put($baseUrl, [
                'customer_name' => 'Cross Tenant Update Attempt',
                'items_count' => 4,
                'total_amount' => 200,
                'order_type' => 'Delivery',
                'status' => 'Preparing',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertNotFound();

        $this->withSession($this->tenantSession($tenantA, 'admin'))
            ->delete($baseUrl)
            ->assertNotFound();
    }

    public function test_store_retries_when_order_number_collides_and_still_succeeds(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'orders-collision.localhost']);
        $today = now()->format('Ymd');

        Order::factory()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'order_number' => sprintf('ORD-%s-%04d', $today, 1234),
        ]);

        $controllerMock = \Mockery::mock(OrdersController::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $controllerMock->shouldReceive('nextOrderNumberSuffix')->andReturn(1234, 5678);
        $this->app->instance(OrdersController::class, $controllerMock);

        $this->withSession($this->tenantSession($tenant, 'staff'))
            ->post('http://orders-collision.localhost/orders', [
                'customer_name' => 'Collision Retry Success',
                'items_count' => 5,
                'total_amount' => 250,
                'order_type' => 'Delivery',
                'status' => 'Pending',
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect('http://orders-collision.localhost/orders');

        $this->assertDatabaseHas('orders', [
            'tenant_id' => (string) $tenant->getKey(),
            'order_number' => sprintf('ORD-%s-%04d', $today, 5678),
            'customer_name' => 'Collision Retry Success',
        ]);
    }

    public function test_orders_routes_support_host_port_variants_in_tenant_route_guard(): void
    {
        $tenantWithoutPort = Tenant::factory()->create(['domain' => 'orders-guard-no-port.localhost']);
        $orderWithoutPort = Order::factory()->create([
            'tenant_id' => (string) $tenantWithoutPort->getKey(),
            'customer_name' => 'Guard Host Variant',
            'order_number' => 'ORD-GUARD-8080',
        ]);

        $this->withSession($this->tenantSession($tenantWithoutPort, 'manager'))
            ->get('http://orders-guard-no-port.localhost:8080/orders')
            ->assertOk()
            ->assertSeeText('Guard Host Variant');

        $this->withSession($this->tenantSession($tenantWithoutPort, 'manager'))
            ->get(sprintf('http://orders-guard-no-port.localhost:8080/orders/%d', $orderWithoutPort->id))
            ->assertOk()
            ->assertSeeText($orderWithoutPort->order_number);

        $tenantWithPort = Tenant::factory()->create(['domain' => 'orders-guard-with-port.localhost:8080']);
        $orderWithPort = Order::factory()->create([
            'tenant_id' => (string) $tenantWithPort->getKey(),
            'customer_name' => 'Guard Port Variant',
            'order_number' => 'ORD-GUARD-NO-PORT',
        ]);

        $this->withSession($this->tenantSession($tenantWithPort, 'manager'))
            ->get('http://orders-guard-with-port.localhost/orders')
            ->assertOk()
            ->assertSeeText('Guard Port Variant');

        $this->withSession($this->tenantSession($tenantWithPort, 'manager'))
            ->get(sprintf('http://orders-guard-with-port.localhost/orders/%d', $orderWithPort->id))
            ->assertOk()
            ->assertSeeText($orderWithPort->order_number);
    }

    public function test_orders_index_uses_route_guard_resolved_tenant_for_same_host_different_ports(): void
    {
        $tenantNoPort = Tenant::factory()->create(['domain' => 'same-host.localhost']);
        $tenantWithPort = Tenant::factory()->create(['domain' => 'same-host.localhost:8080']);

        Order::factory()->create([
            'tenant_id' => (string) $tenantNoPort->getKey(),
            'customer_name' => 'No Port Tenant Order',
            'order_number' => 'ORD-SAMEHOST-NOPORT',
            'ordered_at' => now(),
        ]);

        Order::factory()->create([
            'tenant_id' => (string) $tenantWithPort->getKey(),
            'customer_name' => 'Port Tenant Order',
            'order_number' => 'ORD-SAMEHOST-PORT',
            'ordered_at' => now(),
        ]);

        $this->withSession($this->tenantSession($tenantWithPort, 'manager'))
            ->get('http://same-host.localhost:8080/orders')
            ->assertOk()
            ->assertSeeText('Port Tenant Order')
            ->assertSeeText('ORD-SAMEHOST-PORT')
            ->assertDontSeeText('No Port Tenant Order')
            ->assertDontSeeText('ORD-SAMEHOST-NOPORT');
    }

    /**
     * @return array<string, string>
     */
    private function tenantSession(Tenant $tenant, string $role): array
    {
        return [
            'tenant_authenticated_domain' => (string) $tenant->domain,
            'tenant_role' => $role,
            'tenant_user_email' => 'tester@tenant.local',
            'tenant_user_name' => 'Tenant Tester',
        ];
    }
}