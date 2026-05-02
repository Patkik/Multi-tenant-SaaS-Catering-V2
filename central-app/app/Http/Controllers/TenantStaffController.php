<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantStaffRequest;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantStaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Staff::query()->withCount('events');

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('position', 'like', $search);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        return response()->json([
            'data' => $query
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(15)
                ->through(fn (Staff $staff) => [
                    'id' => $staff->id,
                    'first_name' => $staff->first_name,
                    'last_name' => $staff->last_name,
                    'full_name' => trim($staff->first_name.' '.$staff->last_name),
                    'email' => $staff->email,
                    'phone' => $staff->phone,
                    'position' => $staff->position,
                    'is_active' => (bool) $staff->is_active,
                    'events_count' => $staff->events_count,
                ]),
        ]);
    }

    public function store(TenantStaffRequest $request): JsonResponse
    {
        $staff = Staff::query()->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $staff->id,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'full_name' => trim($staff->first_name.' '.$staff->last_name),
                'email' => $staff->email,
                'phone' => $staff->phone,
                'position' => $staff->position,
                'is_active' => (bool) $staff->is_active,
            ],
        ], 201);
    }

    public function update(TenantStaffRequest $request, Staff $staff): JsonResponse
    {
        $staff->fill($request->validated());
        $staff->save();

        return response()->json([
            'data' => [
                'id' => $staff->id,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'full_name' => trim($staff->first_name.' '.$staff->last_name),
                'email' => $staff->email,
                'phone' => $staff->phone,
                'position' => $staff->position,
                'is_active' => (bool) $staff->is_active,
            ],
        ]);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        if ($staff->events()->exists()) {
            return response()->json([
                'message' => 'This staff member has event assignments and cannot be deleted.',
            ], 422);
        }

        $staff->delete();

        return response()->json([
            'data' => [
                'message' => 'Staff member deleted successfully.',
            ],
        ]);
    }
}
