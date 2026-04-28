<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Meta\AdminCore\Concerns\HasContentBlocks;
use Meta\AdminCore\Concerns\Translatable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * Procurements / tenders directory: each row carries the procedure
 * metadata (number, type, status, dates, budget) plus a flexible
 * content stack via `HasContentBlocks` (description tables, document
 * groups, single-image scans, videos, anything the consumer site
 * ships as a block_type).
 *
 * Designed to be subclassed in consumer sites only when site-specific
 * behavior is needed; out of the box the package model + the
 * `procurements` resource registered by `ProcurementsFeature` cover
 * the standard «list + detail with flexible content» case.
 */
class Procurement extends Model
{
    use HasContentBlocks, HasFactory, HasSlug, Translatable;

    public const TYPES = [
        'open_tender'        => 'Открытый конкурс',
        'tender'             => 'Тендер',
        'single_source'      => 'Из одного источника',
        'electronic_auction' => 'Электронный аукцион',
        'price_request'      => 'Запрос ценовых предложений',
        'other'              => 'Другое',
    ];

    public const STATUSES = [
        'announced' => 'Объявлена',
        'accepting' => 'Приём заявок',
        'reviewing' => 'На рассмотрении',
        'completed' => 'Завершена',
        'cancelled' => 'Отменена',
    ];

    protected $table = 'procurements';

    protected $translatableFields = ['title', 'summary', 'customer'];

    protected $fillable = [
        'number',
        'slug',
        'procurement_type',
        'status',
        'title',
        'summary',
        'customer',
        'budget',
        'budget_currency',
        'budget_public',
        'announced_at',
        'deadline_at',
        'completed_at',
        'is_published',
        'sort_order',
        'meta_data',
    ];

    protected $casts = [
        'announced_at'  => 'datetime',
        'deadline_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'is_published'  => 'boolean',
        'budget_public' => 'boolean',
        'budget'        => 'decimal:2',
        'meta_data'     => 'array',
    ];

    protected static function booted(): void
    {
        $clear = function (Procurement $p) {
            Cache::forget('procurements_index');
            Cache::forget('procurement_show_' . $p->slug);
            $base = sprintf(
                'blockable_%s_%s',
                str_replace('\\', '_', $p->getMorphClass()),
                $p->id
            );
            Cache::forget($base . '_pub');
            Cache::forget($base . '_all');
        };
        static::saved($clear);
        static::deleted($clear);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (Procurement $p) => $p->number ?: $p->translate('title', 'ru'))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* === SCOPES === */

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeByType($query, ?string $type)
    {
        return $type ? $query->where('procurement_type', $type) : $query;
    }

    public function scopeByYear($query, ?int $year)
    {
        return $year ? $query->whereYear('announced_at', $year) : $query;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (!$term) return $query;
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%';
        return $query->where(function ($q) use ($like) {
            $q->where('number', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('customer', 'like', $like);
        });
    }

    /* === DISPLAY HELPERS === */

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->procurement_type] ?? $this->procurement_type;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'announced' => 'sky',
            'accepting' => 'emerald',
            'reviewing' => 'amber',
            'completed' => 'slate',
            'cancelled' => 'rose',
            default     => 'slate',
        };
    }

    public function isDeadlineSoon(): bool
    {
        if (!$this->deadline_at || in_array($this->status, ['completed', 'cancelled'], true)) {
            return false;
        }
        $diff = now()->diffInDays($this->deadline_at, false);
        return $diff >= 0 && $diff <= 7;
    }
}
