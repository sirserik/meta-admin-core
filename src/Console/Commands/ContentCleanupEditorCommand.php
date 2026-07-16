<?php

namespace Meta\AdminCore\Console\Commands;

use Meta\AdminCore\Services\EditorHygiene;

class ContentCleanupEditorCommand extends ContentHygieneCommand
{
    protected $signature = 'admin-core:content-cleanup-editor
                            {--dry-run : Показать изменения без записи}
                            {--target=all : all|<table> из admin-core.editor_hygiene.targets}';

    protected $description = 'Чистит артефакты визуального редактора (TipTap): пустые <p></p> и <li><p>…</p></li>-обёртки';

    protected function needles(): array
    {
        return [
            "LIKE '%<p></p>%'",
            "LIKE '%<p>&nbsp;</p>%'",
            "LIKE '%<p><br></p>%'",
            "LIKE '%<li><p%'",
        ];
    }

    protected function apply(string $value): string
    {
        return EditorHygiene::cleanEditorArtifacts($value);
    }
}
