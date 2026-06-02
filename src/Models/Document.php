<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A downloadable file attached to any model (polymorphic). Replaces the
 * per-parent ArticleDocument / NewsDocument copies — attach with the
 * `HasDocuments` trait. Files live on the `public` disk; the row is deleted
 * → the file is removed.
 */
class Document extends Model
{
    protected $fillable = [
        'documentable_type', 'documentable_id',
        'title', 'description', 'file_path', 'file_name',
        'file_type', 'file_size', 'mime_type', 'locale', 'sort_order', 'downloads',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'sort_order' => 'integer',
        'downloads'  => 'integer',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function incrementDownloads(): void
    {
        $this->increment('downloads');
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $b = (float) $this->file_size;
        foreach (['B', 'KB', 'MB', 'GB'] as $i => $u) {
            if ($b < 1024 || $u === 'GB') {
                return ($i ? number_format($b, 2) : (int) $b) . ' ' . $u;
            }
            $b /= 1024;
        }

        return $this->file_size . ' B';
    }

    public function getFileIconAttribute(): string
    {
        return [
            'pdf' => 'fa-file-pdf', 'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
            'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
            'txt' => 'fa-file-lines', 'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', '7z' => 'fa-file-zipper',
            'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image', 'gif' => 'fa-file-image',
        ][$this->file_type] ?? 'fa-file';
    }

    protected static function booted(): void
    {
        static::deleting(function (Document $doc) {
            if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
                Storage::disk('public')->delete($doc->file_path);
            }
        });
    }

    /** File extensions accepted on upload. */
    public static function getSupportedFileTypes(): array
    {
        return [
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
            'xls', 'xlsx', 'ods', 'csv',
            'ppt', 'pptx', 'odp',
            'zip', 'rar', '7z', 'tar', 'gz',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
            'xml', 'json',
        ];
    }

    /** Comma-joined MIME list for the `mimetypes:` validation rule. */
    public static function getMimeTypesRule(): string
    {
        return implode(',', [
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text', 'application/rtf', 'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.oasis.opendocument.spreadsheet', 'text/csv',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.presentation',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip',
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/xml', 'text/xml', 'application/json',
        ]);
    }
}
