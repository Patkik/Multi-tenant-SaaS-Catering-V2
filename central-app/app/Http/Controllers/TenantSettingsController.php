<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTenantSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class TenantSettingsController extends Controller
{
    private const DEFAULT_SETTINGS = [
        'timezone' => 'Asia/Manila',
        'date_format' => 'MMMM DD, YYYY',
        'default_guest_capacity' => 120,
        'reminder_schedule' => '48_hours',
        'auto_invoice_after_event' => true,
        'two_factor_required_for_admin' => true,
        'webhook_url' => '',
    ];

    public function show(): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        return response()->json([
            'data' => $this->normalizeSettings($tenant->getAttribute('settings')),
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant context is required.',
            ], 400);
        }

        $validated = $request->validated();
        $currentSettings = $this->normalizeSettings($tenant->getAttribute('settings'));
        $nextSettings = array_merge($currentSettings, $validated);

        if (array_key_exists('webhook_url', $validated) && $validated['webhook_url'] === null) {
            $nextSettings['webhook_url'] = '';
        }

        $tenant->setAttribute('settings', $nextSettings);
        $tenant->save();

        return response()->json([
            'data' => [
                ...$this->normalizeSettings($tenant->getAttribute('settings')),
                'updated_at' => optional($tenant->updated_at)->toIso8601String(),
            ],
        ]);
    }

    private function normalizeSettings(mixed $settings): array
    {
        if (! is_array($settings)) {
            $settings = [];
        }

        return [
            'timezone' => (string) Arr::get($settings, 'timezone', self::DEFAULT_SETTINGS['timezone']),
            'date_format' => (string) Arr::get($settings, 'date_format', self::DEFAULT_SETTINGS['date_format']),
            'default_guest_capacity' => (int) Arr::get($settings, 'default_guest_capacity', self::DEFAULT_SETTINGS['default_guest_capacity']),
            'reminder_schedule' => (string) Arr::get($settings, 'reminder_schedule', self::DEFAULT_SETTINGS['reminder_schedule']),
            'auto_invoice_after_event' => (bool) Arr::get($settings, 'auto_invoice_after_event', self::DEFAULT_SETTINGS['auto_invoice_after_event']),
            'two_factor_required_for_admin' => (bool) Arr::get($settings, 'two_factor_required_for_admin', self::DEFAULT_SETTINGS['two_factor_required_for_admin']),
            'webhook_url' => (string) Arr::get($settings, 'webhook_url', self::DEFAULT_SETTINGS['webhook_url']),
        ];
    }
}
