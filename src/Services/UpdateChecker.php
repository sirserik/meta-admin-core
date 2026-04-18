<?php

namespace Meta\AdminCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Polls the meta/admin-core GitHub repo for the latest release tag and
 * compares it to the installed version. Also reads the CHANGELOG so the
 * admin UI can show what's new.
 */
class UpdateChecker
{
    protected const CACHE_KEY = 'admin-core:update-check';
    protected const CACHE_TTL = 3600; // 1h

    protected string $repo = 'sirserik/meta-admin-core';

    public function currentVersion(): string
    {
        // Preferred: read installed.php — reliable on every environment.
        $installed = base_path('vendor/composer/installed.json');
        if (is_file($installed)) {
            $data = json_decode(file_get_contents($installed), true);
            foreach ($data['packages'] ?? [] as $pkg) {
                if (($pkg['name'] ?? '') === 'meta/admin-core') {
                    $v = $pkg['version'] ?? 'dev';
                    return ltrim($v, 'v');
                }
            }
        }
        // Fallback: read package's composer.json (won't have version key
        // unless we tag it; leave as 'dev' in that case).
        $pkgComposer = base_path('vendor/meta/admin-core/composer.json');
        if (is_file($pkgComposer)) {
            $d = json_decode(file_get_contents($pkgComposer), true);
            return ltrim($d['version'] ?? 'dev', 'v');
        }
        return 'unknown';
    }

    /**
     * Returns current+latest versions and update-available flag.
     * Cached for 1h. Set $force to bypass.
     */
    public function check(bool $force = false): array
    {
        if ($force) Cache::forget(self::CACHE_KEY);

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $current = $this->currentVersion();
            $latest = null;
            $changelog = null;
            $error = null;

            try {
                $resp = Http::timeout(10)->get("https://api.github.com/repos/{$this->repo}/releases/latest");
                if ($resp->ok()) {
                    $d = $resp->json();
                    $latest = ltrim($d['tag_name'] ?? '', 'v');
                    $changelog = $d['body'] ?? null;
                } else {
                    // No GitHub Releases? Fall back to tags.
                    $tags = Http::timeout(10)->get("https://api.github.com/repos/{$this->repo}/tags")->json();
                    if (is_array($tags) && !empty($tags)) {
                        $latest = ltrim($tags[0]['name'] ?? '', 'v');
                    }
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }

            $available = $latest && $current !== 'unknown' && $current !== 'dev'
                && version_compare($latest, $current, '>');

            return [
                'current'   => $current,
                'latest'    => $latest ?? 'unknown',
                'available' => $available,
                'changelog' => $changelog,
                'checked_at' => now()->toIso8601String(),
                'error'     => $error,
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
