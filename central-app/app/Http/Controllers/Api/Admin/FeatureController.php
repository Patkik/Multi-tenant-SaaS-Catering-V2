<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeatureRequest;
use App\Http\Requests\UpdateFeatureRequest;
use App\Models\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FeatureController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('admin.features.read');

        return response()->json([
            'data' => Feature::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreFeatureRequest $request): JsonResponse
    {
        Gate::authorize('admin.features.write');

        $feature = Feature::query()->create($request->validated());

        return response()->json([
            'data' => $feature,
        ], 201);
    }

    public function update(UpdateFeatureRequest $request, Feature $feature): JsonResponse
    {
        Gate::authorize('admin.features.write');

        $feature->fill($request->validated());
        $feature->save();

        return response()->json([
            'data' => $feature->fresh(),
        ]);
    }
}
