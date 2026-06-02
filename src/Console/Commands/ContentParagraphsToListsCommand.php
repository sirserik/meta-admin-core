<?php

namespace Meta\AdminCore\Console\Commands;

use Meta\AdminCore\Services\EditorHygiene;

class ContentParagraphsToListsCommand extends ContentHygieneCommand
{
    protected $signature = 'admin-core:content-paragraphs-to-lists
                            {--dry-run : Показать изменения без записи}
                            {--min=2 : Минимум подряд идущих пунктов, чтобы считать списком}
                            {--target=all : all|<table> из admin-core.editor_hygiene.targets}';

    protected $description = 'Превращает подряд идущие <p>…;</p> в <ul><li>…</li></ul>';

    protected function needles(): array
    {
        return ["LIKE '%<p>%;</p>%'"];
    }

    protected function apply(string $value): string
    {
        return EditorHygiene::paragraphsToLists($value, (int) $this->option('min'));
    }
}
