<?php

use App\Exceptions\TenantProvisioningException;
use App\Http\Controllers\Tenant\OrdersController;
use App\Models\Feature;
use App\Models\RoleTemplate;
use App\Models\RoleTemplateApplication;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAccountRegistrationService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$centralHost = strtolower((string) env('CENTRAL_APP_HOST', 'localhost'));

$isLocalLoopbackHost = static function (string $host): bool {
    $normalizedHost = strtolower(trim($host, '[]'));

    return $normalizedHost === 'localhost'
        || $normalizedHost === '127.0.0.1'
        || $normalizedHost === '::1';
};

$isCentralRequest = static function (Request $request) use ($centralHost, $isLocalLoopbackHost): bool {
    $host = strtolower($request->getHost());
    // Treat loopback hosts as equivalent for local development.
    if ($centralHost === 'localhost' && $isLocalLoopbackHost($host)) {
        return true;
    }
    return $host === $centralHost;
};

$normalizeHostname = static function (string $value): string {
    $candidate = strtolower(trim($value));

    if ($candidate === '') {
        return '';
    }

    $candidateWithScheme = preg_match('/\A[a-z][a-z0-9+\-.]*:\/\//i', $candidate) === 1
        ? $candidate
        : sprintf('//%s', $candidate);

    $parsedHost = parse_url($candidateWithScheme, PHP_URL_HOST);

    if (is_string($parsedHost) && $parsedHost !== '') {
        return trim($parsedHost, '[]');
    }

    // Fallback parser for non-standard host strings.
    $candidate = (string) preg_replace('/\A[a-z][a-z0-9+\-.]*:\/\//i', '', $candidate);
    $candidate = (string) preg_replace('/[\/?#].*\z/', '', $candidate);

    if ($candidate === '') {
        return '';
    }

    if (str_starts_with($candidate, '[')) {
        $ipv6End = strpos($candidate, ']');

        if ($ipv6End !== false) {
            return substr($candidate, 1, $ipv6End - 1);
        }
    }

    return explode(':', $candidate, 2)[0];
};

$resolveLocalDevPort = static function (?Request $request = null): ?int {
    $port = $request?->getPort();

    if (is_int($port) && $port > 0) {
        return $port;
    }

    $appUrl = (string) config('app.url', '');
    $appUrlPort = parse_url($appUrl, PHP_URL_PORT);

    return is_int($appUrlPort) && $appUrlPort > 0 ? $appUrlPort : null;
};

$extractExplicitPort = static function (string $value): ?int {
    $candidate = strtolower(trim($value));

    if ($candidate === '') {
        return null;
    }

    $candidateWithScheme = preg_match('/\A[a-z][a-z0-9+\-.]*:\/\//i', $candidate) === 1
        ? $candidate
        : sprintf('//%s', $candidate);

    $parsedPort = parse_url($candidateWithScheme, PHP_URL_PORT);

    return is_int($parsedPort) && $parsedPort > 0 ? $parsedPort : null;
};

$formatHostWithOptionalPort = static function (string $host, ?int $port = null): string {
    $normalizedHost = trim($host, '[]');
    $hostForOutput = str_contains($normalizedHost, ':')
        ? sprintf('[%s]', $normalizedHost)
        : $normalizedHost;

    return $port === null ? $hostForOutput : sprintf('%s:%d', $hostForOutput, $port);
};

$normalizeLocalhostTenantDomain = static function (string $domain, ?Request $request = null) use ($resolveLocalDevPort, $isLocalLoopbackHost, $formatHostWithOptionalPort): string {
    $trimmedDomain = trim($domain);

    if ($trimmedDomain === '') {
        return $trimmedDomain;
    }

    $base = preg_match('/\Ahttps?:\/\//i', $trimmedDomain) === 1
        ? $trimmedDomain
        : sprintf('http://%s', $trimmedDomain);

    $parts = parse_url($base);

    if (! is_array($parts)) {
        return $trimmedDomain;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host === '') {
        return $trimmedDomain;
    }

    $isLocalhost = $isLocalLoopbackHost($host)
        || str_ends_with($host, '.localhost');

    if (! $isLocalhost) {
        return $trimmedDomain;
    }

    $existingPort = isset($parts['port']) ? (int) $parts['port'] : null;

    // Preserve custom localhost ports; only correct empty/defaulted legacy values.
    if ($existingPort !== null && $existingPort !== 8080) {
        return $formatHostWithOptionalPort($host, $existingPort);
    }

    $activePort = $resolveLocalDevPort($request);

    if ($activePort === null) {
        return $formatHostWithOptionalPort($host, $existingPort);
    }

    return $formatHostWithOptionalPort($host, $activePort);
};

$resolveTenantFromRequest = static function (Request $request) use ($normalizeHostname, $extractExplicitPort): ?Tenant {
    $requestHttpHost = strtolower(trim((string) $request->getHttpHost()));
    $requestHost = $normalizeHostname($requestHttpHost);

    if ($requestHost === '') {
        return null;
    }

    $tenant = Tenant::query()->whereRaw('LOWER(domain) = ?', [$requestHttpHost])->first();

    if ($tenant !== null) {
        return $tenant;
    }

    $matches = Tenant::query()
        ->whereRaw('LOWER(domain) LIKE ?', [sprintf('%%%s%%', $requestHost)])
        ->get()
        ->filter(static fn (Tenant $candidate): bool => $normalizeHostname((string) $candidate->domain) === $requestHost)
        ->values();

    if ($matches->count() <= 1) {
        return $matches->first();
    }

    $requestPort = $extractExplicitPort($requestHttpHost);

    if ($requestPort !== null) {
        $portMatched = $matches
            ->filter(static fn (Tenant $candidate): bool => $extractExplicitPort((string) $candidate->domain) === $requestPort)
            ->values();

        return $portMatched->count() === 1 ? $portMatched->first() : null;
    }

    $withoutExplicitPort = $matches
        ->filter(static fn (Tenant $candidate): bool => $extractExplicitPort((string) $candidate->domain) === null)
        ->values();

    return $withoutExplicitPort->count() === 1 ? $withoutExplicitPort->first() : null;
};

