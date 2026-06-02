<?php

namespace Meta\AdminCore\Console\Commands;

use Illuminate\Support\Facades\Storage;
use Meta\AdminCore\Services\EditorHygiene;

class ContentExtractBase64Command extends ContentHygieneCommand
{
    protected $signature = 'admin-core:content-extract-base64
                            {--dry-run : Показать изменения без записи}
                            {--target=all : all|<table> из admin-core.editor_hygiene.targets}';

    protected $description = 'Вытаскивает base64-картинки из текстовых полей в файлы (public disk) и заменяет на URL';

    protected function needles(): array
    {
        return ["LIKE '%data:image%'"];
    }

    protected function apply(string $value): string
    {
        $dir = trim((string) config('admin-core.editor_hygiene.extract_dir', 'uploads/extracted'), '/');
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');

        return EditorHygiene::extractBase64($value, function (string $filename, string $bytes) use ($dir, $dry, $disk) {
            $path = $dir . '/' . $filename;
            if (! $dry && ! $disk->exists($path)) {
                $disk->put($path, $bytes);
            }

            return '/storage/' . $path;
        });
    }
}
