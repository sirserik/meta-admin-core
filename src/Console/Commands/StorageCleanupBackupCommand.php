<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Remove `public/storage_old_backup` left behind by `storage-relink`, once
 * the site has been verified working.
 */
class StorageCleanupBackupCommand extends Command
{
    protected $signature = 'admin-core:storage-cleanup-backup {--force : Skip confirmation}';
    protected $description = 'Удалить public/storage_old_backup (после проверки, что сайт работает)';

    public function handle(): int
    {
        $backup = public_path('storage_old_backup');
        if (! File::exists($backup)) {
            $this->info('No backup directory — nothing to remove.');

            return self::SUCCESS;
        }

        $bytes = 0;
        foreach (File::allFiles($backup) as $f) {
            $bytes += $f->getSize();
        }
        $mb = round($bytes / 1048576, 1);

        if (! $this->option('force') && ! $this->confirm("Delete public/storage_old_backup ({$mb} MB)? This is irreversible.", false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        File::deleteDirectory($backup);
        $this->info("Removed public/storage_old_backup ({$mb} MB freed).");

        return self::SUCCESS;
    }
}
