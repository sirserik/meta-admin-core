<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;

/**
 * Recursively set 0755 on directories and 0644 on files under
 * storage/app/public — fixes 403s on uploaded media after a deploy/restore.
 * Run as the file owner (or via sudo) for it to take effect.
 */
class StorageFixPermissionsCommand extends Command
{
    protected $signature = 'admin-core:storage-fix-permissions';
    protected $description = 'Рекурсивно выставить 0755 на папки и 0644 на файлы в storage/app/public (лечит 403 на медиа)';

    public function handle(): int
    {
        $root = storage_path('app/public');
        if (! is_dir($root)) {
            $this->error('storage/app/public does not exist');

            return self::FAILURE;
        }

        $dirs = 0;
        $files = 0;
        $errors = 0;

        @chmod($root, 0755) ? $dirs++ : $errors++;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $mode = $item->isDir() ? 0755 : 0644;
            if (@chmod($item->getPathname(), $mode)) {
                $item->isDir() ? $dirs++ : $files++;
            } else {
                $errors++;
            }
        }

        $this->info("Fixed: {$dirs} dirs (0755), {$files} files (0644)" . ($errors ? ", {$errors} errors (run as owner / sudo)" : ''));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }
}