$tenantAppUrl = static function (Tenant $tenant): string {
    $domain = (string) $tenant->domain;
    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : sprintf('http://%s', $domain);

    return rtrim($base, '/').'/dashboard';
};

$tenantLoginUrl = static function (Tenant $tenant): string {
    $domain = (string) $tenant->domain;
    $base = preg_match('/\Ahttps?:\/\//i', $domain) === 1 ? $domain : sprintf('http://%s', $domain);

    return rtrim($base, '/').'/auth/tenant/login';
};

$resolveTenantRuntimeConnection = static function (Tenant $tenant): string {
    $runtimeConnection = (string) config('tenancy.runtime_connection', config('database.default'));
    $runtimeConnectionAlias = (string) config('tenancy.runtime_connection_alias', 'tenant_runtime');

    $runtimeConnectionConfig = config("database.connections.{$runtimeConnection}");

    if (! is_array($runtimeConnectionConfig)) {
        throw new RuntimeException(sprintf('Runtime tenant connection "%s" is not configured.', $runtimeConnection));
    }

    $runtimeConnectionConfig['database'] = (string) $tenant->database_name;

    config(["database.connections.{$runtimeConnectionAlias}" => $runtimeConnectionConfig]);

    DB::purge($runtimeConnectionAlias);
    DB::connection($runtimeConnectionAlias)->getPdo();

    return $runtimeConnectionAlias;
};

$centralHomeUrl = static function () use ($centralHost): string {
    return sprintf('http://%s/', $centralHost);
};

$normalizeEmail = static function (string $email): string {
    return mb_strtolower(trim($email));
};

Route::get('/auth/central/login', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    return view('auth-login', [
        'title' => 'Central App Login',
        'subtitle' => 'Authenticate as central administrator to access command operations.',
        'action' => route('auth.central.login.submit'),
        'emailLabel' => 'Central Email',
        'passwordLabel' => 'Central Password',
        'submitLabel' => 'Sign In to Central',
    ]);
})->name('auth.central.login');

Route::post('/auth/central/login', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $expectedEmail = (string) env('CENTRAL_AUTH_EMAIL', 'admin@central.local');
    $expectedPassword = (string) env('CENTRAL_AUTH_PASSWORD', 'central123!');

    if (
        strcasecmp((string) $credentials['email'], $expectedEmail) !== 0
        || ! hash_equals($expectedPassword, (string) $credentials['password'])
    ) {
        return back()->withInput(['email' => $credentials['email']])->withErrors([
            'email' => 'Invalid central credentials.',
        ]);
    }

    $request->session()->regenerate();
    $request->session()->put('central_authenticated', true);

    return redirect()->intended('/');
})->name('auth.central.login.submit');

Route::post('/auth/central/logout', function (Request $request) use ($isCentralRequest, $centralHomeUrl) {
    if (! $isCentralRequest($request)) {
        return abort(404);
    }

    $request->session()->forget('central_authenticated');
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to($centralHomeUrl().'auth/central/login');
})->name('auth.central.logout');

Route::get('/auth/tenant/login', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    $isTenantActive = (bool) ($tenant->is_active ?? true);

    return view('auth-login', [
        'title' => 'Tenant App Login',
        'subtitle' => sprintf('Sign in to continue to %s operations.', $tenant->name),
        'action' => route('auth.tenant.login.submit'),
        'emailLabel' => 'Staff Email',
        'passwordLabel' => 'Tenant Password',
        'roleLabel' => 'Tenant Role',
        'roleOptions' => [
            'admin' => 'Admin',
            'manager' => 'Manager',
            'staff' => 'Staff',
            'cashier' => 'Cashier',
        ],
        'submitLabel' => 'Sign In to Tenant',
        'showRegisterLink' => true,
        'registerUrl' => route('auth.tenant.register'),
        'showForgotPasswordLink' => true,
        'forgotPasswordUrl' => route('auth.tenant.forgot-password'),
        'isTenantActive' => $isTenantActive,
    ]);
})->name('auth.tenant.login');

Route::get('/auth/tenant/forgot-password', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    return view('auth-forgot-password', [
        'title' => 'Reset Password',
        'subtitle' => sprintf('%s - Enter your email to reset your password.', $tenant->name),
        'action' => route('auth.tenant.forgot-password.submit'),
        'emailLabel' => 'Staff Email',
        'submitLabel' => 'Send Reset Link',
        'loginUrl' => route('auth.tenant.login'),
    ]);
})->name('auth.tenant.forgot-password');

Route::post('/auth/tenant/forgot-password', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    $request->validate([
        'email' => ['required', 'email'],
    ]);

    // Simulated success behavior for forgot password.
    return redirect()->route('auth.tenant.login')
        ->with('status', 'If your email exists in our system, you will receive a password reset link shortly.');
})->name('auth.tenant.forgot-password.submit');

// Tenant Registration Routes
Route::get('/auth/tenant/register', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return redirect()->route('auth.tenant.login')
            ->with('tenant_deactivated', true)
            ->withErrors([
                'email' => 'This domain has been deactivated by the administrator.',
            ]);
    }

    return view('auth-register', [
        'title' => 'Create Account',
        'subtitle' => sprintf('Register to join %s.', $tenant->name),
        'action' => route('auth.tenant.register.submit'),
        'submitLabel' => 'Create Account',
        'loginUrl' => route('auth.tenant.login'),
    ]);
})->name('auth.tenant.register');

