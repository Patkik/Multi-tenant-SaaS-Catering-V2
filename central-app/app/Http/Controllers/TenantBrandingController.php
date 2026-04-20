<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantBrandingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class TenantBrandingController extends Controller
{
    private const DEFAULT_PRIMARY_COLOR = '#0B8F66';

    private const DEFAULT_HEADING_FONT = 'Sora';

    private const DEFAULT_BODY_FONT = 'Plus Jakarta Sans';

    private const DEFAULT_LAYOUT_DENSITY = 'comfortable';

    private const DEFAULT_CARD_RADIUS = 18;

    private const DEFAULT_HERO_MESSAGE = 'Craft memorable event experiences your clients love.';

    private const DEFAULT_HOMEPAGE_SECTIONS = [
        ['id' => 'hero', 'label' => 'Hero Banner', 'enabled' => true],
        ['id' => 'featured-packages', 'label' => 'Featured Packages', 'enabled' => true],
        ['id' => 'testimonials', 'label' => 'Client Testimonials', 'enabled' => true],
        ['id' => 'cta', 'label' => 'Call To Action', 'enabled' => true],
    ];

    public function show(): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        $normalizedBranding = $this->normalizeBranding($branding);

        return response()->json([
            'data' => array_merge([
                'company_name' => $tenant->getAttribute('company_name'),
            ], $normalizedBranding),
        ]);
    }

    public function update(UpdateTenantBrandingRequest $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $validated = $request->validated();
        $branding = $tenant->getAttribute('branding');

        if (! is_array($branding)) {
            $branding = [];
        }

        $normalizedBranding = $this->normalizeBranding($branding);
        $uploadedLogoPath = null;
        $uploadedLogoUrl = null;

        if ($request->hasFile('logo_file')) {
            $logoFile = $request->file('logo_file');

            if ($logoFile !== null) {
                $existingLogoPath = Arr::get($normalizedBranding, 'logo_path');

                if (is_string($existingLogoPath) && $existingLogoPath !== '') {
                    Storage::disk('public')->delete($existingLogoPath);
                }

                $tenantKey = (string) $tenant->getTenantKey();
                $uploadedLogoPath = $logoFile->store("tenant-branding/{$tenantKey}", 'public');
                $uploadedLogoUrl = Storage::disk('public')->url($uploadedLogoPath);
            }
        }

        if (array_key_exists('company_name', $validated)) {
            $tenant->setAttribute('company_name', $validated['company_name']);
        }

        $nextBranding = [
            'primary_color' => array_key_exists('primary_color', $validated)
                ? ($validated['primary_color'] ?? self::DEFAULT_PRIMARY_COLOR)
                : Arr::get($normalizedBranding, 'primary_color', self::DEFAULT_PRIMARY_COLOR),
            'logo_url' => array_key_exists('logo_url', $validated)
                ? ($validated['logo_url'] ?? null)
                : Arr::get($normalizedBranding, 'logo_url'),
            'logo_path' => array_key_exists('logo_path', $validated)
                ? ($validated['logo_path'] ?? null)
                : Arr::get($normalizedBranding, 'logo_path'),
            'heading_font' => array_key_exists('heading_font', $validated)
                ? ($validated['heading_font'] ?? self::DEFAULT_HEADING_FONT)
                : Arr::get($normalizedBranding, 'heading_font', self::DEFAULT_HEADING_FONT),
            'body_font' => array_key_exists('body_font', $validated)
                ? ($validated['body_font'] ?? self::DEFAULT_BODY_FONT)
                : Arr::get($normalizedBranding, 'body_font', self::DEFAULT_BODY_FONT),
            'layout_density' => array_key_exists('layout_density', $validated)
                ? ($validated['layout_density'] ?? self::DEFAULT_LAYOUT_DENSITY)
                : Arr::get($normalizedBranding, 'layout_density', self::DEFAULT_LAYOUT_DENSITY),
            'card_radius' => array_key_exists('card_radius', $validated)
                ? (int) ($validated['card_radius'] ?? self::DEFAULT_CARD_RADIUS)
                : (int) Arr::get($normalizedBranding, 'card_radius', self::DEFAULT_CARD_RADIUS),
            'hero_message' => array_key_exists('hero_message', $validated)
                ? ($validated['hero_message'] ?? self::DEFAULT_HERO_MESSAGE)
                : Arr::get($normalizedBranding, 'hero_message', self::DEFAULT_HERO_MESSAGE),
            'homepage_sections' => array_key_exists('homepage_sections', $validated)
                ? $this->normalizeSections($validated['homepage_sections'])
                : Arr::get($normalizedBranding, 'homepage_sections', self::DEFAULT_HOMEPAGE_SECTIONS),
        ];

        if ($uploadedLogoPath !== null && $uploadedLogoUrl !== null) {
            $nextBranding['logo_url'] = $uploadedLogoUrl;
            $nextBranding['logo_path'] = $uploadedLogoPath;
        }

        $tenant->setAttribute('branding', $nextBranding);

        $tenant->save();

        $savedBranding = $this->normalizeBranding((array) $tenant->getAttribute('branding'));

        return response()->json([
            'data' => array_merge([
                'company_name' => $tenant->getAttribute('company_name'),
            ], $savedBranding),
        ]);
    }

    private function normalizeBranding(array $branding): array
    {
        return [
            'primary_color' => Arr::get($branding, 'primary_color', self::DEFAULT_PRIMARY_COLOR),
            'logo_url' => Arr::get($branding, 'logo_url'),
            'logo_path' => Arr::get($branding, 'logo_path'),
            'heading_font' => Arr::get($branding, 'heading_font', self::DEFAULT_HEADING_FONT),
            'body_font' => Arr::get($branding, 'body_font', self::DEFAULT_BODY_FONT),
            'layout_density' => Arr::get($branding, 'layout_density', self::DEFAULT_LAYOUT_DENSITY),
            'card_radius' => (int) Arr::get($branding, 'card_radius', self::DEFAULT_CARD_RADIUS),
            'hero_message' => Arr::get($branding, 'hero_message', self::DEFAULT_HERO_MESSAGE),
            'homepage_sections' => $this->normalizeSections(Arr::get($branding, 'homepage_sections')),
        ];
    }

    private function normalizeSections(mixed $sections): array
    {
        if (! is_array($sections) || count($sections) === 0) {
            return self::DEFAULT_HOMEPAGE_SECTIONS;
        }

        $normalized = [];

        foreach (array_values($sections) as $index => $section) {
            $normalized[] = [
                'id' => (string) Arr::get($section, 'id', 'section-'.($index + 1)),
                'label' => (string) Arr::get($section, 'label', 'Section '.($index + 1)),
                'enabled' => (bool) Arr::get($section, 'enabled', true),
            ];
        }

        return $normalized;
    }
}
