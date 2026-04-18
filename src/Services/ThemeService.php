<?php

namespace Meta\AdminCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the site's design tokens (colors, fonts, sizes, …) from
 * `config/theme.php` and lets the admin override a handful of high-
 * level knobs (primary color, accent, heading font, …) via a single
 * row in the `settings` table (`key = theme_overrides`, JSON value).
 *
 * Shipped from meta/admin-core so every consumer site gets the same
 * token schema and the same admin-driven overrides out of the box.
 */
class ThemeService
{
    public const CACHE_KEY = 'admin_core_theme_tokens';
    public const CACHE_TTL = 86400; // 24h; invalidated on saveOverrides()

    /**
     * Default token skeleton — keys that always exist even if the
     * consumer's config/theme.php is missing a section. Prevents the
     * admin UI from rendering section headers with no inputs when a
     * fresh site hasn't published the config yet.
     */
    protected const DEFAULT_SECTIONS = [
        'colors', 'fonts', 'font_sizes', 'spacing',
        'radius', 'shadows', 'transitions', 'layout',
    ];

    /**
     * Get all theme tokens, merged with admin overrides from the
     * settings table. Cached for 24h (invalidated by saveOverrides).
     */
    public static function getTokens(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            // `config('theme')` can return null when:
            //   (a) config/theme.php hasn't been published
            //   (b) a stale bootstrap/cache/config.php is missing the file
            // Fall back to requiring the file directly, then skeleton
            // defaults — so the page never renders empty sections.
            $config = config('theme');
            if (!is_array($config)) {
                $path = config_path('theme.php');
                $config = is_file($path) ? require $path : [];
            }
            if (!is_array($config)) {
                $config = [];
            }
            foreach (self::DEFAULT_SECTIONS as $section) {
                $config[$section] ??= [];
            }

            // Merge overrides recursively. The admin UI lets users edit
            // any token (not just primary/accent) so we respect whatever
            // is in the settings row instead of cherry-picking keys.
            $overrides = self::getAdminOverrides();
            if ($overrides) {
                $config = array_replace_recursive($config, $overrides);
            }

            return $config;
        });
    }

    /**
     * Generate the :root CSS with --t-* custom properties. Inline this
     * in your layout head to make the tokens available to Tailwind /
     * CSS.
     */
    public static function generateCSS(): string
    {
        $tokens = self::getTokens();
        $css = ":root {\n";

        foreach (($tokens['colors']      ?? []) as $name => $value) $css .= "  --t-color-{$name}: {$value};\n";
        foreach (($tokens['fonts']       ?? []) as $name => $value) $css .= "  --t-font-{$name}: {$value};\n";
        foreach (($tokens['font_sizes']  ?? []) as $name => $value) $css .= "  --t-size-{$name}: {$value};\n";
        foreach (($tokens['spacing']     ?? []) as $name => $value) $css .= "  --t-space-{$name}: {$value};\n";
        foreach (($tokens['radius']      ?? []) as $name => $value) $css .= "  --t-radius-{$name}: {$value};\n";
        foreach (($tokens['shadows']     ?? []) as $name => $value) $css .= "  --t-shadow-{$name}: {$value};\n";
        foreach (($tokens['transitions'] ?? []) as $name => $value) $css .= "  --t-transition-{$name}: {$value};\n";
        foreach (($tokens['layout']      ?? []) as $name => $value) {
            $name = str_replace('_', '-', $name);
            $css .= "  --t-{$name}: {$value};\n";
        }

        $css .= "}\n";
        return $css;
    }

    /**
     * Get a specific color token with graceful fallback.
     */
    public static function color(string $name): string
    {
        return self::getTokens()['colors'][$name] ?? '#000';
    }

    /**
     * Read overrides from settings.theme_overrides. Returns [] on any
     * failure so first-time installs without a settings table don't
     * crash.
     */
    protected static function getAdminOverrides(): array
    {
        try {
            if (!Schema::hasTable('settings')) return [];
            $row = DB::table('settings')->where('key', 'theme_overrides')->first();
            if (!$row || !$row->value) return [];
            $decoded = json_decode($row->value, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Persist overrides and blow the theme cache.
     */
    public static function saveOverrides(array $overrides): void
    {
        if (!Schema::hasTable('settings')) return;

        DB::table('settings')->updateOrInsert(
            ['key' => 'theme_overrides'],
            [
                'value'      => json_encode($overrides, JSON_UNESCAPED_UNICODE),
                'type'       => 'json',
                'group'      => 'theme',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
