<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportRequest;
use App\Models\Support;
use App\Models\Tenant;
use App\Services\SupportMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentralSupportController extends Controller
{
    public function __construct(
        private readonly SupportMessageService $supportMessageService,
    ) {
    }

    public function store(StoreSupportRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $this->supportMessageService->send('central', $validated, [
            'app_label' => 'Central Platform',
            'app_version' => config('app.version'),
            'workspace_name' => 'Central Platform',
            'workspace_id' => 'central',
            'contact_name' => $validated['contact_name'] ?? $user?->name,
            'contact_email' => $validated['contact_email'] ?? $user?->email,
            'user_role' => $validated['user_role'] ?? implode(', ', $user?->getRoleNames()->values()->all() ?? []),
            'page_path' => $validated['page_path'] ?? $request->path(),
            'request_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'data' => [
                'message' => 'Your support request has been sent to the central team.',
            ],
        ]);
    }

    /**
     * List support submissions (central or tenant)
     * Query params: source (tenant|central), search, per_page, page
     */
    public function index(Request $request): JsonResponse
    {
        $source = $request->query('source');
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        $query = DB::connection($centralConnection)
            ->table('support_messages')
            ->select([
                'id',
                'source',
                'category',
                'subject',
                'message',
                'contact_name',
                'contact_email',
                'workspace_name',
                'workspace_id',
                'tenant_id',
                'page_path',
                'app_version',
                'user_role',
                'tenant_domain',
                'request_ip',
                'user_agent',
                'created_at',
            ])
            ->when(in_array($source, ['tenant', 'central'], true), function ($builder) use ($source) {
                $builder->where('source', $source);
            })
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%')
                        ->orWhere('workspace_name', 'like', '%'.$search.'%')
                        ->orWhere('contact_name', 'like', '%'.$search.'%')
                        ->orWhere('contact_email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => [
                'items' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * Get tenant support submissions only
     */
    public function tenantSubmissions(Request $request): JsonResponse
    {
        return $this->index($request->merge(['source' => 'tenant']));
    }

    /**
     * Get support submissions for a specific tenant
     */
    public function byTenant(Request $request, Tenant $tenant): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = Support::where('tenant_id', $tenant->id)
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner
                        ->where('subject', 'like', '%'.$search.'%')
                        ->orWhere('message', 'like', '%'.$search.'%')
                        ->orWhere('contact_name', 'like', '%'.$search.'%')
                        ->orWhere('contact_email', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'items' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * Get a single support message with details
     */
    public function show(Support $support): JsonResponse
    {
        return response()->json([
            'data' => $support->load('tenant'),
        ]);
    }

    /**
     * Get support statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $centralConnection = (string) config('tenancy.database.central_connection', config('database.default'));

        $stats = [
            'total_submissions' => DB::connection($centralConnection)->table('support_messages')->count(),
            'tenant_submissions' => DB::connection($centralConnection)->table('support_messages')->where('source', 'tenant')->count(),
            'central_submissions' => DB::connection($centralConnection)->table('support_messages')->where('source', 'central')->count(),
            'by_category' => DB::connection($centralConnection)
                ->table('support_messages')
                ->selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
            'recent_submissions' => DB::connection($centralConnection)
                ->table('support_messages')
                ->select(['id', 'source', 'subject', 'contact_name', 'workspace_name', 'created_at'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ];

        return response()->json(['data' => $stats]);
    }
}
