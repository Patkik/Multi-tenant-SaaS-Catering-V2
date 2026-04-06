<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Services\RBACService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrdersController extends Controller
{
    /**
     * @var list<string>
     */
    private const ORDER_TYPES = ['Delivery', 'Pickup', 'Catering'];

    /**
     * @var list<string>
     */
    private const ORDER_STATUSES = ['Pending', 'Preparing', 'Ready', 'Delivered'];

    public function index(Request $request): View
    {
        $this->ensureCanViewOrders($request);
        $tenant = $this->resolveTenantFromRequest($request);
        $filters = $this->extractFilters($request);

        $ordersQuery = Order::query()->where('tenant_id', (string) $tenant->getKey());

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $ordersQuery->where(function ($query) use ($search): void {
                $query->where('order_number', 'like', $search)
                    ->orWhere('customer_name', 'like', $search);
            });
        }

        if ($filters['status'] !== '') {
            $ordersQuery->where('status', $filters['status']);
        }

        if ($filters['type'] !== '') {
            $ordersQuery->where('order_type', $filters['type']);
        }

        if ($filters['today']) {
            $ordersQuery->whereDate('ordered_at', today());
        }

        $orders = $ordersQuery
            ->orderByDesc('ordered_at')
            ->paginate(10)
            ->withQueryString();

        $statsQuery = Order::query()->where('tenant_id', (string) $tenant->getKey());
        $todayOrders = (clone $statsQuery)->whereDate('ordered_at', today())->count();
        $pendingOrders = (clone $statsQuery)->where('status', 'Pending')->count();
        $inProgressOrders = (clone $statsQuery)->whereIn('status', ['Preparing', 'Ready'])->count();
        $completedOrders = (clone $statsQuery)->where('status', 'Delivered')->count();

        return view('tenant.orders', [
            ...$this->buildTenantViewData($request, $tenant),
            'orders' => $orders,
            'filters' => $filters,
            'orderStatuses' => self::ORDER_STATUSES,
            'orderTypes' => self::ORDER_TYPES,
            'statusColors' => [
                'Pending' => 'bg-gray-100 text-gray-700',
                'Preparing' => 'bg-amber-100 text-amber-700',
                'Ready' => 'bg-blue-100 text-blue-700',
                'Delivered' => 'bg-green-100 text-green-700',
            ],
            'stats' => [
                'today_orders' => $todayOrders,
                'pending_orders' => $pendingOrders,
                'in_progress_orders' => $inProgressOrders,
                'completed_orders' => $completedOrders,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $this->ensureCanCreateOrders($request);

        return view('tenant.orders.create', [
            ...$this->buildTenantViewData($request, $tenant),
            'orderStatuses' => self::ORDER_STATUSES,
            'orderTypes' => self::ORDER_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $this->ensureCanCreateOrders($request);

        $validated = $this->validateOrderInput($request);

        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                Order::query()->create([
                    'tenant_id' => (string) $tenant->getKey(),
                    'order_number' => $this->generateOrderNumber(),
                    ...$validated,
                ]);

                return redirect()
                    ->route('tenant.orders.index')
                    ->with('success', 'Order created successfully.');
            } catch (QueryException $exception) {
                if (! $this->isOrderNumberCollision($exception)) {
                    throw $exception;
                }
            }
        }

        return back()->withInput()->withErrors([
            'order_number' => 'Unable to create a unique order number right now. Please try again.',
        ]);
    }

    public function show(Request $request, int $order): View
    {
        $this->ensureCanViewOrders($request);
        $tenant = $this->resolveTenantFromRequest($request);
        $orderModel = $this->findTenantOrderOrFail($tenant, $order);

        return view('tenant.orders.show', [
            ...$this->buildTenantViewData($request, $tenant),
            'order' => $orderModel,
        ]);
    }

    public function edit(Request $request, int $order): View
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $this->ensureCanUpdateOrders($request);
        $orderModel = $this->findTenantOrderOrFail($tenant, $order);

        return view('tenant.orders.edit', [
            ...$this->buildTenantViewData($request, $tenant),
            'order' => $orderModel,
            'orderStatuses' => self::ORDER_STATUSES,
            'orderTypes' => self::ORDER_TYPES,
        ]);
    }

    public function update(Request $request, int $order): RedirectResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $this->ensureCanUpdateOrders($request);
        $orderModel = $this->findTenantOrderOrFail($tenant, $order);

        $validated = $this->validateOrderInput($request);
        $orderModel->update($validated);

        return redirect()
            ->route('tenant.orders.show', $orderModel)
            ->with('success', 'Order updated successfully.');
    }

    public function destroy(Request $request, int $order): RedirectResponse
    {
        $tenant = $this->resolveTenantFromRequest($request);
        $this->ensureCanDeleteOrders($request);
        $orderModel = $this->findTenantOrderOrFail($tenant, $order);

        $orderModel->delete();

        return redirect()
            ->route('tenant.orders.index')
            ->with('success', 'Order deleted successfully.');
    }

    /**
     * @return array{search: string, status: string, type: string, today: bool}
     */
    private function extractFilters(Request $request): array
    {
        $status = (string) $request->query('status', '');
        if (! in_array($status, self::ORDER_STATUSES, true)) {
            $status = '';
        }

        $type = (string) $request->query('type', '');
        if (! in_array($type, self::ORDER_TYPES, true)) {
            $type = '';
        }

        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $status,
            'type' => $type,
            'today' => $request->boolean('today'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateOrderInput(Request $request): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'items_count' => ['required', 'integer', 'min:1'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'order_type' => ['required', 'in:Delivery,Pickup,Catering'],
            'status' => ['required', 'in:Pending,Preparing,Ready,Delivered'],
            'ordered_at' => ['required', 'date'],
        ]);
    }

    private function resolveTenantFromRequest(Request $request): Tenant
    {
        $resolvedTenant = $request->attributes->get('resolved_tenant');
        if ($resolvedTenant instanceof Tenant) {
            return $resolvedTenant;
        }

        $httpHost = strtolower((string) $request->getHttpHost());
        $host = strtolower((string) $request->getHost());
        $authenticatedDomain = strtolower(trim((string) $request->session()->get('tenant_authenticated_domain', '')));

        $domainCandidates = array_values(array_filter(array_unique([
            $authenticatedDomain,
            $httpHost,
            $host,
        ])));

        foreach ($domainCandidates as $domainCandidate) {
            $tenant = Tenant::query()
                ->whereRaw('LOWER(domain) = ?', [$domainCandidate])
                ->first();

            if ($tenant !== null) {
                return $tenant;
            }
        }

        $normalizedHost = $this->normalizeHost($httpHost !== '' ? $httpHost : $host);
        $requestPort = $request->getPort();
        $hasRequestPort = is_int($requestPort) && $requestPort > 0;

        if ($normalizedHost !== '') {
            if ($hasRequestPort) {
                $tenantWithPort = Tenant::query()
                    ->whereRaw('LOWER(domain) = ?', [sprintf('%s:%d', $normalizedHost, $requestPort)])
                    ->first();

                if ($tenantWithPort !== null) {
                    return $tenantWithPort;
                }
            }

            $tenantWithoutPort = Tenant::query()
                ->whereRaw('LOWER(domain) = ?', [$normalizedHost])
                ->first();

            if ($tenantWithoutPort !== null) {
                return $tenantWithoutPort;
            }

            if (! $hasRequestPort) {
                $tenantWithUniqueHostPort = Tenant::query()
                    ->whereRaw('LOWER(domain) LIKE ?', [sprintf('%s:%%', $normalizedHost)])
                    ->orderBy('domain')
                    ->limit(2)
                    ->get();

                if ($tenantWithUniqueHostPort->count() === 1) {
                    return $tenantWithUniqueHostPort->first();
                }
            }
        }

        abort(404);
    }

    private function findTenantOrderOrFail(Tenant $tenant, int $orderId): Order
    {
        return Order::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->findOrFail($orderId);
    }

    private function ensureCanViewOrders(Request $request): void
    {
        abort_unless(
            $this->hasOrderPermission($request, ['orders.view']),
            403,
            'View permission is required for orders.'
        );
    }

    private function ensureCanCreateOrders(Request $request): void
    {
        abort_unless(
            $this->hasOrderPermission($request, ['orders.create', 'orders.manage']),
            403,
            'Create permission is required for orders.'
        );
    }

    private function ensureCanUpdateOrders(Request $request): void
    {
        abort_unless(
            $this->hasOrderPermission($request, ['orders.update', 'orders.manage']),
            403,
            'Update permission is required for orders.'
        );
    }

    private function ensureCanDeleteOrders(Request $request): void
    {
        abort_unless(
            $this->hasOrderPermission($request, ['orders.delete', 'orders.manage']),
            403,
            'Delete permission is required for orders.'
        );
    }

    /**
     * @param list<string> $permissionNames
     */
    private function hasOrderPermission(Request $request, array $permissionNames): bool
    {
        $tenantRoleName = trim((string) $request->session()->get('tenant_role', ''));
        if ($tenantRoleName === '') {
            return false;
        }

        if (! $this->rbacTablesAreAvailable()) {
            return false;
        }

        $tenantRole = TenantRole::query()->where('name', $tenantRoleName)->first();
        if (! $tenantRole instanceof TenantRole) {
            return false;
        }

        /** @var RBACService $rbacService */
        $rbacService = app(RBACService::class);

        return $rbacService->hasAnyPermission($tenantRole, $permissionNames);
    }

    private function rbacTablesAreAvailable(): bool
    {
        return Schema::hasTable('tenant_roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_permissions');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTenantViewData(Request $request, Tenant $tenant): array
    {
        $tenantRole = (string) $request->session()->get('tenant_role', 'staff');
        $canCreateOrders = $this->hasOrderPermission($request, ['orders.create', 'orders.manage']);
        $canUpdateOrders = $this->hasOrderPermission($request, ['orders.update', 'orders.manage']);
        $canDeleteOrders = $this->hasOrderPermission($request, ['orders.delete', 'orders.manage']);
        $tenantRoleLabel = match ($tenantRole) {
            'admin' => 'Admin',
            'manager' => 'Manager',
            'cashier' => 'Cashier',
            default => 'Staff',
        };

        return [
            'tenant' => $tenant,
            'tenantRole' => $tenantRole,
            'tenantRoleLabel' => $tenantRoleLabel,
            'tenantUserEmail' => (string) $request->session()->get('tenant_user_email', 'unknown@tenant.local'),
            'tenantUserName' => (string) $request->session()->get('tenant_user_name', 'User'),
            'canCreateOrders' => $canCreateOrders,
            'canUpdateOrders' => $canUpdateOrders,
            'canDeleteOrders' => $canDeleteOrders,
            // Keep UI behavior aligned with authorization checks.
            'canEdit' => $canUpdateOrders,
            'canDelete' => $canDeleteOrders,
        ];
    }

    private function generateOrderNumber(): string
    {
        return sprintf('ORD-%s-%04d', now()->format('Ymd'), $this->nextOrderNumberSuffix());
    }

    protected function nextOrderNumberSuffix(): int
    {
        return random_int(0, 9999);
    }

    private function isOrderNumberCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['1062', '2067'], true)
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry');

        if (! $isUniqueViolation) {
            return false;
        }

        return str_contains($message, 'order_number') && str_contains($message, 'tenant_id');
    }

    private function normalizeHost(string $value): string
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return '';
        }

        if (! str_contains($normalized, '://')) {
            $normalized = 'http://'.$normalized;
        }

        $host = parse_url($normalized, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return '';
        }

        return strtolower($host);
    }
}