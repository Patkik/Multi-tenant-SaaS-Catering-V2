<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantUserRequest;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TenantUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->trim().'%';
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('username', 'like', $search)
                    ->orWhere('firstname', 'like', $search)
                    ->orWhere('lastname', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        return response()->json([
            'data' => $query
                ->latest('id')
                ->paginate(15)
                ->through(fn (User $user) => $this->serializeUser($user)),
        ]);
    }

    public function store(TenantUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'name' => trim($validated['firstname'].' '.$validated['lastname']),
            'username' => $validated['username'],
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'mi' => $validated['mi'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => Hash::make((string) $validated['password']),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        $user->syncRoles([TenantRoles::normalize((string) $validated['role'])]);

        return response()->json([
            'data' => $this->serializeUser($user),
        ], 201);
    }

    public function update(TenantUserRequest $request, User $member): JsonResponse
    {
        $validated = $request->validated();

        $member->fill([
            'name' => array_key_exists('firstname', $validated) || array_key_exists('lastname', $validated)
                ? trim(($validated['firstname'] ?? $member->firstname).' '.($validated['lastname'] ?? $member->lastname))
                : $member->name,
            'username' => $validated['username'] ?? $member->username,
            'firstname' => $validated['firstname'] ?? $member->firstname,
            'lastname' => $validated['lastname'] ?? $member->lastname,
            'mi' => array_key_exists('mi', $validated) ? $validated['mi'] : $member->mi,
            'email' => array_key_exists('email', $validated) ? $validated['email'] : $member->email,
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : $member->is_active,
        ]);

        if (! empty($validated['password'])) {
            $member->password = Hash::make((string) $validated['password']);
        }

        $member->save();

        if (array_key_exists('role', $validated)) {
            $member->syncRoles([TenantRoles::normalize((string) $validated['role'])]);
        }

        if (! $member->is_active) {
            $member->tokens()->delete();
        }

        return response()->json([
            'data' => $this->serializeUser($member),
        ]);
    }

    public function destroy(User $member): JsonResponse
    {
        $actor = request()->user();

        if ($actor && $actor->id === $member->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $member->delete();

        return response()->json([
            'data' => [
                'message' => 'User deleted successfully.',
            ],
        ]);
    }

    private function serializeUser(User $user): array
    {
        $role = TenantRoles::resolveFromUser($user);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'display_name' => trim((string) (($user->firstname ?? '').' '.($user->lastname ?? ''))) ?: ($user->name ?? $user->username),
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'role' => $role,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'modules' => TenantRoles::moduleCapabilities()[$role] ?? [],
            'created_at' => optional($user->created_at)?->toIso8601String(),
        ];
    }
}
