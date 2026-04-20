<?php

namespace App\Http\Controllers;

use App\Http\Requests\CentralLoginRequest;
use App\Models\User;
use App\Services\AppUpdateService;
use App\Support\CentralPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CentralAuthController extends Controller
{
    public function __construct(
        private readonly AppUpdateService $appUpdateService,
    ) {
    }

    public function login(CentralLoginRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
            ], 422);
        }

        $isActive = $user->getAttribute('is_active');

        if ($isActive !== null && ! (bool) $isActive) {
            return response()->json([
                'message' => 'This account has been deactivated.',
            ], 403);
        }

        if (! $user->hasAnyPermission(...CentralPermissions::all())) {
            return response()->json([
                'message' => 'This account is not allowed to access the central application.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('central-api')->plainTextToken;

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
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'app_version' => $this->appUpdateService->currentVersion(),
            'is_active' => (bool) ($user->getAttribute('is_active') ?? true),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
