<?php

namespace Meta\AdminCore\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Meta\AdminCore\Models\Translation;

/**
 * Polymorphic translations without a vendor dependency. Each host
 * model declares `protected array $translatableFields = [...]` and
 * calls `$model->translate('title', 'kk')` to get the localized
 * string. Translations live in the `translations` morph table.
 */
trait Translatable
{
    /** Per-instance cache to prevent N+1 across repeated translate() calls. */
    protected ?array $translationsCache = null;

    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $all = $this->getCachedTranslations();

        $value = $all["{$locale}.{$field}"] ?? null;
        if (!empty($value)) return $value;

        // Locale fallback chain: requested → kk → ru → raw.
        if ($locale !== 'kk' && !empty($all["kk.{$field}"] ?? null)) return $all["kk.{$field}"];
        if ($locale !== 'ru' && !empty($all["ru.{$field}"] ?? null)) return $all["ru.{$field}"];

        // Final fallback: decode the raw column (some legacy rows store
        // a JSON `{ru:..,kk:..,en:..}` blob directly in the field).
        if (isset($this->$field)) {
            $raw = $this->$field;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['kk']) || isset($decoded['ru']) || isset($decoded['en']))) {
                    return $decoded[$locale] ?? $decoded['kk'] ?? $decoded['ru'] ?? $decoded['en'] ?? $raw;
                }
            }
            return $raw;
        }

        return null;
    }

    protected function getCachedTranslations(): array
    {
        if ($this->translationsCache === null) {
            // Use the eager-loaded relation when available to kill N+1
            // (one SELECT per instance) on pages that render collections
            // of translatable models.
            $source = $this->relationLoaded('translations')
                ? $this->getRelation('translations')
                : $this->translations()->get();

            $this->translationsCache = $source
                ->mapWithKeys(fn (Translation $t) => ["{$t->locale}.{$t->field}" => $t->value])
                ->toArray();
        }
        return $this->translationsCache;
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    public function saveTranslations(string $locale, array $data): void
    {
        $allowed = property_exists($this, 'translatableFields') ? $this->translatableFields : [];

        foreach ($data as $field => $value) {
            if ($allowed && !in_array($field, $allowed, true)) continue;

            Translation::updateOrCreate([
                'translatable_type' => get_class($this),
                'translatable_id'   => $this->id,
                'locale'            => $locale,
                'field'             => $field,
            ], [
                'value' => $value,
            ]);
        }

        $this->translationsCache = null;
    }

    public function getTranslations(string $locale): array
    {
        return collect($this->getCachedTranslations())
            ->filter(fn ($v, $k) => str_starts_with($k, "{$locale}."))
            ->mapWithKeys(fn ($v, $k) => [str_replace("{$locale}.", '', $k) => $v])
            ->toArray();
    }
}
