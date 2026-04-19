<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantLoginRequest;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TenantAuthController extends Controller
{
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $identity = (string) $request->string('identity');

        $user = User::query()
            ->where('username', $identity)
            ->orWhere('email', $identity)
            ->first();

        if (! $user || ! Hash::check((string) $request->string('password'), (string) $user->password)) {
            return response()->json([
                'message' => 'Invalid username/email or password.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('tenant-api')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => $this->serializeUser($user),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'data' => [
                'user' => $this->serializeUser($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json([
            'data' => [
                'message' => 'Signed out successfully.',
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
        ];
    }
}
