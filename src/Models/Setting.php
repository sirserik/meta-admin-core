<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value settings row. `value` is stored as JSON (see `array` cast)
 * so a single row can hold translations: `{"ru":"...","kk":"...","en":"..."}`.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['key', 'group', 'value', 'type', 'description'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Read a setting's localized value with graceful locale fallback.
     *
     *   setting('university.name', 'ETU')        // current locale
     *   setting('university.name', 'ETU', 'kk')  // force Kazakh
     */
    public static function get(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $value = Cache::remember("setting_{$key}", 3600, function () use ($key) {
            $row = static::query()->where('key', $key)->first();
            return $row?->value;
        });

        if ($value === null) return $default;

        if (is_array($value)) {
            $locale ??= app()->getLocale();
            return $value[$locale] ?? $value['ru'] ?? $default;
        }
        return $value;
    }

    /**
     * Write a setting. Scalars get expanded into {ru,kk,en} so partial
     * translations fall back gracefully.
     */
    public static function set(
        string $key,
        mixed $value,
        string $type = 'text',
        ?string $description = null,
        string $group = 'general',
    ): self {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value'       => is_array($value) ? $value : ['ru' => $value, 'kk' => $value, 'en' => $value],
                'type'        => $type,
                'group'       => $group,
                'description' => $description,
            ],
        );
    }

    public static function getGroups(): array
    {
        return static::query()->distinct()->pluck('group')->sort()->values()->toArray();
    }

    public static function getGrouped(): array
    {
        return static::query()->orderBy('group')->orderBy('key')->get()->groupBy('group')->toArray();
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s)   => Cache::forget("setting_{$s->key}"));
        static::deleted(fn (Setting $s) => Cache::forget("setting_{$s->key}"));
    }
}
