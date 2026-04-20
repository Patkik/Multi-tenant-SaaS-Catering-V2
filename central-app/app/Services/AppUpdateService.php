<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class AppUpdateService
{
    private const OUTPUT_PREVIEW_MAX_LENGTH = 2000;
    private const VERSION_STATE_PATH = 'app/system-release-version.txt';

    public function currentVersion(): string
    {
        $configuredVersion = $this->normalizeVersion((string) config('app.version', '0.0.0'));

        if (app()->environment('testing')) {
            return $configuredVersion !== '' ? $configuredVersion : '0.0.0';
        }

        $persistedVersion = $this->readPersistedCurrentVersion();

        if ($persistedVersion !== null) {
            return $persistedVersion;
        }

        $initialVersion = $configuredVersion !== '' ? $configuredVersion : '0.0.0';
        $this->persistCurrentVersion($initialVersion);

        return $initialVersion;
    }

    public function latestRelease(): array
    {
        $currentVersion = $this->currentVersion();
        $repository = trim((string) config('services.app_updates.github_repository', ''));
        $applyMode = $this->resolveApplyMode();
        $canApply = $applyMode === 'command';

        if ($repository === '') {
            return [
                'enabled' => false,
                'provider' => 'github',
                'repository' => null,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'latest_tag' => null,
                'comparison_mode' => null,
                'update_available' => false,
                'apply_mode' => $applyMode,
                'can_apply' => $canApply,
                'release_name' => null,
                'release_url' => null,
                'published_at' => null,
                'error' => 'APP_UPDATE_GITHUB_REPOSITORY is not configured.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $cacheTtlSeconds = max((int) config('services.app_updates.cache_ttl', 300), 30);
        $cacheKey = $this->cacheKeyForRepository($repository);

        $release = $this->rememberLatestReleasePayload($cacheKey, $cacheTtlSeconds, $repository);

        if (! Arr::get($release, 'ok', false)) {
            return [
                'enabled' => true,
                'provider' => 'github',
                'repository' => $repository,
                'current_version' => $currentVersion,
                'latest_version' => null,
                'latest_tag' => null,
                'comparison_mode' => null,
                'update_available' => false,
                'apply_mode' => $applyMode,
                'can_apply' => $canApply,
                'release_name' => null,
                'release_url' => null,
                'published_at' => null,
                'error' => (string) Arr::get($release, 'error', 'Unable to check updates right now.'),
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $latestTag = (string) Arr::get($release, 'tag_name', '');
        $comparison = $this->compareVersions($currentVersion, $latestTag);

        return [
            'enabled' => true,
            'provider' => 'github',
            'repository' => $repository,
            'current_version' => $currentVersion,
            'latest_version' => Arr::get($comparison, 'latest_version'),
            'latest_tag' => $latestTag !== '' ? $latestTag : null,
            'comparison_mode' => Arr::get($comparison, 'mode'),
            'update_available' => (bool) Arr::get($comparison, 'update_available', false),
            'apply_mode' => $applyMode,
            'can_apply' => $canApply,
            'release_name' => Arr::get($release, 'name') ?: null,
            'release_url' => Arr::get($release, 'html_url') ?: null,
            'published_at' => Arr::get($release, 'published_at'),
            'error' => null,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    public function applyLatestRelease(): array
    {
        $release = $this->latestRelease();

        if (! Arr::get($release, 'enabled', false)) {
            return [
                'status' => 'unavailable',
                'message' => (string) Arr::get($release, 'error', 'Release checks are not configured.'),
                'release' => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if (! Arr::get($release, 'update_available', false)) {
            return [
                'status' => 'up_to_date',
                'message' => 'This web system is already on the latest release.',
                'release' => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if (! Arr::get($release, 'can_apply', false)) {
            return [
                'status' => 'manual_required',
                'message' => 'Automatic update is not configured. Open the latest release and run your deployment workflow.',
                'release_url' => Arr::get($release, 'release_url'),
                'release' => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        $command = trim((string) config('services.app_updates.apply_command', ''));

        if ($command === '') {
            return [
                'status' => 'manual_required',
                'message' => 'Automatic update command is empty. Configure APP_UPDATE_APPLY_COMMAND first.',
                'release_url' => Arr::get($release, 'release_url'),
                'release' => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        $timeoutSeconds = max((int) config('services.app_updates.apply_timeout', 900), 30);

        try {
            $result = Process::path(base_path())
                ->timeout($timeoutSeconds)
                ->run($command);
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => 'Failed to start update command.',
                'release' => $this->releaseSummary($release),
                'error' => $exception->getMessage(),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if ($result->failed()) {
            return [
                'status' => 'failed',
                'message' => 'Update command failed. Review command output before retrying.',
                'release' => $this->releaseSummary($release),
                'exit_code' => $result->exitCode(),
                'output' => $this->summarizeOutput($result->output()),
                'error_output' => $this->summarizeOutput($result->errorOutput()),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        $appliedVersion = $this->normalizeVersion((string) (Arr::get($release, 'latest_tag') ?: Arr::get($release, 'latest_version')));

        if ($appliedVersion !== '') {
            $this->persistCurrentVersion($appliedVersion);
        }

        $repository = trim((string) config('services.app_updates.github_repository', ''));

        if ($repository !== '') {
            $this->forgetReleaseCache($this->cacheKeyForRepository($repository));
        }

        $refreshedRelease = $this->latestRelease();

        return [
            'status' => 'applied',
            'message' => 'Update command completed. Verify system health and background workers.',
            'release' => $this->releaseSummary($refreshedRelease),
            'exit_code' => $result->exitCode(),
            'output' => $this->summarizeOutput($result->output()),
            'error_output' => $this->summarizeOutput($result->errorOutput()),
            'executed_at' => now()->toIso8601String(),
        ];
    }

    public function syncCurrentVersion(): array
    {
        $resolvedVersion = $this->normalizeVersion((string) config('app.version', '0.0.0'));
        $previousVersion = $this->currentVersion();

        if ($resolvedVersion === '') {
            return [
                'status' => 'failed',
                'message' => 'Unable to resolve the application version from configuration.',
                'previous_version' => $previousVersion,
                'current_version' => $previousVersion,
                'synced_at' => now()->toIso8601String(),
            ];
        }

        $this->persistCurrentVersion($resolvedVersion);

        $repository = trim((string) config('services.app_updates.github_repository', ''));

        if ($repository !== '') {
            $this->forgetReleaseCache($this->cacheKeyForRepository($repository));
        }

        return [
            'status' => 'synced',
            'message' => $resolvedVersion === $previousVersion
                ? sprintf('Version already synced at v%s.', $resolvedVersion)
                : sprintf('Version synced to v%s.', $resolvedVersion),
            'previous_version' => $previousVersion,
            'current_version' => $resolvedVersion,
            'synced_at' => now()->toIso8601String(),
        ];
    }

    private function fetchLatestGithubRelease(string $repository): array
    {
        $request = Http::acceptJson()
            ->timeout(8)
            ->withOptions([
                'verify' => (bool) config('services.app_updates.github_verify_tls', true),
            ])
            ->withHeaders([
                'User-Agent' => 'CaterPro-App-Updates',
            ]);
        $token = trim((string) config('services.app_updates.github_token', ''));

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (\Throwable) {
            return [
                'ok' => false,
                'error' => 'GitHub API is unreachable right now. Check APP_UPDATE_GITHUB_VERIFY_TLS and local CA certificates.',
            ];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'error' => sprintf('GitHub API request failed (%d).', $response->status()),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [
                'ok' => false,
                'error' => 'GitHub API returned an invalid payload.',
            ];
        }

        return [
            'ok' => true,
            'tag_name' => (string) Arr::get($payload, 'tag_name', ''),
            'name' => (string) Arr::get($payload, 'name', ''),
            'html_url' => (string) Arr::get($payload, 'html_url', ''),
            'published_at' => Arr::get($payload, 'published_at'),
        ];
    }

    private function compareVersions(string $currentVersion, string $latestTag): array
    {
        if ($latestTag === '') {
            return [
                'mode' => null,
                'latest_version' => null,
                'update_available' => false,
            ];
        }

        $normalizedCurrentVersion = $this->normalizeVersion($currentVersion);
        $normalizedLatestVersion = $this->normalizeVersion($latestTag);
        $isSemverComparable = $this->isSemverLike($normalizedCurrentVersion)
            && $this->isSemverLike($normalizedLatestVersion);

        if ($isSemverComparable) {
            return [
                'mode' => 'semver',
                'latest_version' => $normalizedLatestVersion,
                'update_available' => version_compare($normalizedLatestVersion, $normalizedCurrentVersion, '>'),
            ];
        }

        return [
            'mode' => 'tag',
            'latest_version' => $latestTag,
            'update_available' => strcasecmp($latestTag, $currentVersion) !== 0,
        ];
    }

    private function normalizeVersion(string $version): string
    {
        $trimmedVersion = trim($version);

        return (string) preg_replace('/^v(?=\d)/i', '', $trimmedVersion);
    }

    private function isSemverLike(string $version): bool
    {
        return preg_match('/^\d+(?:\.\d+){0,2}(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }

    private function cacheKeyForRepository(string $repository): string
    {
        return 'app-updates:github:'.sha1($repository);
    }

    private function rememberLatestReleasePayload(string $cacheKey, int $cacheTtlSeconds, string $repository): array
    {
        try {
            return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($repository) {
                return $this->fetchLatestGithubRelease($repository);
            });
        } catch (\Throwable) {
            // Some environments (e.g. tenancy with file cache) cannot support tagged cache stores.
            return $this->fetchLatestGithubRelease($repository);
        }
    }

    private function forgetReleaseCache(string $cacheKey): void
    {
        try {
            Cache::forget($cacheKey);
        } catch (\Throwable) {
            // Best effort cache eviction.
        }
    }

    private function readPersistedCurrentVersion(): ?string
    {
        $path = $this->versionStateFilePath();

        try {
            if (! File::exists($path)) {
                return null;
            }

            $storedVersion = trim((string) File::get($path));

            if ($storedVersion === '') {
                return null;
            }

            $normalizedVersion = $this->normalizeVersion($storedVersion);

            return $normalizedVersion !== '' ? $normalizedVersion : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function persistCurrentVersion(string $version): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $normalizedVersion = $this->normalizeVersion($version);

        if ($normalizedVersion === '') {
            return;
        }

        $path = $this->versionStateFilePath();

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $normalizedVersion);
        } catch (\Throwable) {
            // Best effort persistence.
        }
    }

    private function versionStateFilePath(): string
    {
        return storage_path(self::VERSION_STATE_PATH);
    }

    private function resolveApplyMode(): string
    {
        $command = trim((string) config('services.app_updates.apply_command', ''));

        return $command === '' ? 'manual' : 'command';
    }

    private function releaseSummary(array $release): array
    {
        return [
            'current_version' => Arr::get($release, 'current_version'),
            'latest_version' => Arr::get($release, 'latest_version'),
            'latest_tag' => Arr::get($release, 'latest_tag'),
            'update_available' => (bool) Arr::get($release, 'update_available', false),
            'apply_mode' => Arr::get($release, 'apply_mode', 'manual'),
            'can_apply' => (bool) Arr::get($release, 'can_apply', false),
            'release_url' => Arr::get($release, 'release_url'),
        ];
    }

    private function summarizeOutput(string $output): ?string
    {
        $trimmedOutput = trim($output);

        if ($trimmedOutput === '') {
            return null;
        }

        if (mb_strlen($trimmedOutput) <= self::OUTPUT_PREVIEW_MAX_LENGTH) {
            return $trimmedOutput;
        }

        return mb_substr($trimmedOutput, 0, self::OUTPUT_PREVIEW_MAX_LENGTH).'... [truncated]';
    }
}
