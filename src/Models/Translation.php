<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic translation row. One record per (model, locale, field)
 * triple. The package's Translatable concern reads/writes these.
 */
class Translation extends Model
{
    protected $table = 'translations';

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field',
        'value',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    public function scopeField($query, string $field)
    {
        return $query->where('field', $field);
    }
}
