<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Meta\AdminCore\Facades\AdminCore;

/**
 * Scheduled-publishing ticker.
 *
 * Iterates every model registered via `AdminCore::schedulable()` and
 * flips `status` based on the current time:
 *
 *   draft     + publish_at   ≤ now          → published
 *   published + unpublish_at ≤ now          → draft
 *
 * Designed to be idempotent — run as often as you need the precision.
 * Once a minute is the sweet spot for most sites. Wire it into Laravel
 * scheduler:
 *
 *   // bootstrap/app.php  (Laravel 12)
 *   ->withSchedule(function (Schedule $s) {
 *       $s->command('admin-core:apply-schedule')->everyMinute();
 *   })
 *
 * Or a system cron on Plesk:
 *
 *   * * * * * cd /var/www/… && php artisan admin-core:apply-schedule >/dev/null 2>&1
 */
class ApplyScheduleCommand extends Command
{
    protected $signature   = 'admin-core:apply-schedule
                              {--dry-run : Show what would change without writing}';
    protected $description = 'Flip published/draft state on scheduled content (Publishable trait).';

    public function handle(): int
    {
        $models = AdminCore::getSchedulableModels();
        if (empty($models)) {
            $this->components->info('No schedulable models registered.');
            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $totalPublished   = 0;
        $totalUnpublished = 0;

        foreach ($models as $class) {
            if (!class_exists($class)) {
                $this->components->warn("Skipping {$class} — class does not exist.");
                continue;
            }

            $uses = in_array(
                \Meta\AdminCore\Concerns\Publishable::class,
                class_uses_recursive($class) ?: [],
                true,
            );
            if (!$uses) {
                $this->components->warn("Skipping {$class} — not using Publishable trait.");
                continue;
            }

            $due = $class::duePublish()->get();
            foreach ($due as $row) {
                $this->line("  → publishing [{$class}] #{$row->getKey()}");
                if (!$dry) $row->update(['status' => 'published']);
                $totalPublished++;
            }

            $down = $class::dueUnpublish()->get();
            foreach ($down as $row) {
                $this->line("  → unpublishing [{$class}] #{$row->getKey()}");
                if (!$dry) $row->update(['status' => 'draft']);
                $totalUnpublished++;
            }
        }

        $suffix = $dry ? ' (dry-run)' : '';
        $this->components->info("Published {$totalPublished}, unpublished {$totalUnpublished}{$suffix}.");

        return self::SUCCESS;
    }
}
