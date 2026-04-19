<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantBrandingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class TenantBrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        return response()->json([
            'data' => [
                'company_name' => $tenant->getAttribute('company_name'),
                'primary_color' => Arr::get($branding, 'primary_color', '#0B8F66'),
                'logo_url' => Arr::get($branding, 'logo_url'),
                'logo_path' => Arr::get($branding, 'logo_path'),
            ],
        ]);
    }

    public function update(UpdateTenantBrandingRequest $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $validated = $request->validated();
        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        if (array_key_exists('company_name', $validated)) {
            $tenant->setAttribute('company_name', $validated['company_name']);
        }

        $tenant->setAttribute('branding', array_filter([
            'primary_color' => $validated['primary_color'] ?? Arr::get($branding, 'primary_color', '#0B8F66'),
            'logo_url' => $validated['logo_url'] ?? Arr::get($branding, 'logo_url'),
            'logo_path' => $validated['logo_path'] ?? Arr::get($branding, 'logo_path'),
        ], static fn ($value) => $value !== null));

        $tenant->save();

        return response()->json([
            'data' => [
                'company_name' => $tenant->getAttribute('company_name'),
                'primary_color' => Arr::get($tenant->getAttribute('branding'), 'primary_color', '#0B8F66'),
                'logo_url' => Arr::get($tenant->getAttribute('branding'), 'logo_url'),
                'logo_path' => Arr::get($tenant->getAttribute('branding'), 'logo_path'),
            ],
        ]);
    }
}