Route::post('/auth/tenant/otp/send', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $normalizeEmail) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return response()->json([
            'message' => 'This domain has been deactivated by the administrator.',
        ], 422);
    }

    $validated = $request->validate([
        'email' => ['required', 'email', 'max:255'],
    ]);

    $email = $normalizeEmail((string) $validated['email']);
    $tenantDomain = strtolower((string) $tenant->domain);
    $sendIpKey = sprintf('tenant-otp-send:ip:%s:%s', $tenantDomain, $request->ip());
    $sendEmailKey = sprintf('tenant-otp-send:email:%s:%s', $tenantDomain, $email);

    if (RateLimiter::tooManyAttempts($sendIpKey, 10) || RateLimiter::tooManyAttempts($sendEmailKey, 5)) {
        $retryAfterSeconds = max(
            RateLimiter::availableIn($sendIpKey),
            RateLimiter::availableIn($sendEmailKey)
        );

        return response()->json([
            'message' => sprintf('Too many verification code requests. Please retry in %d seconds.', max(1, $retryAfterSeconds)),
        ], 429);
    }

    RateLimiter::hit($sendIpKey, 600);
    RateLimiter::hit($sendEmailKey, 600);

    $configuredMockOtp = (string) config('tenancy.mock_otp_code', '');
    $isMockOtpEnabled = app()->environment(['local', 'testing'])
        && preg_match('/^\d{6}$/', $configuredMockOtp) === 1;
    $otp = $isMockOtpEnabled
        ? $configuredMockOtp
        : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $request->session()->put('tenant_registration_otp_challenge', [
        'email' => $email,
        'otp_hash' => Hash::make($otp),
        'expires_at' => now()->addMinutes(10)->toIso8601String(),
        'tenant_id' => (int) $tenant->getKey(),
        'tenant_domain' => $tenantDomain,
    ]);
    $request->session()->forget('tenant_registration_otp_verified_email');

    try {
        Mail::raw(
            sprintf('Your verification code is %s. It expires in 10 minutes.', $otp),
            static function ($message) use ($email): void {
                $message->to($email)->subject('Your verification code');
            }
        );
    } catch (\Throwable $exception) {
        Log::warning('Tenant registration OTP delivery failed.', [
            'tenant_domain' => $tenantDomain,
            'error_type' => $exception::class,
        ]);

        return response()->json([
            'message' => 'Unable to send verification code right now. Please try again in a few moments.',
        ], 503);
    }

    $responsePayload = [
        'message' => 'Verification code sent.',
    ];

    if ($isMockOtpEnabled) {
        $responsePayload['mock_code'] = $otp;
    }

    return response()->json($responsePayload);
})->name('auth.tenant.otp.send');

Route::post('/auth/tenant/otp/verify', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $normalizeEmail) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return response()->json([
            'message' => 'This domain has been deactivated by the administrator.',
        ], 422);
    }

    $validated = $request->validate([
        'email' => ['required', 'email', 'max:255'],
        'code' => ['required', 'digits:6'],
    ]);

    $email = $normalizeEmail((string) $validated['email']);
    $tenantDomain = strtolower((string) $tenant->domain);
    $verifyIpKey = sprintf('tenant-otp-verify:ip:%s:%s', $tenantDomain, $request->ip());
    $verifyEmailKey = sprintf('tenant-otp-verify:email:%s:%s', $tenantDomain, $email);

    if (RateLimiter::tooManyAttempts($verifyIpKey, 30) || RateLimiter::tooManyAttempts($verifyEmailKey, 8)) {
        $retryAfterSeconds = max(
            RateLimiter::availableIn($verifyIpKey),
            RateLimiter::availableIn($verifyEmailKey)
        );

        return response()->json([
            'message' => sprintf('Too many verification attempts. Please retry in %d seconds.', max(1, $retryAfterSeconds)),
        ], 429);
    }

    RateLimiter::hit($verifyIpKey, 600);
    RateLimiter::hit($verifyEmailKey, 600);

    $challenge = $request->session()->get('tenant_registration_otp_challenge');

    if (! is_array($challenge)) {
        return response()->json([
            'message' => 'No OTP challenge found. Please request a new code.',
        ], 422);
    }

    $challengeEmail = $normalizeEmail((string) ($challenge['email'] ?? ''));
    $otpHash = (string) ($challenge['otp_hash'] ?? '');
    $expiresAt = isset($challenge['expires_at']) ? \Illuminate\Support\Carbon::parse((string) $challenge['expires_at']) : null;
    $challengeTenantId = (int) ($challenge['tenant_id'] ?? 0);
    $challengeTenantDomain = strtolower(trim((string) ($challenge['tenant_domain'] ?? '')));
    $tenantId = (int) $tenant->getKey();

    if (
        $challengeEmail !== $email
        || $otpHash === ''
        || ! $expiresAt
        || now()->greaterThan($expiresAt)
        || $challengeTenantId !== $tenantId
        || $challengeTenantDomain === ''
        || $challengeTenantDomain !== $tenantDomain
    ) {
        $request->session()->forget('tenant_registration_otp_challenge');

        return response()->json([
            'message' => 'OTP challenge expired or invalid. Please request a new code.',
        ], 422);
    }

    if (! Hash::check((string) $validated['code'], $otpHash)) {
        return response()->json([
            'message' => 'Invalid verification code.',
        ], 422);
    }

    $request->session()->put('tenant_registration_otp_verified_email', [
        'email' => $email,
        'tenant_id' => $tenantId,
        'tenant_domain' => $tenantDomain,
        'verified_at' => now()->toIso8601String(),
    ]);
    $request->session()->forget('tenant_registration_otp_challenge');
    RateLimiter::clear($verifyEmailKey);

    return response()->json([
        'message' => 'Email verified.',
    ]);
})->name('auth.tenant.otp.verify');

