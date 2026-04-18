<?php

namespace Meta\AdminCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin audit log row: who did what, when. The package ships this
 * model so any consumer site can render `/admin/activity` without
 * declaring its own.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'description', 'changes', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', \App\Models\User::class);
        return $this->belongsTo($userModel);
    }

    public function getModelNameAttribute(): string
    {
        return $this->model_type ? class_basename($this->model_type) : '';
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created'     => 'Создал',
            'updated'     => 'Обновил',
            'deleted'     => 'Удалил',
            'published'   => 'Опубликовал',
            'unpublished' => 'Снял с публикации',
            'login'       => 'Вошёл в систему',
            'logout'      => 'Вышел из системы',
            default       => $this->action,
        };
    }
}
