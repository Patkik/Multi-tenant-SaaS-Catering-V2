<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantLoginRequest;
use App\Http\Requests\TenantRegisterRequest;
use App\Models\User;
use App\Support\TenantRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class TenantAuthController extends Controller
{
    public function registrationPolicy(): JsonResponse
    {
        $firstAdminUserId = $this->resolveFirstAdminUserId();

        return response()->json([
            'data' => [
                'first_admin_user_id' => $firstAdminUserId,
                'first_admin_exists' => $firstAdminUserId !== null,
                'open_registration' => true,
                'available_roles' => $firstAdminUserId === null
                    ? TenantRoles::all()
                    : array_values(array_filter(TenantRoles::all(), fn (string $role): bool => $role !== TenantRoles::ADMIN)),
            ],
        ]);
    }

    public function register(TenantRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $requestedRole = TenantRoles::normalize((string) $validated['role']);
        $actor = auth('sanctum')->user();
        $firstAdminUserId = $this->resolveFirstAdminUserId();

        if ($requestedRole === TenantRoles::ADMIN && ! $this->canAssignAdminRole($actor, $firstAdminUserId)) {
            return response()->json([
                'message' => 'Only the first admin account can assign the Admin role.',
                'errors' => [
                    'role' => ['Only the first admin account can assign the Admin role.'],
                ],
            ], 403);
        }

        $username = $this->buildUniqueUsername(
            isset($validated['username']) ? (string) $validated['username'] : null,
            (string) ($validated['email'] ?? ''),
            (string) $validated['firstname'],
            (string) $validated['lastname'],
        );

        $user = User::query()->create([
            'name' => trim($validated['firstname'].' '.$validated['lastname']),
            'username' => $username,
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'mi' => $validated['mi'] ?? null,
            'email' => $validated['email'] ?? null,
            'password' => Hash::make((string) $validated['password']),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
        ]);

        $user->syncRoles([$requestedRole]);

        $response = [
            'user' => $this->serializeUser($user),
        ];

        if (! $actor) {
            $user->tokens()->delete();
            $response['token'] = $user->createToken('tenant-api')->plainTextToken;
        }

        return response()->json([
            'data' => $response,
        ], 201);
    }

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

    private function resolveFirstAdminUserId(): ?int
    {
        $firstAdminUserId = User::query()
            ->whereHas('roles', function ($query): void {
                $query->where('name', TenantRoles::ADMIN);
            })
            ->orderBy('id')
            ->value('id');

        return is_numeric($firstAdminUserId) ? (int) $firstAdminUserId : null;
    }

    private function canAssignAdminRole(mixed $actor, ?int $firstAdminUserId): bool
    {
        if ($firstAdminUserId === null) {
            return true;
        }

        return $actor instanceof User && (int) $actor->id === $firstAdminUserId;
    }

    private function buildUniqueUsername(?string $username, string $email, string $firstname, string $lastname): string
    {
        $seed = trim((string) $username);

        if ($seed === '') {
            $seed = trim(Str::before($email, '@'));
        }

        if ($seed === '') {
            $seed = trim($firstname.'.'.$lastname, '.');
        }

        $base = Str::of($seed)
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '')
            ->trim('.')
            ->substr(0, 50)
            ->value();

        if ($base === '') {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $tail = (string) $suffix;
            $candidate = Str::limit($base, 50 - strlen($tail), '');
            $candidate .= $tail;
            $suffix++;
        }

        return $candidate;
    }
}