Route::post('/auth/tenant/register', function (Request $request, TenantAccountRegistrationService $tenantAccountRegistrationService) use ($isCentralRequest, $resolveTenantFromRequest, $tenantAppUrl, $normalizeEmail) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return redirect()->route('auth.tenant.login')
            ->with('tenant_deactivated', true)
            ->withErrors([
                'email' => 'This domain has been deactivated by the administrator.',
            ]);
    }

    if ((string) ($tenant->provisioning_status ?? 'provisioning') !== 'ready') {
        return back()->withInput()->withErrors([
            'email' => 'Tenant setup is still in progress. Please try again in a few minutes.',
        ]);
    }

    $validated = $request->validate([
        'first_name' => ['required', 'string', 'max:100'],
        'middle_initial' => ['nullable', 'string', 'max:1'],
        'last_name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:255'],
        'phone' => ['required', 'string', 'max:30'],
        'phone_format' => ['required', 'string', 'in:us,uk,ph,au,ca,de,fr,jp,other'],
        'role' => ['required', 'string', 'in:staff,cashier,manager,admin'],
        'password' => [
            'required',
            'string',
            'min:8',
            'confirmed',
            'regex:/[a-z]/',      // lowercase
            'regex:/[A-Z]/',      // uppercase
            'regex:/[0-9]/',      // number
            'regex:/[!@#$%^&*(),.?":{}|<>]/', // special char
        ],
    ], [
        'password.regex' => 'Password must contain uppercase, lowercase, number, and special character.',
        'password.confirmed' => 'Passwords do not match.',
    ]);

    $verifiedProof = $request->session()->get('tenant_registration_otp_verified_email');
    $verifiedEmail = '';
    $verifiedTenantId = 0;
    $verifiedTenantDomain = '';

    if (is_array($verifiedProof)) {
        $verifiedEmail = $normalizeEmail((string) ($verifiedProof['email'] ?? ''));
        $verifiedTenantId = (int) ($verifiedProof['tenant_id'] ?? 0);
        $verifiedTenantDomain = strtolower(trim((string) ($verifiedProof['tenant_domain'] ?? '')));
    }

    $requestEmail = $normalizeEmail((string) $validated['email']);
    $tenantId = (int) $tenant->getKey();
    $tenantDomain = strtolower((string) $tenant->domain);

    if (
        $verifiedEmail === ''
        || $verifiedEmail !== $requestEmail
        || $verifiedTenantId !== $tenantId
        || $verifiedTenantDomain === ''
        || $verifiedTenantDomain !== $tenantDomain
    ) {
        $request->session()->forget('tenant_registration_otp_verified_email');

        return back()->withInput()->withErrors([
            'otp' => 'Email verification required.',
        ]);
    }

    // Store formatted phone with country code
    $phoneFormats = [
        'us' => '+1', 'uk' => '+44', 'ph' => '+63', 'au' => '+61',
        'ca' => '+1', 'de' => '+49', 'fr' => '+33', 'jp' => '+81', 'other' => ''
    ];
    $phoneWithCode = $phoneFormats[$validated['phone_format']] . ' ' . $validated['phone'];

    // Build full name
    $middleInitial = !empty($validated['middle_initial']) ? strtoupper($validated['middle_initial']) . '.' : '';
    $fullName = trim($validated['first_name'] . ' ' . $middleInitial . ' ' . $validated['last_name']);

    $role = $validated['role'];
    $requiresApproval = ($role === 'admin');

    try {
        $registrationResult = $tenantAccountRegistrationService->register($tenant, [
            'email' => (string) $validated['email'],
            'password' => (string) $validated['password'],
            'role' => $role,
            'full_name' => $fullName,
        ]);
    } catch (RuntimeException $exception) {
        Log::warning('tenant_registration_runtime_exception', [
            'tenant_id' => $tenantId,
            'tenant_domain' => (string) $tenant->domain,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
        ]);

        if (str_contains($exception->getMessage(), 'already exists')) {
            return back()->withInput()->withErrors([
                'email' => 'An account with this email already exists.',
            ]);
        }

        return back()->withInput()->withErrors([
            'email' => 'Unable to create account right now. Please try again in a few moments.',
        ]);
    } catch (\Throwable $exception) {
        Log::error('tenant_registration_unhandled_exception', [
            'tenant_id' => $tenantId,
            'tenant_domain' => (string) $tenant->domain,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
        ]);

        return back()->withInput()->withErrors([
            'email' => 'Unable to create account right now. Please try again in a few moments.',
        ]);
    }

    $request->session()->forget([
        'tenant_registration_otp_verified_email',
        'tenant_registration_otp_challenge',
    ]);
    
    if ($requiresApproval) {
        // Admin role requires approval - show pending page
        $request->session()->put('pending_registration', [
            'tenant_domain' => (string) $tenant->domain,
            'tenant_name' => $tenant->name,
            'email' => $validated['email'],
            'name' => $fullName,
            'role' => $role,
            'phone' => $phoneWithCode,
            'status' => $registrationResult['status'],
            'created_at' => now()->toIso8601String(),
        ]);
        
        return redirect()->route('auth.tenant.register.pending');
    }

    // Non-admin roles - activate immediately
    $sessionTenantRole = 'staff';
    $sessionTenantEmail = $requestEmail;

    try {
        $registrationConnection = (string) ($registrationResult['connection'] ?? '');

        if ($registrationConnection !== '' && Schema::connection($registrationConnection)->hasTable('users')) {
            $usersHasRoleColumn = Schema::connection($registrationConnection)->hasColumn('users', 'role');
            $selectColumns = ['email'];

            if ($usersHasRoleColumn) {
                $selectColumns[] = 'role';
            }

            $persistedUser = DB::connection($registrationConnection)
                ->table('users')
                ->whereRaw('LOWER(email) = ?', [$requestEmail])
                ->latest('id')
                ->first($selectColumns);

            if ($persistedUser !== null) {
                $sessionTenantEmail = (string) ($persistedUser->email ?? $requestEmail);

                if ($usersHasRoleColumn) {
                    $persistedRole = (string) ($persistedUser->role ?? '');

                    if (in_array($persistedRole, ['staff', 'cashier', 'manager', 'admin'], true)) {
                        $sessionTenantRole = $persistedRole;
                    }
                }
            }
        }
    } catch (\Throwable) {
        // Keep safe defaults if persisted role lookup is unavailable.
    }

    $request->session()->regenerate();
    $request->session()->put('tenant_authenticated_domain', (string) $tenant->domain);
    $request->session()->put('tenant_role', $sessionTenantRole);
    $request->session()->put('tenant_user_email', $sessionTenantEmail);
    $request->session()->put('tenant_user_name', $fullName);
    $request->session()->put('tenant_user_first_name', $validated['first_name']);
    $request->session()->put('tenant_user_last_name', $validated['last_name']);
    $request->session()->put('tenant_user_middle_initial', $validated['middle_initial'] ?? '');
    $request->session()->put('tenant_user_phone', $phoneWithCode);

    return redirect()->to($tenantAppUrl($tenant));
})->name('auth.tenant.register.submit');

