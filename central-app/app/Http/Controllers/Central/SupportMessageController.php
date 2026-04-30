<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Support;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportMessageController extends Controller
{
    /**
     * Display a listing of tenant support messages
     */
    public function indexTenant(Request $request): View
    {
        $messages = Support::tenantSubmissions()
            ->with('tenant')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total_tenant_submissions' => Support::tenantSubmissions()->count(),
            'total_central_submissions' => Support::centralSubmissions()->count(),
            'total_all' => Support::count(),
        ];

        return view('central.support-messages-tenant', [
            'messages' => $messages,
            'stats' => $stats,
        ]);
    }

    /**
     * Display a listing of central support messages
     */
    public function indexCentral(Request $request): View
    {
        $messages = Support::centralSubmissions()
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'total_tenant_submissions' => Support::tenantSubmissions()->count(),
            'total_central_submissions' => Support::centralSubmissions()->count(),
            'total_all' => Support::count(),
        ];

        return view('central.support-messages-central', [
            'messages' => $messages,
            'stats' => $stats,
        ]);
    }

    /**
     * Display a single support message
     */
    public function show(Support $support): View
    {
        return view('central.support-message-detail', [
            'message' => $support,
            'tenant' => $support->tenant,
        ]);
    }

    /**
     * Display tenant support messages filtered by tenant
     */
    public function byTenant(Request $request, Tenant $tenant): View
    {
        $messages = Support::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('central.support-messages-by-tenant', [
            'messages' => $messages,
            'tenant' => $tenant,
        ]);
    }

    /**
     * Get support statistics (JSON for dashboard)
     */
    public function statistics(Request $request)
    {
        return response()->json([
            'total_submissions' => Support::count(),
            'tenant_submissions' => Support::tenantSubmissions()->count(),
            'central_submissions' => Support::centralSubmissions()->count(),
            'unreviewed_tenant' => Support::tenantSubmissions()
                ->whereNull('reviewed_at')
                ->count(),
            'unreviewed_central' => Support::centralSubmissions()
                ->whereNull('reviewed_at')
                ->count(),
            'by_category' => Support::selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->pluck('count', 'category'),
            'by_source' => Support::selectRaw('source, count(*) as count')
                ->groupBy('source')
                ->pluck('count', 'source'),
        ]);
    }
}
