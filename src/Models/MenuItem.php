<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Meta\AdminCore\Concerns\Translatable;

/**
 * One row in the site's navigation tree. `content_type` is a freeform
 * string — consumer sites decide the vocabulary ('schools', 'programs',
 * 'link', ...) and resolve it at render time. `title` + `url` live in
 * the morph translations table (three locales per row).
 */
class MenuItem extends Model
{
    /**
     * Stable morph alias — see PageBlock for rationale (v1.3).
     */
    public function getMorphClass(): string
    {
        $map = \Illuminate\Database\Eloquent\Relations\Relation::morphMap();
        $found = array_search(static::class, $map, true);
        return $found !== false ? $found : 'menu_item';
    }

    use Translatable;

    protected $table = 'menu_items';

    protected $fillable = [
        'parent_id',
        'content_type',
        'content_id',
        'slug',
        'icon',
        'is_published',
        'menu_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'menu_order'   => 'integer',
    ];

    protected $translatableFields = ['title', 'url'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('menu_order');
    }

    public function scopePublished($query) { return $query->where('is_published', true); }
    public function scopeRoots($query)     { return $query->whereNull('parent_id'); }
}