// Pending approval page for admin registrations
Route::get('/auth/tenant/register/pending', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $pending = $request->session()->get('pending_registration');
    
    if (!$pending) {
        return redirect()->route('auth.tenant.register');
    }

    return view('auth-register-pending', [
        'title' => 'Registration Pending',
        'email' => $pending['email'],
        'name' => $pending['name'],
        'role' => $pending['role'],
        'tenantName' => $pending['tenant_name'],
        'loginUrl' => route('auth.tenant.login'),
    ]);
})->name('auth.tenant.register.pending');

Route::post('/auth/tenant/login', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $tenantAppUrl, $resolveTenantRuntimeConnection, $normalizeEmail) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return back()->withInput($request->only('email', 'role'))
            ->with('tenant_deactivated', true)
            ->withErrors([
                'email' => 'This domain has been deactivated by the administrator.',
            ]);
    }

    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);
    $normalizedEmail = $normalizeEmail((string) $credentials['email']);

    try {
        $connectionName = $resolveTenantRuntimeConnection($tenant);
    } catch (\Throwable) {
        return back()->withInput($request->only('email', 'role'))->withErrors([
            'email' => 'Unable to authenticate right now. Please try again shortly.',
        ]);
    }

    $usersHasRoleColumn = Schema::connection($connectionName)->hasColumn('users', 'role');
    $usersHasIsActiveColumn = Schema::connection($connectionName)->hasColumn('users', 'is_active');

    $selectColumns = ['email', 'password'];

    if ($usersHasRoleColumn) {
        $selectColumns[] = 'role';
    }

    if ($usersHasIsActiveColumn) {
        $selectColumns[] = 'is_active';
    }

    $resolvedUser = DB::connection($connectionName)
        ->table('users')
        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
        ->first($selectColumns);

    if (
        ! $resolvedUser
        || ($usersHasIsActiveColumn && ! (bool) ($resolvedUser->is_active ?? false))
        || ! is_string($resolvedUser->password ?? null)
        || ! Hash::check((string) $credentials['password'], (string) $resolvedUser->password)
    ) {
        return back()->withInput(['email' => $normalizedEmail])->withErrors([
            'email' => 'Invalid tenant credentials.',
        ]);
    }

    $request->session()->regenerate();
    $request->session()->put('tenant_authenticated_domain', (string) $tenant->domain);
    $request->session()->put('tenant_role', (string) ($resolvedUser->role ?? 'staff'));
    $request->session()->put('tenant_user_email', (string) ($resolvedUser->email ?? $normalizedEmail));

    return redirect()->to($tenantAppUrl($tenant));
})->name('auth.tenant.login.submit');

Route::post('/auth/tenant/logout', function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $tenantLoginUrl) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    $request->session()->forget([
        'tenant_authenticated_domain',
        'tenant_role',
        'tenant_user_email',
        'tenant_user_name',
        'tenant_user_first_name',
        'tenant_user_last_name',
        'tenant_user_middle_initial',
        'tenant_user_phone',
    ]);
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->to($tenantLoginUrl($tenant));
})->name('auth.tenant.logout');

// Helper function for safe database operations
$safe = static function (callable $callback, mixed $fallback = null): mixed {
    try {
        return $callback();
    } catch (\Throwable) {
        return $fallback;
    }
};

Route::get('/', function (Request $request) use ($isCentralRequest, $safe) {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $databaseOnline = $safe(static fn (): bool => DB::connection()->getPdo() !== null, false);

    if ($databaseOnline) {
        $stats = [
            'tenants' => $safe(static fn (): int => Tenant::count()),
            'features' => $safe(static fn (): int => Feature::count()),
            'role_templates' => $safe(static fn (): int => RoleTemplate::count()),
            'applications_active' => $safe(
                static fn (): int => RoleTemplateApplication::query()
                    ->whereIn('status', [
                        RoleTemplateApplication::STATUS_QUEUED,
                        RoleTemplateApplication::STATUS_APPLYING,
                    ])->count()
            ),
        ];
    } else {
        $stats = [
            'tenants' => null,
            'features' => null,
            'role_templates' => null,
            'applications_active' => null,
        ];
    }

    /** @var Collection<int, array{status:string,total:int}> $tenantStatusBreakdown */
    $tenantStatusBreakdown = $databaseOnline
        ? $safe(
            static fn (): Collection => Tenant::query()
                ->selectRaw('COALESCE(provisioning_status, ?) as status, COUNT(*) as total', ['unknown'])
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(static fn (Tenant $tenant): array => [
                    'status' => (string) ($tenant->getAttribute('status') ?? 'unknown'),
                    'total' => (int) ($tenant->getAttribute('total') ?? 0),
                ]),
            collect()
        )
        : collect();

    $recentApplications = $databaseOnline
        ? $safe(
            static fn (): Collection => RoleTemplateApplication::query()
                ->with([
                    'tenant:id,name,domain',
                    'roleTemplate:id,name',
                ])
                ->latest('id')
                ->limit(6)
                ->get(),
            collect()
        )
        : collect();

    return view('central-dashboard', [
        'databaseOnline' => (bool) $databaseOnline,
        'stats' => $stats,
        'tenantStatusBreakdown' => $tenantStatusBreakdown,
        'recentApplications' => $recentApplications,
    ]);
})->name('central.dashboard');

