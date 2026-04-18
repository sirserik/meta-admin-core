<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * One-click SQLite + storage backup. Writes zips into
 * `storage/backups/`. Intended for single-node Plesk installs;
 * Postgres / multi-node setups should use a proper backup tool.
 */
class BackupController extends Controller
{
    private string $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('backups');
        if (!File::isDirectory($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    public function index(): Response
    {
        $backups = collect(File::files($this->backupPath))
            ->filter(fn ($f) => $f->getExtension() === 'zip')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->map(fn ($f) => [
                'name'  => $f->getFilename(),
                'size'  => $this->humanSize($f->getSize()),
                'bytes' => $f->getSize(),
                'date'  => date('d.m.Y H:i', $f->getMTime()),
            ])->values();

        $dbPath = database_path('database.sqlite');

        return Inertia::render('Backup/Index', [
            'title'   => 'Бэкапы',
            'backups' => $backups,
            'dbPath'  => $dbPath,
            'dbSize'  => file_exists($dbPath) ? $this->humanSize(filesize($dbPath)) : '—',
        ]);
    }

    public function create(): RedirectResponse
    {
        $filename = 'backup_' . date('Y-m-d_His') . '.zip';
        $zipPath  = $this->backupPath . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return back()->with('error', 'Не удалось создать архив');
        }

        $dbPath = database_path('database.sqlite');
        if (file_exists($dbPath)) {
            $zip->addFile($dbPath, 'database.sqlite');
        }

        $storage = storage_path('app/public');
        if (is_dir($storage)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storage));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $relative = 'storage/' . substr($file->getPathname(), strlen($storage) + 1);
                    $zip->addFile($file->getPathname(), $relative);
                }
            }
        }

        $zip->close();
        return back()->with('success', 'Бэкап «' . $filename . '» создан');
    }

    public function download(Request $request): BinaryFileResponse|RedirectResponse
    {
        $name = basename($request->get('name', ''));
        $path = $this->backupPath . '/' . $name;
        if (!file_exists($path) || !str_ends_with($name, '.zip')) {
            return back()->with('error', 'Файл не найден');
        }
        return response()->download($path);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $name = basename($request->input('name', ''));
        $path = $this->backupPath . '/' . $name;
        if (file_exists($path) && str_ends_with($name, '.zip')) {
            unlink($path);
            return back()->with('success', 'Бэкап удалён');
        }
        return back()->with('error', 'Файл не найден');
    }

    private function humanSize(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
