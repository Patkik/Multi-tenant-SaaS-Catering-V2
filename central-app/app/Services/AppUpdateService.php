<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AppUpdateService
{
    private const OUTPUT_PREVIEW_MAX_LENGTH = 2000;
    private const VERSION_STATE_PATH = 'app/system-release-version.txt';

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns the currently installed version.
     *
     * Priority:
     *   1. Persisted state file  (storage/app/system-release-version.txt)
     *   2. config('app.version') / package.json
     *   3. Fallback: '0.0.0'
     *
     * The persisted file is written either by applyLatestRelease() after a
     * successful update, or by syncVersionFromGitHub() / syncCurrentVersion().
     */
    public function currentVersion(): string
    {
        if (app()->environment('testing')) {
            return $this->resolveConfigVersion();
        }

        return $this->readPersistedCurrentVersion() ?? $this->resolveConfigVersion();
    }

    /**
     * Fetch the latest GitHub release and compare against the current version.
     */
    public function latestRelease(): array
    {
        $currentVersion = $this->currentVersion();
        $repository     = $this->resolveRepository();
        $applyMode      = $this->resolveApplyMode();
        $canApply       = $applyMode === 'command';

        if ($repository === '') {
            return $this->buildResponse([
                'enabled'          => false,
                'repository'       => null,
                'current_version'  => $currentVersion,
                'latest_version'   => null,
                'latest_tag'       => null,
                'comparison_mode'  => null,
                'update_available' => false,
                'apply_mode'       => $applyMode,
                'can_apply'        => $canApply,
                'release_name'     => null,
                'release_url'      => null,
                'published_at'     => null,
                'error'            => 'APP_UPDATE_GITHUB_REPOSITORY is not configured.',
            ]);
        }

        $cacheTtl  = max((int) config('services.app_updates.cache_ttl', 300), 30);
        $cacheKey  = $this->cacheKeyForRepository($repository);
        $release   = $this->rememberLatestReleasePayload($cacheKey, $cacheTtl, $repository);

        if (! Arr::get($release, 'ok', false)) {
            return $this->buildResponse([
                'enabled'          => true,
                'repository'       => $repository,
                'current_version'  => $currentVersion,
                'latest_version'   => null,
                'latest_tag'       => null,
                'comparison_mode'  => null,
                'update_available' => false,
                'apply_mode'       => $applyMode,
                'can_apply'        => $canApply,
                'release_name'     => null,
                'release_url'      => null,
                'published_at'     => null,
                'error'            => (string) Arr::get($release, 'error', 'Unable to check updates right now.'),
            ]);
        }

        $latestTag  = (string) Arr::get($release, 'tag_name', '');
        $comparison = $this->compareVersions($currentVersion, $latestTag);

        return $this->buildResponse([
            'enabled'          => true,
            'repository'       => $repository,
            'current_version'  => $currentVersion,
            'latest_version'   => Arr::get($comparison, 'latest_version'),
            'latest_tag'       => $latestTag !== '' ? $latestTag : null,
            'comparison_mode'  => Arr::get($comparison, 'mode'),
            'update_available' => (bool) Arr::get($comparison, 'update_available', false),
            'apply_mode'       => $applyMode,
            'can_apply'        => $canApply,
            'release_name'     => Arr::get($release, 'name') ?: null,
            'release_url'      => Arr::get($release, 'html_url') ?: null,
            'published_at'     => Arr::get($release, 'published_at'),
            'error'            => null,
        ]);
    }

    /**
     * Attempt to apply the latest release via a configured shell command.
     * After a successful run the persisted version is bumped to the release tag.
     */
    public function applyLatestRelease(): array
    {
        $release = $this->latestRelease();

        if (! Arr::get($release, 'enabled', false)) {
            return [
                'status'      => 'unavailable',
                'message'     => (string) Arr::get($release, 'error', 'Release checks are not configured.'),
                'release'     => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if (! Arr::get($release, 'update_available', false)) {
            return [
                'status'      => 'up_to_date',
                'message'     => 'This web system is already on the latest release.',
                'release'     => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if (! Arr::get($release, 'can_apply', false)) {
            return [
                'status'      => 'manual_required',
                'message'     => 'Automatic update is not configured. Open the latest release and run your deployment workflow.',
                'release_url' => Arr::get($release, 'release_url'),
                'release'     => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        $command = trim((string) config('services.app_updates.apply_command', ''));

        if ($command === '') {
            return [
                'status'      => 'manual_required',
                'message'     => 'Automatic update command is empty. Configure APP_UPDATE_APPLY_COMMAND.',
                'release_url' => Arr::get($release, 'release_url'),
                'release'     => $this->releaseSummary($release),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        $timeout = max((int) config('services.app_updates.apply_timeout', 900), 30);

        try {
            $result = Process::path(base_path())->timeout($timeout)->run($command);
        } catch (\Throwable $e) {
            return [
                'status'      => 'failed',
                'message'     => 'Failed to start update command.',
                'release'     => $this->releaseSummary($release),
                'error'       => $e->getMessage(),
                'executed_at' => now()->toIso8601String(),
            ];
        }

        if ($result->failed()) {
            return [
                'status'       => 'failed',
                'message'      => 'Update command failed. Review command output before retrying.',
                'release'      => $this->releaseSummary($release),
                'exit_code'    => $result->exitCode(),
                'output'       => $this->summarizeOutput($result->output()),
                'error_output' => $this->summarizeOutput($result->errorOutput()),
                'executed_at'  => now()->toIso8601String(),
            ];
        }

        // Persist the new version from the GitHub tag
        $newVersion = $this->normalizeVersion(
            (string) (Arr::get($release, 'latest_tag') ?: Arr::get($release, 'latest_version', ''))
        );

        if ($newVersion !== '') {
            $this->persistCurrentVersion($newVersion);
        }

        $this->forgetReleaseCacheForCurrentRepo();
        $refreshedRelease = $this->latestRelease();

        return [
            'status'       => 'applied',
            'message'      => 'Update applied successfully. Verify system health and background workers.',
            'release'      => $this->releaseSummary($refreshedRelease),
            'exit_code'    => $result->exitCode(),
            'output'       => $this->summarizeOutput($result->output()),
            'error_output' => $this->summarizeOutput($result->errorOutput()),
            'executed_at'  => now()->toIso8601String(),
        ];
    }

    /**
     * Sync the locally persisted version to exactly match the latest GitHub
     * release tag.  This is the correct way to mark the app as "up-to-date"
     * when the code was deployed manually (git pull, Docker rebuild, etc.)
     * without running the apply command.
     *
     * It bypasses the stale package.json version and uses GitHub as the
     * single source of truth.
     */
    public function syncVersionFromGitHub(): array
    {
        $repository = $this->resolveRepository();

        $pullOutput = '';
        try {
            $result = Process::path(base_path())->timeout(120)->run('git pull');
            $pullOutput = $this->summarizeOutput($result->output() . "\n" . $result->errorOutput());
        } catch (\Throwable $e) {
            $pullOutput = 'Failed to run git pull: ' . $e->getMessage();
        }

        if ($repository === '') {
            return [
                'status'           => 'failed',
                'message'          => 'APP_UPDATE_GITHUB_REPOSITORY is not configured.',
                'previous_version' => $this->currentVersion(),
                'current_version'  => $this->currentVersion(),
                'pull_output'      => $pullOutput,
                'synced_at'        => now()->toIso8601String(),
            ];
        }

        // Bust the cache so we always get a fresh answer from GitHub
        $cacheKey = $this->cacheKeyForRepository($repository);
        $this->forgetReleaseCache($cacheKey);

        $release = $this->fetchLatestGithubRelease($repository);

        if (! Arr::get($release, 'ok', false)) {
            return [
                'status'           => 'failed',
                'message'          => (string) Arr::get($release, 'error', 'Could not reach GitHub API.'),
                'previous_version' => $this->currentVersion(),
                'current_version'  => $this->currentVersion(),
                'pull_output'      => $pullOutput,
                'synced_at'        => now()->toIso8601String(),
            ];
        }

        $latestTag      = (string) Arr::get($release, 'tag_name', '');
        $latestVersion  = $this->normalizeVersion($latestTag);
        $previousVersion = $this->currentVersion();

        if ($latestVersion === '') {
            return [
                'status'           => 'failed',
                'message'          => 'GitHub returned an empty tag name.',
                'previous_version' => $previousVersion,
                'current_version'  => $previousVersion,
                'pull_output'      => $pullOutput,
                'synced_at'        => now()->toIso8601String(),
            ];
        }

        $this->persistCurrentVersion($latestVersion);

        Log::info('App version synced from GitHub', [
            'previous' => $previousVersion,
            'synced'   => $latestVersion,
            'tag'      => $latestTag,
            'pull'     => $pullOutput,
        ]);

        return [
            'status'           => 'synced',
            'message'          => $latestVersion === $previousVersion
                ? sprintf('Version already matched GitHub at v%s.', $latestVersion)
                : sprintf('Version synced from GitHub v%s → v%s.', $previousVersion, $latestVersion),
            'previous_version' => $previousVersion,
            'current_version'  => $latestVersion,
            'latest_tag'       => $latestTag,
            'pull_output'      => $pullOutput,
            'synced_at'        => now()->toIso8601String(),
        ];
    }

    /**
     * Legacy sync: aligns the persisted file with config('app.version') /
     * package.json.  Kept for backward compatibility but prefer
     * syncVersionFromGitHub() for accurate results.
     */
    public function syncCurrentVersion(): array
    {
        $resolvedVersion = $this->normalizeVersion((string) config('app.version', '0.0.0'));
        $previousVersion = $this->currentVersion();

        if ($resolvedVersion === '' || $resolvedVersion === '0.0.0') {
            // Fall back to GitHub sync when package.json is also missing/stale
            return $this->syncVersionFromGitHub();
        }

        $this->persistCurrentVersion($resolvedVersion);
        $this->forgetReleaseCacheForCurrentRepo();

        return [
            'status'           => 'synced',
            'message'          => $resolvedVersion === $previousVersion
                ? sprintf('Version already synced at v%s.', $resolvedVersion)
                : sprintf('Version synced to v%s.', $resolvedVersion),
            'previous_version' => $previousVersion,
            'current_version'  => $resolvedVersion,
            'synced_at'        => now()->toIso8601String(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // GitHub API
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchLatestGithubRelease(string $repository): array
    {
        $token = trim((string) config('services.app_updates.github_token', ''));

        $request = Http::acceptJson()
            ->timeout(10)
            ->withOptions([
                'verify' => (bool) config('services.app_updates.github_verify_tls', true),
            ])
            ->withHeaders([
                'User-Agent' => 'CaterPro-App-Updates/1.0',
            ]);

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->get("https://api.github.com/repos/{$repository}/releases/latest");
        } catch (\Throwable $e) {
            Log::warning('GitHub API unreachable', ['error' => $e->getMessage()]);

            return [
                'ok'    => false,
                'error' => 'GitHub API is unreachable. Check APP_UPDATE_GITHUB_VERIFY_TLS and network access.',
            ];
        }

        // 404 = no releases published yet; treat as "no update available"
        if ($response->status() === 404) {
            return [
                'ok'    => false,
                'error' => 'No releases have been published on this repository yet.',
            ];
        }

        if ($response->failed()) {
            Log::warning('GitHub API request failed', [
                'status'     => $response->status(),
                'repository' => $repository,
            ]);

            return [
                'ok'    => false,
                'error' => sprintf('GitHub API responded with HTTP %d.', $response->status()),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload) || empty($payload['tag_name'])) {
            return [
                'ok'    => false,
                'error' => 'GitHub API returned an invalid or empty payload.',
            ];
        }

        return [
            'ok'           => true,
            'tag_name'     => (string) Arr::get($payload, 'tag_name', ''),
            'name'         => (string) Arr::get($payload, 'name', ''),
            'html_url'     => (string) Arr::get($payload, 'html_url', ''),
            'published_at' => Arr::get($payload, 'published_at'),
            'draft'        => (bool) Arr::get($payload, 'draft', false),
            'prerelease'   => (bool) Arr::get($payload, 'prerelease', false),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Version helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function compareVersions(string $currentVersion, string $latestTag): array
    {
        if ($latestTag === '') {
            return ['mode' => null, 'latest_version' => null, 'update_available' => false];
        }

        $normalizedCurrent = $this->normalizeVersion($currentVersion);
        $normalizedLatest  = $this->normalizeVersion($latestTag);

        if ($this->isSemverLike($normalizedCurrent) && $this->isSemverLike($normalizedLatest)) {
            return [
                'mode'             => 'semver',
                'latest_version'   => $normalizedLatest,
                'update_available' => version_compare($normalizedLatest, $normalizedCurrent, '>'),
            ];
        }

        return [
            'mode'             => 'tag',
            'latest_version'   => $latestTag,
            'update_available' => strcasecmp($latestTag, $currentVersion) !== 0,
        ];
    }

    private function normalizeVersion(string $version): string
    {
        return (string) preg_replace('/^v(?=\d)/i', '', trim($version));
    }

    private function isSemverLike(string $version): bool
    {
        return preg_match('/^\d+(?:\.\d+){0,2}(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }

    /**
     * Read the configured version from app config / package.json.
     * Returns '0.0.0' when nothing is set.
     */
    private function resolveConfigVersion(): string
    {
        $v = $this->normalizeVersion((string) config('app.version', '0.0.0'));

        return $v !== '' ? $v : '0.0.0';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Persistence
    // ──────────────────────────────────────────────────────────────────────────

    private function readPersistedCurrentVersion(): ?string
    {
        $path = $this->versionStateFilePath();

        try {
            if (! File::exists($path)) {
                return null;
            }

            $stored = trim((string) File::get($path));

            if ($stored === '') {
                return null;
            }

            $normalized = $this->normalizeVersion($stored);

            return $normalized !== '' ? $normalized : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function persistCurrentVersion(string $version): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $normalized = $this->normalizeVersion($version);

        if ($normalized === '') {
            return;
        }

        $path = $this->versionStateFilePath();

        try {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $normalized);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist version state file', ['error' => $e->getMessage()]);
        }
    }

    private function versionStateFilePath(): string
    {
        return storage_path(self::VERSION_STATE_PATH);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Cache
    // ──────────────────────────────────────────────────────────────────────────

    private function cacheKeyForRepository(string $repository): string
    {
        return 'app-updates:github:' . sha1($repository);
    }

    private function rememberLatestReleasePayload(string $cacheKey, int $ttl, string $repository): array
    {
        try {
            return Cache::remember($cacheKey, now()->addSeconds($ttl), function () use ($repository) {
                return $this->fetchLatestGithubRelease($repository);
            });
        } catch (\Throwable) {
            return $this->fetchLatestGithubRelease($repository);
        }
    }

    private function forgetReleaseCache(string $cacheKey): void
    {
        try {
            Cache::forget($cacheKey);
        } catch (\Throwable) {
            // Best-effort cache eviction.
        }
    }

    private function forgetReleaseCacheForCurrentRepo(): void
    {
        $repository = $this->resolveRepository();

        if ($repository !== '') {
            $this->forgetReleaseCache($this->cacheKeyForRepository($repository));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Misc helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveRepository(): string
    {
        return trim((string) config('services.app_updates.github_repository', ''));
    }

    private function resolveApplyMode(): string
    {
        $command = trim((string) config('services.app_updates.apply_command', ''));

        return $command === '' ? 'manual' : 'command';
    }

    /**
     * Stamp the standard envelope keys that are always present in a response.
     */
    private function buildResponse(array $fields): array
    {
        return array_merge($fields, [
            'provider'   => 'github',
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function releaseSummary(array $release): array
    {
        return [
            'current_version'  => Arr::get($release, 'current_version'),
            'latest_version'   => Arr::get($release, 'latest_version'),
            'latest_tag'       => Arr::get($release, 'latest_tag'),
            'update_available' => (bool) Arr::get($release, 'update_available', false),
            'apply_mode'       => Arr::get($release, 'apply_mode', 'manual'),
            'can_apply'        => (bool) Arr::get($release, 'can_apply', false),
            'release_url'      => Arr::get($release, 'release_url'),
        ];
    }

    private function summarizeOutput(string $output): ?string
    {
        $trimmed = trim($output);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) <= self::OUTPUT_PREVIEW_MAX_LENGTH) {
            return $trimmed;
        }

        return mb_substr($trimmed, 0, self::OUTPUT_PREVIEW_MAX_LENGTH) . '... [truncated]';
    }
}