// Tenants management page
Route::get('/tenants', function (Request $request) use ($isCentralRequest, $safe, $resolveLocalDevPort) {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $databaseOnline = $safe(static fn (): bool => DB::connection()->getPdo() !== null, false);

    $tenants = $databaseOnline
        ? $safe(
            static fn (): Collection => Tenant::query()
                ->latest('id')
                ->get(['id', 'name', 'domain', 'database_name', 'plan_code', 'is_active', 'provisioning_status', 'created_at']),
            collect()
        )
        : collect();

    return view('central-tenants', [
        'databaseOnline' => (bool) $databaseOnline,
        'tenants' => $tenants,
        'defaultTenantDomainPort' => $resolveLocalDevPort($request),
    ]);
})->name('central.tenants');

Route::post('/tenants/create', function (Request $request, TenantProvisioningService $tenantProvisioningService) use ($isCentralRequest, $normalizeLocalhostTenantDomain): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $defaultConnection = (string) config('database.default');
    $defaultDriver = (string) config("database.connections.{$defaultConnection}.driver");

    if ($defaultDriver === 'mysql') {
        try {
            DB::connection($defaultConnection)->getPdo();
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();

            if (! str_contains($message, 'Unknown database')) {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Database connection failed before provisioning. Check MySQL service availability.');
            }

            $databaseName = (string) config("database.connections.{$defaultConnection}.database");

            if ($databaseName === '') {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Central database name is not configured. Set DB_DATABASE in your environment.');
            }

            try {
                $provisioningConnection = (string) config('tenancy.provisioning_connection', $defaultConnection);
                $provisioning = DB::connection($provisioningConnection);

                $exists = $provisioning->selectOne(
                    'SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
                    [$databaseName]
                );

                if ($exists === null) {
                    $identifier = sprintf('`%s`', str_replace('`', '``', $databaseName));
                    $provisioning->statement(sprintf('CREATE DATABASE %s', $identifier));
                }

                DB::purge($defaultConnection);
                DB::reconnect($defaultConnection);
            } catch (\Throwable) {
                return back()
                    ->withInput()
                    ->with('tenant_create_error', 'Failed to bootstrap central database before tenant creation. Verify DB_PROVISIONING_* privileges and rerun.');
            }
        }

        try {
            $schema = Schema::connection($defaultConnection);

            if (! $schema->hasTable('tenants')) {
                $schema->create('tenants', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('name');
                    $table->string('domain')->unique();
                    $table->string('database_name')->unique();
                    $table->string('plan_code')->nullable();
                    $table->json('plan_entitlements')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->string('provisioning_status')->default('provisioning');
                    $table->text('provisioning_error')->nullable();
                    $table->timestamp('provisioned_at')->nullable();
                    $table->timestamps();
                });
            } else {
                $schema->table('tenants', function (Blueprint $table) use ($schema): void {
                    if (! $schema->hasColumn('tenants', 'is_active')) {
                        $table->boolean('is_active')->default(true)->after('plan_entitlements');
                    }

                    if (! $schema->hasColumn('tenants', 'provisioning_status')) {
                        $table->string('provisioning_status')->default('provisioning');
                    }

                    if (! $schema->hasColumn('tenants', 'provisioning_error')) {
                        $table->text('provisioning_error')->nullable();
                    }

                    if (! $schema->hasColumn('tenants', 'provisioned_at')) {
                        $table->timestamp('provisioned_at')->nullable();
                    }
                });
            }
        } catch (\Throwable) {
            return back()
                ->withInput()
                ->with('tenant_create_error', 'Failed to prepare central schema for tenant validation. Run central migrations and retry.');
        }
    }

    $request->merge([
        'domain' => $normalizeLocalhostTenantDomain((string) $request->input('domain', ''), $request),
    ]);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'domain' => ['required', 'string', 'max:255', 'unique:tenants,domain'],
        'database_name' => ['required', 'string', 'max:64', 'regex:/\A[a-zA-Z0-9_]+\z/', 'unique:tenants,database_name'],
        'plan_code' => ['nullable', 'string', 'max:50'],
        'plan_entitlements' => ['nullable', 'string', 'max:500'],
    ]);

    $entitlements = collect(explode(',', (string) ($validated['plan_entitlements'] ?? '')))
        ->map(static fn (string $value): string => trim($value))
        ->filter(static fn (string $value): bool => $value !== '')
        ->values()
        ->all();

    try {
        $tenant = $tenantProvisioningService->createTenant([
            'name' => $validated['name'],
            'domain' => $validated['domain'],
            'database_name' => $validated['database_name'],
            'plan_code' => $validated['plan_code'] ?? null,
            'plan_entitlements' => $entitlements,
        ]);
    } catch (TenantProvisioningException) {
        return back()
            ->withInput()
            ->with('tenant_create_error', 'Tenant was saved but database provisioning failed. Check provisioning credentials and MySQL availability.');
    }

    return redirect('/')
        ->with('tenant_create_success', sprintf('Tenant "%s" created and database "%s" provisioned.', $tenant->name, $tenant->database_name));
})->name('tenants.create');

Route::patch('/tenants/{tenant}', function (Request $request, Tenant $tenant) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $payload = $request->validate([
        'plan_code' => ['required', 'string', 'in:starter,growth,enterprise'],
        'is_active' => ['required', 'boolean'],
    ]);

    $tenant->fill([
        'plan_code' => trim((string) $payload['plan_code']),
        'is_active' => (bool) $payload['is_active'],
    ])->save();

    return redirect()->route('central.tenants')->with('success', sprintf('Tenant "%s" updated.', $tenant->name));
})->name('central.tenants.update');

Route::get('/users', function (Request $request) use ($isCentralRequest) {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    return view('central-user-management', [
        'users' => User::query()->orderBy('name')->get(),
    ]);
})->name('central.users.index');

