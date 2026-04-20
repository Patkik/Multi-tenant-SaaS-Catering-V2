<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantPackageRequest;
use App\Models\CateringPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CateringPackage::query()->withCount('events');

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        return response()->json([
            'data' => $query
                ->latest('id')
                ->paginate(15)
                ->through(fn (CateringPackage $package) => $this->serializePackage($package, true)),
        ]);
    }

    public function store(TenantPackageRequest $request): JsonResponse
    {
        $package = CateringPackage::query()->create($request->validated());

        return response()->json([
            'data' => $this->serializePackage($package),
        ], 201);
    }

    public function update(TenantPackageRequest $request, CateringPackage $package): JsonResponse
    {
        $package->fill($request->validated());
        $package->save();

        return response()->json([
            'data' => $this->serializePackage($package),
        ]);
    }

    public function destroy(CateringPackage $package): JsonResponse
    {
        if ($package->events()->exists()) {
            return response()->json([
                'message' => 'This package is used by existing events and cannot be deleted.',
            ], 422);
        }

        $package->delete();

        return response()->json([
            'data' => [
                'message' => 'Package deleted successfully.',
            ],
        ]);
    }

    private function serializePackage(CateringPackage $package, bool $includeCounts = false): array
    {
        $payload = [
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'pricing_mode' => $package->pricing_mode,
            'base_price' => $package->base_price,
            'is_active' => (bool) $package->is_active,
            'menu_items' => is_array($package->menu_items) ? array_values($package->menu_items) : [],
            'menu_published_at' => $package->menu_published_at?->toIso8601String(),
        ];

        if ($includeCounts) {
            $payload['events_count'] = $package->events_count;
        }

        return $payload;
    }
}
