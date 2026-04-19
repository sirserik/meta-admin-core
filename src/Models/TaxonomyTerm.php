<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * A single term inside a vocabulary (e.g. a tag named "interview"
 * inside the `tag` type, or a category named "admissions").
 *
 * `type` segments the term space — queries scope to a type before
 * anything else, so adding a new vocabulary is a matter of picking
 * a unique string rather than creating a new table.
 */
class TaxonomyTerm extends Model
{
    protected $table = 'taxonomy_terms';

    protected $fillable = [
        'type',
        'slug',
        'label',
        'label_translations',
        'sort_order',
    ];

    protected $casts = [
        'label_translations' => 'array',
        'sort_order'         => 'integer',
    ];

    public function scopeOfType($q, string $type)
    {
        return $q->where('type', $type);
    }

    /**
     * Generic morph pointing at every model that uses the Taxable trait.
     * Eloquent's relation needs a concrete type to work — consumers who
     * want a typed relation (tags() on Article) do so via Taxable::tags().
     */
    public function taxables(string $relatedClass): MorphToMany
    {
        return $this->morphedByMany(
            $relatedClass,
            'taxable',
            'taxonomy_term_model',
            'term_id',
            'taxable_id',
        );
    }

    /** Locale-aware label. Falls back to the primary column. */
    public function localizedLabel(string $locale): string
    {
        $t = (array) $this->label_translations;
        return $t[$locale] ?? $this->label;
    }
}
