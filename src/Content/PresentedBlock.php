<?php

namespace Meta\AdminCore\Content;

/**
 * Augmented view of a PageBlock, ready for template consumption.
 *
 * Wraps a PageBlock record with a locale-aware accessor layer so templates
 * never have to reach into raw `data['links'][0]['title']['ru']` — access
 * `$block->links[0]['title']` and get the current-locale string.
 *
 * Translations are resolved recursively: at any depth inside `data` or
 * `settings`, an assoc array keyed by locale (`ru`/`kk`/`en`) collapses to
 * the single string for the active locale, with graceful fallback to ru,
 * then kk, then en, then the first value present.
 *
 * Access patterns:
 *   $block->title                     // string (auto-translated)
 *   $block->links                     // array — translatable values resolved
 *   $block->layout                    // scalar from data['layout']
 *   $block->has('links')              // does the data key exist
 *   $block->raw('links')              // untouched JSON-decoded value
 *
 * Safe to pass directly to Blade: `{!! $block->content !!}`,
 * `@foreach ($block->links as $l)`.
 */
class PresentedBlock
{
    public readonly int $id;
    public readonly string $key;
    public readonly string $type;
    public readonly string $title;
    public readonly string $subtitle;
    public readonly string $content;
    public readonly string $status;
    public readonly int $sort;
    public readonly string $locale;

    /** Original raw record — use only when you genuinely need untranslated data. */
    public readonly object $model;

    /** Raw data JSON (array form), pre-augmentation. */
    protected array $data = [];
    protected array $settings = [];

    public function __construct(object $block, string $locale)
    {
        $this->model    = $block;
        $this->locale   = $locale;
        $this->id       = (int) ($block->id ?? 0);
        $this->key      = (string) ($block->block_key ?? '');
        $this->type     = (string) ($block->block_type ?? '');
        $this->status   = (string) ($block->status ?? 'published');
        $this->sort     = (int) ($block->sort_order ?? 0);
        $this->data     = is_array($block->data)     ? $block->data     : [];
        $this->settings = is_array($block->settings) ? $block->settings : [];

        // title/subtitle/content travel through Translatable trait first
        // (preferred — reads from the `translations` morph table) and fall
        // back to the raw column (which may itself be a JSON locale map).
        $this->title    = $this->translatableField($block, 'title');
        $this->subtitle = $this->translatableField($block, 'subtitle');
        $this->content  = $this->translatableField($block, 'content');
    }

    /**
     * Dynamic access: `$block->links`, `$block->layout`, etc. Looks in
     * `data` first, then `settings`. Returns null if not set.
     */
    public function __get(string $name): mixed
    {
        // Back-compat: legacy templates do `$block->data['slides']` and
        // `$block->settings['bg_color']` — give them the raw arrays.
        if ($name === 'data')     return $this->data;
        if ($name === 'settings') return $this->settings;

        if (array_key_exists($name, $this->data)) {
            return $this->resolve($this->data[$name]);
        }
        if (array_key_exists($name, $this->settings)) {
            return $this->resolve($this->settings[$name]);
        }

        // Final fallback: forward to the underlying model for fields
        // like image_url, is_active, slug — untouched.
        if (isset($this->model->{$name})) {
            return $this->model->{$name};
        }
        return null;
    }

    /**
     * Mirrors __get's lookup chain so `isset($block->foo)` / `data_get`
     * / Collection::firstWhere() agree with what the magic getter returns.
     * Also covers the declared readonly props (id/key/type/title/…)
     * so isset() on those is truthful.
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, ['data', 'settings', 'id', 'key', 'type',
            'title', 'subtitle', 'content', 'status', 'sort', 'locale'], true)) {
            return true;
        }
        if (array_key_exists($name, $this->data))     return true;
        if (array_key_exists($name, $this->settings)) return true;
        return isset($this->model->{$name});
    }

    /** True if a data or settings key exists (regardless of value). */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data)
            || array_key_exists($name, $this->settings);
    }

    /** Untouched value — no translation resolution. */
    public function raw(string $name): mixed
    {
        return $this->data[$name]
            ?? $this->settings[$name]
            ?? null;
    }

    /**
     * Backwards-compat shim for templates still calling ->translate().
     * Forwards to the underlying model's translate() if available, or
     * reads the field directly for the given locale.
     */
    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= $this->locale;
        if (method_exists($this->model, 'translate')) {
            return $this->model->translate($field, $locale);
        }
        $raw = $this->model->{$field} ?? null;
        if (is_array($raw)) return $raw[$locale] ?? $raw['ru'] ?? null;
        return $raw;
    }

    /**
     * Pass through arbitrary property access to the underlying model
     * when it's not a data/settings key — templates that still read
     * `$block->image_url`, `$block->is_active`, etc. keep working during
     * migration.
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists($this->model, $name)) {
            return $this->model->{$name}(...$args);
        }
        throw new \BadMethodCallException("Method {$name} not found on PresentedBlock");
    }

    /** Full augmented payload, useful for handing to JSON / API endpoints. */
    public function toArray(): array
    {
        $out = [
            'id'        => $this->id,
            'key'       => $this->key,
            'type'      => $this->type,
            'title'     => $this->title,
            'subtitle'  => $this->subtitle,
            'content'   => $this->content,
            'status'    => $this->status,
            'sort'      => $this->sort,
            'locale'    => $this->locale,
            'data'      => $this->resolve($this->data),
            'settings'  => $this->resolve($this->settings),
        ];
        return $out;
    }

    // -------------------------------------------------------------------
    // Resolution internals.
    // -------------------------------------------------------------------

    /**
     * Recursive translation resolution:
     *  - scalar → unchanged
     *  - assoc keyed by locale names → current locale string
     *  - list → map elements
     *  - other assoc → resolve children
     */
    protected function resolve(mixed $value): mixed
    {
        if ($value === null || is_scalar($value)) return $value;
        if (!is_array($value)) return $value;

        if ($this->looksTranslatable($value)) {
            return $value[$this->locale]
                ?? $value['ru']
                ?? $value['kk']
                ?? $value['en']
                ?? $this->firstNonEmpty($value);
        }

        if (array_is_list($value)) {
            return array_map(fn ($v) => $this->resolve($v), $value);
        }

        $out = [];
        foreach ($value as $k => $v) $out[$k] = $this->resolve($v);
        return $out;
    }

    protected function looksTranslatable(array $value): bool
    {
        if (array_is_list($value)) return false;
        foreach (['ru', 'kk', 'en'] as $loc) {
            if (array_key_exists($loc, $value)) return true;
        }
        return false;
    }

    protected function firstNonEmpty(array $value): mixed
    {
        foreach ($value as $v) if ($v !== null && $v !== '') return $v;
        return '';
    }

    /**
     * Pull a translatable column (title/subtitle/content) using the host
     * model's Translatable trait if available; otherwise decode a
     * JSON-locale-map if the column stores one directly.
     */
    protected function translatableField(object $block, string $field): string
    {
        if (method_exists($block, 'translate')) {
            $v = $block->translate($field, $this->locale);
            if ($v !== null && $v !== '') return (string) $v;
        }
        $raw = $block->{$field} ?? '';
        if (is_array($raw)) {
            return (string) ($raw[$this->locale] ?? $raw['ru'] ?? $raw['kk'] ?? $raw['en'] ?? '');
        }
        if (is_string($raw) && str_starts_with(trim($raw), '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return (string) ($decoded[$this->locale] ?? $decoded['ru'] ?? $decoded['kk'] ?? $decoded['en'] ?? $raw);
            }
        }
        return (string) $raw;
    }
}
