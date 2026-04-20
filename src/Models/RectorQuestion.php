<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Q&A workflow: publicly-submitted questions addressed to the rector.
 * Editors answer, set a status, optionally publish the answer to the
 * public site. Shared across all university consumers — every site
 * with `Вопросы ректору` page uses the same schema, controller and UI.
 */
class RectorQuestion extends Model
{
    const STATUS_NEW       = 'new';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_ANSWERED  = 'answered';
    const STATUS_REJECTED  = 'rejected';

    const CATEGORIES = [
        'education'     => 'Образовательные программы',
        'research'      => 'Научная деятельность',
        'international' => 'Международное сотрудничество',
        'youth'         => 'Молодежная политика',
        'general'       => 'Общие вопросы',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'category',
        'subject',
        'question',
        'answer',
        'status',
        'is_published',
        'answered_at',
        'ip_address',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'answered_at'  => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where('status', self::STATUS_ANSWERED)
            ->whereNotNull('answer');
    }

    public function scopeNewQuestions($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category ?? 'Другое';
    }
}
