<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantClientRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query()->withCount('events');

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('first_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        return response()->json([
            'data' => $query
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(15)
                ->through(fn (Client $client) => [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => $client->full_name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'address' => $client->address,
                    'notes' => $client->notes,
                    'events_count' => $client->events_count,
                    'created_at' => optional($client->created_at)?->toIso8601String(),
                ]),
        ]);
    }

    public function store(TenantClientRequest $request): JsonResponse
    {
        $client = Client::query()->create($request->validated());

        return response()->json([
            'data' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'notes' => $client->notes,
            ],
        ], 201);
    }

    public function update(TenantClientRequest $request, Client $client): JsonResponse
    {
        $client->fill($request->validated());
        $client->save();

        return response()->json([
            'data' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'full_name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'notes' => $client->notes,
            ],
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        if ($client->events()->exists()) {
            return response()->json([
                'message' => 'This client has events and cannot be deleted.',
            ], 422);
        }

        $client->delete();

        return response()->json([
            'data' => [
                'message' => 'Client deleted successfully.',
            ],
        ]);
    }
}
