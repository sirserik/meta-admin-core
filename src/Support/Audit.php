<?php

namespace Meta\AdminCore\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Meta\AdminCore\Models\ActivityLog;

/**
 * Пишет в журнал действий то, что не является правкой модели: входы,
 * неудачные попытки, просмотр и скачивание персональных данных.
 * Правки моделей журналирует сам сайт — здесь их не дублируем.
 *
 * Журнал не должен ронять запрос: нет таблицы или упала запись —
 * событие уходит в обычный лог, а пользователь получает свою страницу.
 */
class Audit
{
    private static ?bool $tableExists = null;

    public static function log(string $action, string $description, ?object $model = null, ?array $changes = null, mixed $userId = null): void
    {
        if (! config('admin-core.audit.enabled', true)) {
            return;
        }

        $request = request();

        try {
            if (self::$tableExists === null) {
                self::$tableExists = Schema::hasTable('activity_logs');
            }
            if (! self::$tableExists) {
                return;
            }

            ActivityLog::create([
                'user_id'     => $userId ?? $request?->user()?->getAuthIdentifier(),
                'action'      => $action,
                // На части сайтов model_type объявлен NOT NULL (ETU) —
                // для входов и прочих событий без модели пишем пустую строку.
                'model_type'  => $model ? get_class($model) : '',
                'model_id'    => $model?->getKey(),
                'description' => mb_substr($description, 0, 250),
                'changes'     => $changes,
                'ip_address'  => $request?->ip(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[admin-core audit] ' . $action . ': ' . $description, ['error' => $e->getMessage()]);
        }
    }

    /** Для тестов: сбросить закэшированную проверку таблицы. */
    public static function flushCache(): void
    {
        self::$tableExists = null;
    }
}
