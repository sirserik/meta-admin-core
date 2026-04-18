<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Admin media library record. Stored in `media_legacy` so it doesn't
 * collide with Spatie Media Library's `media` table — some consumer
 * apps run both.
 */
class Media extends Model
{
    protected $table = 'media_legacy';

    protected $fillable = [
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'title',
        'folder',
        'uploaded_by',
    ];

    protected $appends = ['url', 'human_size', 'is_image'];

    public function getUrlAttribute(): string
    {
        return $this->path ? media_url($this->path) : '';
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeInFolder($query, string $folder)
    {
        return $query->where('folder', $folder);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (!$search) return $query;
        return $query->where(function ($q) use ($search) {
            $q->where('filename', 'like', "%{$search}%")
              ->orWhere('alt',     'like', "%{$search}%")
              ->orWhere('title',   'like', "%{$search}%");
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (Media $m) {
            if ($m->path) {
                Storage::disk($m->disk ?: 'public')->delete($m->path);
            }
        });
    }
}