Route::post('/users', function (Request $request) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $payload = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8'],
        'role' => ['required', 'string', 'in:super_admin,operations_admin,auditor'],
    ]);

    User::query()->create([
        'name' => $payload['name'],
        'email' => $payload['email'],
        'password' => Hash::make($payload['password']),
        'role' => $payload['role'],
        'is_active' => true,
    ]);

    return redirect()->route('central.users.index')->with('user_success', 'Central user created.');
})->name('central.users.store');

Route::patch('/users/{user}', function (Request $request, User $user) use ($isCentralRequest): RedirectResponse {
    if (! $isCentralRequest($request)) {
        return redirect()->route('auth.tenant.login');
    }

    if (! $request->session()->get('central_authenticated', false)) {
        return redirect()->route('auth.central.login');
    }

    $payload = $request->validate([
        'role' => ['required', 'string', 'in:super_admin,operations_admin,auditor'],
        'is_active' => ['required', 'boolean'],
    ]);

    $user->fill([
        'role' => $payload['role'],
        'is_active' => (bool) $payload['is_active'],
    ])->save();

    return redirect()->route('central.users.index')->with('user_success', 'Central user updated.');
})->name('central.users.update');

Route::get('/tenant-app/{tenant?}', function (Request $request, ?Tenant $tenant = null) use ($isCentralRequest, $resolveTenantFromRequest, $tenantAppUrl, $tenantLoginUrl, $centralHomeUrl) {
    if ($isCentralRequest($request) && $tenant === null) {
        return redirect()->to($centralHomeUrl());
    }

    $tenant ??= $resolveTenantFromRequest($request);

    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        return redirect()->to($tenantLoginUrl($tenant))->with('tenant_deactivated', true);
    }

    $tenantDomain = strtolower((string) $tenant->domain);
    $requestDomain = strtolower($request->getHttpHost());

    if ($tenantDomain !== $requestDomain) {
        return redirect()->away($tenantLoginUrl($tenant));
    }

    if ($request->session()->get('tenant_authenticated_domain') !== (string) $tenant->domain) {
        return redirect()->away($tenantLoginUrl($tenant));
    }

    $tenantRole = (string) $request->session()->get('tenant_role', 'staff');
    $tenantRoleLabel = match ($tenantRole) {
        'admin' => 'Admin',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        default => 'Staff',
    };

    $target = $tenantAppUrl($tenant);
    $current = rtrim($request->getSchemeAndHttpHost().$request->getPathInfo(), '/');

    if ($current !== rtrim($target, '/')) {
        return redirect()->away($target);
    }

    // Load TenantRole from database and use RBAC service
    $tenantRoleModel = null;
    try {
        $tenantRoleModel = \App\Models\TenantRole::where('name', $tenantRole)->first();
    } catch (\Exception $e) {
        // If RBAC tables don't exist yet, allow access with fallback
    }

    $rbacService = app(\App\Services\RBACService::class);
    $availableModules = $rbacService->getAvailableModules($tenantRoleModel);
    $canManageTeam = $rbacService->hasPermission($tenantRoleModel, 'team.manage');
    $canSeeAnalytics = $rbacService->hasFeature($tenantRoleModel, 'analytics');
    $canOpenKitchenBoard = $rbacService->hasFeature($tenantRoleModel, 'kitchen_board');

    return view('tenant-app-home', [
        'tenant' => $tenant,
        'tenantRole' => $tenantRole,
        'tenantRoleLabel' => $tenantRoleLabel,
        'tenantUserEmail' => (string) $request->session()->get('tenant_user_email', 'unknown@tenant.local'),
        'availableModules' => $availableModules,
        'canManageTeam' => $canManageTeam,
        'canSeeAnalytics' => $canSeeAnalytics,
        'canOpenKitchenBoard' => $canOpenKitchenBoard,
    ]);
})->name('tenant.app.preview');

// =============================================================================
// Tenant Dashboard Routes (Role-Based)
// =============================================================================

// Middleware closure for tenant authentication
$requireTenantAuth = function (Request $request) use ($isCentralRequest, $resolveTenantFromRequest, $tenantLoginUrl) {
    if ($isCentralRequest($request)) {
        return abort(404);
    }

    $tenant = $resolveTenantFromRequest($request);
    if ($tenant === null) {
        return abort(404);
    }

    if (! (bool) ($tenant->is_active ?? true)) {
        $request->session()->forget([
            'tenant_authenticated_domain',
            'tenant_role',
            'tenant_user_email',
            'tenant_user_name',
            'tenant_user_first_name',
            'tenant_user_last_name',
            'tenant_user_middle_initial',
            'tenant_user_phone',
        ]);

        return redirect()->to($tenantLoginUrl($tenant))->with('tenant_deactivated', true);
    }

    if ($request->session()->get('tenant_authenticated_domain') !== (string) $tenant->domain) {
        return redirect()->away($tenantLoginUrl($tenant));
    }

    $request->attributes->set('resolved_tenant', $tenant);

    return null;
};

// Get tenant view data helper
$getTenantViewData = function (Request $request) use ($resolveTenantFromRequest) {
    $tenant = $resolveTenantFromRequest($request);
    $tenantRole = (string) $request->session()->get('tenant_role', 'staff');
    $tenantRoleLabel = match ($tenantRole) {
        'admin' => 'Admin',
        'manager' => 'Manager',
        'cashier' => 'Cashier',
        default => 'Staff',
    };

    return [
        'tenant' => $tenant,
        'tenantRole' => $tenantRole,
        'tenantRoleLabel' => $tenantRoleLabel,
        'tenantUserEmail' => (string) $request->session()->get('tenant_user_email', 'unknown@tenant.local'),
        'tenantUserName' => (string) $request->session()->get('tenant_user_name', 'User'),
    ];
};

