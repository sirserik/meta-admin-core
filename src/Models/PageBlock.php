<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Meta\AdminCore\Concerns\Publishable;
use Meta\AdminCore\Concerns\Translatable;

/**
 * A single piece of page content: hero, stats block, photo gallery,
 * menu-config row, whatever the consumer site needs. Rows are keyed
 * by (page_name, block_key), typed by block_type (for rendering),
 * with JSON `data` + `settings` for flexibility.
 *
 * Side-effects on save/delete: flush the block's page cache and
 * compiled blade views — Plesk has bitten us twice by serving stale
 * compiled templates after block edits.
 */
class PageBlock extends Model
{
    use Publishable, Translatable;

    protected $table = 'page_blocks';

    protected $fillable = [
        'page_name', 'block_key', 'block_type',
        'title', 'subtitle', 'content',
        'data', 'settings',
        'is_active', 'status', 'published_at',
        'publish_at', 'unpublish_at',
        'sort_order',
    ];

    protected $casts = [
        'data'         => 'array',
        'settings'     => 'array',
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $translatableFields = ['title', 'subtitle', 'content'];

    protected static function booted(): void
    {
        $bust = function (PageBlock $block) {
            Cache::forget('page_blocks_' . $block->page_name);
            Cache::flush();

            $viewsPath = storage_path('framework/views');
            if (is_dir($viewsPath)) {
                foreach ((array) glob("{$viewsPath}/*.php") as $file) {
                    @unlink($file);
                }
            }
        };
        static::saved($bust);
        static::deleted($bust);
    }

    /* === SCOPES === */

    public function scopeActive($query)    { return $query->where('is_active', true); }
    // scopePublished / scopeScheduled / scopeDuePublish / scopeDueUnpublish
    // come from the Publishable trait and honour publish_at/unpublish_at.
    public function scopeDraft($query)     { return $query->where('status', 'draft'); }
    public function scopeForPage($query, string $pageName)
    {
        return $query->where('page_name', $pageName);
    }

    public function isDraft(): bool     { return $this->status === 'draft'; }
    // isPublished() comes from the Publishable trait.

    public function publish(): void
    {
        $this->update([
            'status'       => 'published',
            'is_active'    => true,
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update(['status' => 'draft', 'is_active' => false]);
    }

    /* === QUERY HELPERS === */

    public static function getPageBlocks(string $pageName)
    {
        return Cache::remember("page_blocks_{$pageName}", 600, function () use ($pageName) {
            return static::forPage($pageName)
                ->published()
                ->orderBy('sort_order')
                ->get()
                ->keyBy('block_key');
        });
    }

    public static function getPageBlocksForPreview(string $pageName)
    {
        return static::forPage($pageName)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('block_key');
    }

    public static function getBlock(string $pageName, string $blockKey): ?self
    {
        return static::forPage($pageName)->where('block_key', $blockKey)->first();
    }

    public static function updateOrCreateBlock(string $pageName, string $blockKey, array $data): self
    {
        return static::updateOrCreate(
            ['page_name' => $pageName, 'block_key' => $blockKey],
            $data,
        );
    }

    /* === ACCESSORS === */

    public function getLocalizedField(string $field, string $locale = 'ru'): string
    {
        $value = $this->$field ?? null;
        if ($value === null || $value === '') return '';

        if (is_array($value)) {
            return (string) ($value[$locale] ?? $value['ru'] ?? '');
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && (isset($decoded['ru']) || isset($decoded['kk']) || isset($decoded['en']))) {
                return (string) ($decoded[$locale] ?? $decoded['ru'] ?? '');
            }
        }

        return (string) $value;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->data['image'] ?? null);
    }

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return $this->resolveImageUrl($this->data['background_image'] ?? null);
    }

    protected function resolveImageUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return media_url($path);
    }
}
