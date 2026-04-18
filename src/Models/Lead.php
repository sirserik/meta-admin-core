<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Meta\AdminCore\Events\LeadCreated;

/**
 * Incoming admission / consultation request. Model lives in the
 * package so every consumer's `/admin/leads` has the same schema.
 *
 * Side-effects (admin notifications, CRM push, Telegram ping) are
 * emitted via the `LeadCreated` event — consumer apps register
 * listeners for whatever integrations they need.
 */
class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'type',
        'name', 'first_name', 'last_name',
        'phone', 'email',
        'program', 'year', 'message',
        'interests', 'call_time', 'questions',
        'source', 'status',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'interests' => 'array',
    ];

    public const STATUS_NEW        = 'new';
    public const STATUS_CONTACTED  = 'contacted';
    public const STATUS_QUALIFIED  = 'qualified';
    public const STATUS_CONVERTED  = 'converted';
    public const STATUS_REJECTED   = 'rejected';

    public const TYPE_APPLICATION  = 'application';
    public const TYPE_CONSULTATION = 'consultation';

    protected static function booted(): void
    {
        static::created(fn (Lead $lead) => event(new LeadCreated($lead)));
    }

    public function scopeNew($query)            { return $query->where('status', self::STATUS_NEW); }
    public function scopeOfType($query, $type)  { return $query->where('type', $type); }

    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return trim($this->first_name . ' ' . $this->last_name);
        }
        return (string) $this->name;
    }

    public function getProgramNameAttribute(): string
    {
        $programs = config('admin-core.lead_programs', [
            'it'          => 'IT и Технологии',
            'engineering' => 'Инженерия',
            'business'    => 'Бизнес и Менеджмент',
            'design'      => 'Дизайн',
            'law'         => 'Юриспруденция',
            'other'       => 'Другое',
        ]);
        return $programs[$this->program] ?? (string) $this->program;
    }

    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW       => 'Новая',
            self::STATUS_CONTACTED => 'Связались',
            self::STATUS_QUALIFIED => 'Квалифицирована',
            self::STATUS_CONVERTED => 'Преобразована',
            self::STATUS_REJECTED  => 'Отклонена',
            default                => (string) $this->status,
        };
    }
}