// Dashboard (all roles)
Route::get('/dashboard', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    
    $usePlaceholderMetrics = (string) config('app.env') !== 'production';

    if ($usePlaceholderMetrics) {
        // Placeholder-only values are intentionally disabled for production.
        $data['stats'] = [
            'orders_today' => rand(15, 30),
            'orders_growth' => '+'.rand(5, 15).'%',
            'delivered_percent' => rand(85, 98).'%',
            'delivered_count' => rand(8, 20),
            'in_kitchen' => rand(5, 15),
            'pending_pickup' => $data['tenantRole'] === 'cashier' ? rand(2, 5) : rand(1, 3),
            'revenue' => number_format(rand(1500, 3500), 0),
            'active_staff' => rand(3, 8),
            'pending_approvals' => rand(1, 4),
            'assigned_tasks' => rand(2, 6),
        ];

        $data['chart_data'] = [
            'revenue' => [rand(40, 60), rand(60, 80), rand(50, 70), rand(75, 95), rand(45, 65), rand(80, 100), rand(55, 75)],
            'orders' => [rand(30, 50), rand(50, 70), rand(40, 60), rand(65, 85), rand(35, 55), rand(70, 90), rand(45, 65)],
        ];

        $data['recent_orders'] = [
            ['id' => '#'.rand(1000, 9999), 'customer' => 'Maria Santos', 'items' => rand(5, 20).' items', 'status' => 'preparing', 'time' => '10 min ago'],
            ['id' => '#'.rand(1000, 9999), 'customer' => 'Tech Corp', 'items' => rand(15, 50).' items', 'status' => 'ready', 'time' => '25 min ago'],
            ['id' => '#'.rand(1000, 9999), 'customer' => 'Wedding Party', 'items' => rand(50, 150).' items', 'status' => 'delivered', 'time' => '1 hour ago'],
        ];
    } else {
        $data['stats'] = [
            'orders_today' => 0,
            'orders_growth' => '+0%',
            'delivered_percent' => '0%',
            'delivered_count' => 0,
            'in_kitchen' => 0,
            'pending_pickup' => 0,
            'revenue' => '0',
            'active_staff' => 0,
            'pending_approvals' => 0,
            'assigned_tasks' => 0,
        ];

        $data['chart_data'] = [
            'revenue' => [0, 0, 0, 0, 0, 0, 0],
            'orders' => [0, 0, 0, 0, 0, 0, 0],
        ];

        $data['recent_orders'] = [];
    }
    
    $data['tasks'] = [
        ['task' => 'Prepare seafood batch for evening event', 'priority' => 'high', 'done' => false],
        ['task' => 'Check inventory levels for weekend', 'priority' => 'medium', 'done' => false],
        ['task' => 'Review new menu items with chef', 'priority' => 'low', 'done' => true],
        ['task' => 'Update delivery schedule', 'priority' => 'medium', 'done' => false],
    ];

    return view('tenant.dashboard', $data);
})->name('tenant.dashboard');

// Orders (all roles)
Route::get('/orders', function (Request $request) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->index($request);
})->name('tenant.orders.index');

Route::get('/orders/create', function (Request $request) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->create($request);
})->name('tenant.orders.create');

Route::post('/orders', function (Request $request) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->store($request);
})->name('tenant.orders.store');

Route::get('/orders/{order}', function (Request $request, int $order) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->show($request, $order);
})->name('tenant.orders.show');

Route::get('/orders/{order}/edit', function (Request $request, int $order) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->edit($request, $order);
})->name('tenant.orders.edit');

Route::put('/orders/{order}', function (Request $request, int $order) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->update($request, $order);
})->name('tenant.orders.update');

Route::delete('/orders/{order}', function (Request $request, int $order) use ($requireTenantAuth) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;

    return app(OrdersController::class)->destroy($request, $order);
})->name('tenant.orders.destroy');

// Kitchen Board (admin, manager, staff)
Route::get('/kitchen', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if (!in_array($data['tenantRole'], ['admin', 'manager', 'staff'])) {
        return abort(403, 'Access denied');
    }
    return view('tenant.kitchen', $data);
})->name('tenant.kitchen');

// Calendar (admin, manager, staff)
Route::get('/calendar', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if (!in_array($data['tenantRole'], ['admin', 'manager', 'staff'])) {
        return abort(403, 'Access denied');
    }
    return view('tenant.calendar', $data);
})->name('tenant.calendar');

// Payments (admin, manager, cashier)
Route::get('/payments', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if (!in_array($data['tenantRole'], ['admin', 'manager', 'cashier'])) {
        return abort(403, 'Access denied');
    }
    return view('tenant.payments', $data);
})->name('tenant.payments');

// Analytics (admin, manager)
Route::get('/analytics', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if (!in_array($data['tenantRole'], ['admin', 'manager'])) {
        return abort(403, 'Access denied');
    }
    return view('tenant.analytics', $data);
})->name('tenant.analytics');

// Reports (admin, manager)
Route::get('/reports', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if (!in_array($data['tenantRole'], ['admin', 'manager'])) {
        return abort(403, 'Access denied');
    }
    return view('tenant.reports', $data);
})->name('tenant.reports');

// =============================================================================
// Admin-Only Routes
// =============================================================================

// User Management (admin only)
Route::get('/admin/users', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if ($data['tenantRole'] !== 'admin') {
        return abort(403, 'Admin access required');
    }
    return view('tenant.admin.users', $data);
})->name('tenant.admin.users');

// Roles & Permissions (admin only)
Route::get('/admin/roles', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if ($data['tenantRole'] !== 'admin') {
        return abort(403, 'Admin access required');
    }
    return view('tenant.admin.roles', $data);
})->name('tenant.admin.roles');

// Pending Approvals (admin only)
Route::get('/admin/approvals', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if ($data['tenantRole'] !== 'admin') {
        return abort(403, 'Admin access required');
    }
    return view('tenant.admin.approvals', $data);
})->name('tenant.admin.approvals');

// Settings (admin only)
Route::get('/admin/settings', function (Request $request) use ($requireTenantAuth, $getTenantViewData) {
    if ($redirect = $requireTenantAuth($request)) return $redirect;
    $data = $getTenantViewData($request);
    if ($data['tenantRole'] !== 'admin') {
        return abort(403, 'Admin access required');
    }
    return view('tenant.admin.settings', $data);
})->name('tenant.admin.settings');
