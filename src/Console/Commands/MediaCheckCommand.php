<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Meta\AdminCore\Services\MediaIntegrity;

/**
 * Scan a filesystem disk for media files that are silently corrupt — image/PDF
 * files whose content is actually an HTML page (crawl/migration artifact).
 * They serve 200 but won't render, so HTTP checks miss them; this checks by
 * content. Optionally repairs each corrupt file from a valid mirror directory.
 *
 *   php artisan admin-core:media-check                       # scan public disk
 *   php artisan admin-core:media-check --dir=news            # limit to a subdir
 *   php artisan admin-core:media-check --from=/path/public/media --fix
 */
class MediaCheckCommand extends Command
{
    protected $signature = 'admin-core:media-check
                            {--disk=public : Filesystem disk to scan}
                            {--dir= : Limit scan to this subdirectory (relative to the disk root)}
                            {--from= : Absolute path to a valid mirror dir to repair from (paths mirror the disk root)}
                            {--fix : Replace corrupt files from --from (otherwise report only)}
                            {--limit=0 : Cap listed files (0 = all)}';

    protected $description = 'Найти медиа-файлы, которые на самом деле HTML (битые картинки/PDF, отдаются 200 но не рендерятся); опц. починить из зеркала';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $root = rtrim(Storage::disk($disk)->path(''), '/');
        $scan = $this->option('dir') ? $root . '/' . trim((string) $this->option('dir'), '/') : $root;

        if (! is_dir($scan)) {
            $this->error("Каталог не найден: {$scan}");

            return self::FAILURE;
        }

        $from = $this->option('from') ? rtrim((string) $this->option('from'), '/') : null;
        $fix = (bool) $this->option('fix');
        $limit = (int) $this->option('limit');

        $corrupt = 0;
        $repaired = 0;
        $noSource = 0;
        $listed = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scan, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            $path = $file->getPathname();
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (! MediaIntegrity::isBinaryImageExt($ext)) {
                continue;
            }
            $head = (string) @file_get_contents($path, false, null, 0, 64);
            if (! MediaIntegrity::isCorrupt($ext, $head)) {
                continue;
            }

            $corrupt++;
            $rel = ltrim(substr($path, strlen($root)), '/');

            if ($from) {
                $src = $from . '/' . $rel;
                $srcHead = is_file($src) ? (string) @file_get_contents($src, false, null, 0, 64) : '';
                $srcOk = is_file($src) && ! MediaIntegrity::isCorrupt($ext, $srcHead);

                if ($srcOk && $fix) {
                    if (@copy($src, $path)) {
                        $repaired++;
                        @chmod($path, 0644);
                        continue;
                    }
                }
                if (! $srcOk) {
                    $noSource++;
                }
            }

            if ($limit === 0 || $listed < $limit) {
                $this->line('  ✗ ' . $rel);
                $listed++;
            }
        }

        $this->newLine();
        $msg = "Битых медиа-файлов: {$corrupt}";
        if ($from) {
            $msg .= $fix ? " | починено из зеркала: {$repaired} | без валидного источника: {$noSource}"
                        : " | (запусти с --fix чтобы починить из --from; без источника: {$noSource})";
        }
        $this->info($msg);

        return self::SUCCESS;
    }
}
