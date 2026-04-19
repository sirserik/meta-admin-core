<?php

namespace Meta\AdminCore\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Meta\AdminCore\Models\TaxonomyTerm;

/**
 * Polymorphic many-to-many with `taxonomy_terms`. Lets a model own any
 * number of tags, categories, audiences, … without per-vocabulary
 * tables.
 *
 *   class Article extends Model {
 *       use \Meta\AdminCore\Concerns\Taxable;
 *   }
 *
 *   $article->terms;                       // all terms
 *   $article->terms()->whereType('tag');   // scoped list
 *   $article->syncTerms('tag', ['interview', 'opinion']);
 *   Article::withTerm('category', 'admissions')->get();
 */
trait Taxable
{
    public function terms(): MorphToMany
    {
        return $this->morphToMany(
            TaxonomyTerm::class,
            'taxable',
            'taxonomy_term_model',
            'taxable_id',
            'term_id',
        )->withTimestamps();
    }

    /**
     * Convenience accessor for a single vocabulary.
     * $article->termsOfType('tag') === collection of TaxonomyTerm.
     */
    public function termsOfType(string $type)
    {
        return $this->terms()->where('type', $type)->get();
    }

    /**
     * Replace the model's terms for one vocabulary with the given slugs.
     * Creates missing terms on the fly so consumers can add tags from
     * the form without visiting the admin /taxonomies screen first.
     */
    public function syncTerms(string $type, array $slugs): void
    {
        $ids = [];
        foreach (array_unique($slugs) as $slug) {
            $slug = \Illuminate\Support\Str::slug((string) $slug);
            if ($slug === '') continue;

            $term = TaxonomyTerm::firstOrCreate(
                ['type' => $type, 'slug' => $slug],
                ['label' => ucfirst(str_replace('-', ' ', $slug))],
            );
            $ids[] = $term->id;
        }

        // Detach terms of this type first so we don't wipe *other*
        // vocabularies attached to the same model.
        $existingOfType = $this->terms()->where('type', $type)->pluck('term_id')->all();
        $toDetach = array_diff($existingOfType, $ids);
        if ($toDetach) $this->terms()->detach($toDetach);

        $toAttach = array_diff($ids, $existingOfType);
        if ($toAttach) $this->terms()->attach($toAttach);
    }

    public function scopeWithTerm(Builder $q, string $type, string $slug): Builder
    {
        return $q->whereHas('terms', fn ($t) => $t->where('type', $type)->where('slug', $slug));
    }

    public function scopeWithAnyTerm(Builder $q, string $type, array $slugs): Builder
    {
        return $q->whereHas('terms', fn ($t) => $t->where('type', $type)->whereIn('slug', $slugs));
    }
}
