<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\PlanFeatures;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Models\Domain;

class TenantProvisioningService
{
    public function provision(array $payload): array
    {
        $subdomain = Str::lower((string) Arr::get($payload, 'subdomain'));
        $baseDomain = $this->baseDomain();
        $fullDomain = $subdomain.'.'.$baseDomain;
        $normalizedPlan = PlanFeatures::normalizePlan((string) Arr::get($payload, 'plan', 'free'));

        if (in_array($subdomain, config('tenancy.central_domains', []), true) || in_array($fullDomain, config('tenancy.central_domains', []), true)) {
            throw ValidationException::withMessages([
                'subdomain' => ['This subdomain is reserved for the central application.'],
            ]);
        }

        if (Domain::query()->whereIn('domain', [$subdomain, $fullDomain])->exists()) {
            throw ValidationException::withMessages([
                'subdomain' => ['This subdomain is already taken.'],
            ]);
        }

        $tenantId = $this->makeTenantId((string) Arr::get($payload, 'company_name'));

        $tenant = Tenant::create([
            'id' => $tenantId,
            'company_name' => Arr::get($payload, 'company_name'),
            'plan' => $normalizedPlan,
            'enabled_features' => $this->defaultFeatures($normalizedPlan),
            'client_access' => Arr::exists($payload, 'client_access')
                ? (bool) Arr::get($payload, 'client_access')
                : PlanFeatures::supportsClientPortal($normalizedPlan),
            'branding' => [
                'primary_color' => '#0B8F66',
            ],
            'admin' => [
                'username' => Arr::get($payload, 'admin.username'),
                'lastname' => Arr::get($payload, 'admin.lastname'),
                'mi' => Arr::get($payload, 'admin.mi'),
                'firstname' => Arr::get($payload, 'admin.firstname'),
                // The password is hashed and persisted in metadata until tenant user provisioning is implemented.
                'password_hash' => Hash::make((string) Arr::get($payload, 'admin.password')),
            ],
        ]);

        $tenant->domains()->create([
            // Subdomain identification middleware resolves tenants by subdomain key.
            'domain' => $subdomain,
        ]);

        return [
            'tenant_id' => $tenantId,
            'domain' => $fullDomain,
            'plan' => $normalizedPlan,
            'company_name' => Arr::get($payload, 'company_name'),
        ];
    }

    private function makeTenantId(string $companyName): string
    {
        $slug = Str::slug($companyName);

        if ($slug === '') {
            $slug = 'tenant';
        }

        return Str::limit($slug, 24, '').'-'.Str::lower(Str::random(6));
    }

    private function baseDomain(): string
    {
        $domains = config('tenancy.central_domains', ['127.0.0.1']);

        return (string) Arr::first($domains);
    }

    private function defaultFeatures(string $plan): array
    {
        return PlanFeatures::forPlan($plan);
    }
}
