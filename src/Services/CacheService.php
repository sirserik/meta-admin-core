<?php

namespace Meta\AdminCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Thin wrapper around Laravel's Cache that tracks which keys belong
 * to which logical "group" so the admin UI can invalidate a whole
 * group (e.g. «Страницы», «Новости») without flushing the entire
 * cache.
 *
 * Groups are read from `config('admin-core.cache_groups')` so consumer
 * apps can add their own sections via config publish.
 */
class CacheService
{
    /**
     * Default groups — consumer apps merge/override via
     * config('admin-core.cache_groups').
     */
    public const DEFAULT_GROUPS = [
        'pages'    => ['ttl' => 3600,  'label' => 'Страницы',       'icon' => 'fa-file-alt'],
        'blocks'   => ['ttl' => 3600,  'label' => 'Блоки контента', 'icon' => 'fa-cubes'],
        'news'     => ['ttl' => 1800,  'label' => 'Новости',        'icon' => 'fa-newspaper'],
        'settings' => ['ttl' => 86400, 'label' => 'Настройки',      'icon' => 'fa-cog'],
        'menus'    => ['ttl' => 86400, 'label' => 'Меню',           'icon' => 'fa-bars'],
        'schools'  => ['ttl' => 3600,  'label' => 'Школы',          'icon' => 'fa-university'],
        'programs' => ['ttl' => 3600,  'label' => 'Программы',      'icon' => 'fa-graduation-cap'],
        'views'    => ['ttl' => 1800,  'label' => 'Кэш страниц',    'icon' => 'fa-globe'],
    ];

    private const REGISTRY_KEY = 'cache_registry';

    public static function groups(): array
    {
        $config = (array) config('admin-core.cache_groups', []);
        return $config ?: self::DEFAULT_GROUPS;
    }

    public static function remember(string $group, string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        $ttl ??= (self::groups()[$group]['ttl'] ?? 3600);
        self::registerKey($group, $key);
        return Cache::remember($key, $ttl, $callback);
    }

    public static function put(string $group, string $key, mixed $value, ?int $ttl = null): void
    {
        $ttl ??= (self::groups()[$group]['ttl'] ?? 3600);
        self::registerKey($group, $key);
        Cache::put($key, $value, $ttl);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);
        self::unregisterKey($key);
    }

    public static function flush(string $group): int
    {
        $registry = self::getRegistry();
        $keys     = $registry[$group] ?? [];
        $count    = 0;

        foreach ($keys as $key) {
            if (Cache::forget($key)) $count++;
        }

        $registry[$group] = [];
        Cache::put(self::REGISTRY_KEY, $registry, 86400 * 30);

        return $count;
    }

    public static function flushGroups(array $groups): int
    {
        $total = 0;
        foreach ($groups as $group) {
            $total += self::flush($group);
        }
        return $total;
    }

    /**
     * Nuke absolutely everything, including compiled views (Plesk
     * sometimes serves stale blade output from disk).
     */
    public static function flushAll(): void
    {
        Cache::flush();

        $cachePath = storage_path('framework/cache/data');
        if (File::isDirectory($cachePath)) {
            File::cleanDirectory($cachePath);
        }

        $viewsPath = storage_path('framework/views');
        if (File::isDirectory($viewsPath)) {
            foreach (File::glob($viewsPath . '/*.php') as $file) {
                File::delete($file);
            }
        }
    }

    public static function getStats(): array
    {
        $registry   = self::getRegistry();
        $stats      = [];
        $totalKeys  = 0;

        foreach (self::groups() as $group => $info) {
            $keys = $registry[$group] ?? [];
            $activeCount = 0;
            foreach ($keys as $key) {
                if (Cache::has($key)) $activeCount++;
            }

            $stats[$group] = [
                'label'      => $info['label'],
                'icon'       => $info['icon'] ?? 'fa-database',
                'ttl'        => $info['ttl'],
                'ttl_human'  => self::humanTtl($info['ttl']),
                'registered' => count($keys),
                'active'     => $activeCount,
            ];
            $totalKeys += $activeCount;
        }

        $cacheSize = 0;
        $cachePath = storage_path('framework/cache/data');
        if (File::isDirectory($cachePath)) {
            foreach (File::allFiles($cachePath) as $file) {
                $cacheSize += $file->getSize();
            }
        }

        return [
            'groups'     => $stats,
            'total_keys' => $totalKeys,
            'cache_size' => self::humanSize($cacheSize),
            'driver'     => config('cache.default'),
        ];
    }

    private static function registerKey(string $group, string $key): void
    {
        $registry = self::getRegistry();
        $registry[$group] ??= [];
        if (!in_array($key, $registry[$group], true)) {
            $registry[$group][] = $key;
            Cache::put(self::REGISTRY_KEY, $registry, 86400 * 30);
        }
    }

    private static function unregisterKey(string $key): void
    {
        $registry = self::getRegistry();
        foreach ($registry as $group => &$keys) {
            $keys = array_values(array_filter($keys, fn ($k) => $k !== $key));
        }
        Cache::put(self::REGISTRY_KEY, $registry, 86400 * 30);
    }

    private static function getRegistry(): array
    {
        return Cache::get(self::REGISTRY_KEY, []);
    }

    private static function humanTtl(int $seconds): string
    {
        if ($seconds >= 86400) return round($seconds / 86400) . ' д.';
        if ($seconds >= 3600)  return round($seconds / 3600) . ' ч.';
        if ($seconds >= 60)    return round($seconds / 60) . ' мин.';
        return $seconds . ' сек.';
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
