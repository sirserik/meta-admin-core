<?php

namespace Meta\AdminCore\Support;

/**
 * Разбор построчных списков вида «значение | значение | значение».
 *
 * Пока в формах ресурсов не было повторяющихся групп, списки (команда,
 * авторы, ссылки, материалы) хранили построчным текстом в textarea. Трейт
 * превращает такую колонку в массив строк с именованными полями.
 *
 * Для новых ресурсов лучше сразу брать `type => 'array'` с `item_fields`
 * (RepeaterField) и json-колонку. Трейт остаётся для уже накопленных данных:
 * он позволяет отдавать наружу нормальные массивы, не трогая схему.
 */
trait ParsesLineLists
{
    /**
     * @param  string|null  $raw   содержимое колонки
     * @param  string[]     $keys  имена колонок по порядку
     * @return array<int, array<string, string|null>>
     */
    protected function parseLines(?string $raw, array $keys): array
    {
        if (blank($raw)) {
            return [];
        }

        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $row = [];

            foreach ($keys as $i => $key) {
                $row[$key] = ($parts[$i] ?? '') !== '' ? $parts[$i] : null;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
