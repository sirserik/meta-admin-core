<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Convert a real `public/storage` directory (a frequent deploy mistake —
 * files copied instead of symlinked) into the proper symlink: moves it to
 * `public/storage_old_backup`, merges its contents into storage/app/public,
 * then runs `storage:link`. Idempotent: no-op if already a symlink.
 */
class StorageRelinkCommand extends Command
{
    protected $signature = 'admin-core:storage-relink';
    protected $description = 'Превратить реальную папку public/storage в правильный симлинк (с переносом файлов в storage/app/public)';

    public function handle(): int
    {
        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_link($link)) {
            $this->info('public/storage is already a symlink → nothing to do.');

            return self::SUCCESS;
        }

        if (! is_dir($link)) {
            Artisan::call('storage:link');
            $this->info('public/storage created as symlink.');

            return self::SUCCESS;
        }

        $backup = public_path('storage_old_backup');
        if (File::exists($backup)) {
            $this->error('public/storage_old_backup already exists — remove/rename it first.');

            return self::FAILURE;
        }

        File::move($link, $backup);
        File::ensureDirectoryExists($target);

        // merge backup contents into storage/app/public
        foreach (File::directories($backup) as $dir) {
            $name = basename($dir);
            File::ensureDirectoryExists($target . '/' . $name);
            foreach (File::allFiles($dir) as $f) {
                $dest = $target . '/' . $name . str_replace($dir, '', $f->getPathname());
                File::ensureDirectoryExists(dirname($dest));
                File::copy($f->getPathname(), $dest);
            }
        }
        foreach (File::files($backup) as $f) {
            File::copy($f->getPathname(), $target . '/' . $f->getFilename());
        }

        Artisan::call('storage:link');

        if (! is_link($link)) {
            $this->error('storage:link did not create the symlink — check permissions.');

            return self::FAILURE;
        }

        $this->info('Done. public/storage is now a symlink; old dir kept at public/storage_old_backup.');
        $this->line('Verify the site, then: php artisan admin-core:storage-cleanup-backup');

        return self::SUCCESS;
    }
}
