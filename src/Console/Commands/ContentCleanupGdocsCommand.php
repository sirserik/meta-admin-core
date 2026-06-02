<?php

namespace Meta\AdminCore\Console\Commands;

use Meta\AdminCore\Services\EditorHygiene;

class ContentCleanupGdocsCommand extends ContentHygieneCommand
{
    protected $signature = 'admin-core:content-cleanup-gdocs
                            {--dry-run : Показать изменения без записи}
                            {--target=all : all|<table> из admin-core.editor_hygiene.targets}';

    protected $description = 'Чистит Google Docs / Word мусор из HTML-полей контента (вложенные span, мусорные inline-стили, dir/lang, пустые <p>)';

    protected function needles(): array
    {
        return [
            "LIKE '%docs-internal-guid%'",
            "LIKE '%font-variant-numeric%'",
            "LIKE '%Times New Roman%'",
            "LIKE '%<span style=\"%'",
        ];
    }

    protected function apply(string $value): string
    {
        return EditorHygiene::cleanGoogleDocs($value);
    }
}
