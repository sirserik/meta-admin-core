<?php

namespace Meta\AdminCore\Support;

/**
 * Legacy block catalog — pre-v1.0 ETU/ETEC types still living in
 * production DBs that should now be editable through admin-core.
 *
 * Inherits all default catalog blocks (Hero/Stats/Gallery/etc.) and
 * adds 34 legacy types shared across sister sites (menu_item,
 * stat, content-section, buildings, achievements, …) with their
 * schemas. Each schema mirrors the production data shape so the
 * Vue admin renders editable fields end-to-end.
 *
 * Consumer sites that have their own one-off blocks (ETU's grid-cards
 * with nested links, ETEC's flat-i18n labels, …) extend this class
 * and override blockSchema() / blockTypesFlat() for those extras.
 *
 * @see Meta\AdminCore\Support\DefaultBlockCatalog parent (admin-core defaults)
 */
const LEGACY_BLOCKS_CATALOG_TYPES = [
    'menu_item'      => ['label' => 'Пункт меню (legacy)',         'description' => 'Translatable label для header navigation', 'icon' => 'fa-list', 'category' => 'Legacy'],
    'stat'           => ['label' => 'Stat',                         'description' => 'Счётчик / число с подписью',                'icon' => 'fa-chart-line', 'category' => 'Legacy'],
    'metric'         => ['label' => 'Metric',                       'description' => 'Метрика формы (отклик/консультация)',       'icon' => 'fa-chart-pie',  'category' => 'Legacy'],
    'ui_element'     => ['label' => 'UI element',                   'description' => 'Кнопка / текст / надпись',                  'icon' => 'fa-square',     'category' => 'Legacy'],
    'heading'        => ['label' => 'Heading',                      'description' => 'Заголовок секции',                          'icon' => 'fa-heading',    'category' => 'Legacy'],
    'feature_card'   => ['label' => 'Feature card',                 'description' => 'Карточка фичи с иконкой',                   'icon' => 'fa-id-card',    'category' => 'Legacy'],
    'feature_item'   => ['label' => 'Feature item',                 'description' => 'Элемент списка фич',                        'icon' => 'fa-check',      'category' => 'Legacy'],
    'news_card'      => ['label' => 'News card',                    'description' => 'Карточка новости главной',                  'icon' => 'fa-newspaper',  'category' => 'Legacy'],
    'program_card'   => ['label' => 'Program card',                 'description' => 'Карточка программы обучения',               'icon' => 'fa-graduation-cap','category' => 'Legacy'],
    'admission_step' => ['label' => 'Admission step',               'description' => 'Шаг подачи заявки',                         'icon' => 'fa-shoe-prints','category' => 'Legacy'],
    'form_option'    => ['label' => 'Form option',                  'description' => 'Пункт выпадающего списка формы',            'icon' => 'fa-list-ol',    'category' => 'Legacy'],
    'programs'       => ['label' => 'Programs (legacy)',            'description' => 'Список программ обучения',                  'icon' => 'fa-book',       'category' => 'Legacy'],

    // Green-Deal-Center секции (page=green-deal-center)
    'content-section'      => ['label' => 'Content section',          'description' => 'Карточная секция с иконкой',                'icon' => 'fa-square',            'category' => 'GDC'],
    'features-section'     => ['label' => 'Features section',         'description' => 'Приоритетные направления + регион. фичи',   'icon' => 'fa-list-check',        'category' => 'GDC'],
    'news-section'         => ['label' => 'News section (GDC)',       'description' => 'Локальные + европейские новости центра',    'icon' => 'fa-newspaper',         'category' => 'GDC'],
    'research-section'     => ['label' => 'Research section',         'description' => 'Проекты + публикации',                      'icon' => 'fa-flask',             'category' => 'GDC'],
    'publications-section' => ['label' => 'Publications section',     'description' => 'Список публикаций + статистика',            'icon' => 'fa-book-open',         'category' => 'GDC'],
    'contacts-section'     => ['label' => 'Contacts section',         'description' => 'Контакты + команда + соц. сети',            'icon' => 'fa-address-book',      'category' => 'GDC'],
    'rich-content'         => ['label' => 'Rich content',             'description' => 'Иконка + emoji + кастомный текст команды',  'icon' => 'fa-pen-fancy',         'category' => 'GDC'],
    'programs-table'       => ['label' => 'Programs table',           'description' => 'Таблица программ (код, название, описание)','icon' => 'fa-table',             'category' => 'GDC'],

    // Infrastructure-блоки (page=infrastructure)
    'buildings'            => ['label' => 'Buildings',                'description' => 'Карточки корпусов с фичами',                'icon' => 'fa-building',          'category' => 'Infra'],
    'labs'                 => ['label' => 'Labs',                     'description' => 'Учебные лаборатории',                       'icon' => 'fa-flask',             'category' => 'Infra'],
    'gallery_slider'       => ['label' => 'Gallery slider',           'description' => 'Слайдер с подписями',                       'icon' => 'fa-images',            'category' => 'Infra'],
    'it_infrastructure'    => ['label' => 'IT infrastructure',        'description' => 'IT-сервисы + featured',                     'icon' => 'fa-server',            'category' => 'Infra'],
    'sports'               => ['label' => 'Sports',                   'description' => 'Спорт-категории с вложенными items',        'icon' => 'fa-running',           'category' => 'Infra'],
    'dormitories'          => ['label' => 'Dormitories',              'description' => 'Общежития + статистика',                    'icon' => 'fa-bed',               'category' => 'Infra'],
    'virtual_tour'         => ['label' => 'Virtual tour',             'description' => 'Панорама + hotspots + фильтры',             'icon' => 'fa-vr-cardboard',      'category' => 'Infra'],

    // Прочие специфичные блоки
    'legal-document'       => ['label' => 'Legal document',           'description' => 'Privacy / Terms (только title + content)',  'icon' => 'fa-file-contract',     'category' => 'Misc'],
    'achievements'         => ['label' => 'Achievements',             'description' => 'Достижения с бейджами',                     'icon' => 'fa-trophy',            'category' => 'Misc'],
    'faculty'              => ['label' => 'Faculty',                  'description' => 'Преподавательский состав школы',            'icon' => 'fa-chalkboard-teacher','category' => 'Misc'],
    'contact'              => ['label' => 'Contact info',             'description' => 'Phone / email / address простой блок',      'icon' => 'fa-phone',             'category' => 'Misc'],
    'form'                 => ['label' => 'Form schema',              'description' => 'Категории + поля для формы (rector)',        'icon' => 'fa-square-check',      'category' => 'Misc'],
    'settings'             => ['label' => 'Settings (legacy)',        'description' => 'Контактные настройки страницы',             'icon' => 'fa-gears',             'category' => 'Misc'],
    'section-header'       => ['label' => 'Section header',           'description' => 'Заголовок секции (legacy)',                 'icon' => 'fa-heading',           'category' => 'Misc'],
];

class LegacyBlocksCatalog extends DefaultBlockCatalog
{
    public function blockTypesFlat(): array
    {
        $out = parent::blockTypesFlat();
        foreach (LEGACY_BLOCKS_CATALOG_TYPES as $key => $info) {
            $out[] = array_merge($info, ['key' => $key]);
        }
        return $out;
    }

    public function blockTypesGrouped(): array
    {
        $out = parent::blockTypesGrouped();
        foreach (LEGACY_BLOCKS_CATALOG_TYPES as $key => $info) {
            $cat = $info['category'] ?? 'Другое';
            $out[$cat] ??= [];
            $out[$cat][$key] = $info;
        }
        return $out;
    }

    public function blockSchema(string $key): ?array
    {
        if ($key === 'partners-section') {
            return $this->partnersSectionSchema();
        }
        if ($key === 'menu_item') {
            return $this->menuItemSchema();
        }
        if ($key === 'content') {
            return $this->contentSchema();
        }
        if ($homeSchema = $this->homePageSchema($key)) {
            return $homeSchema;
        }
        if ($gdc = $this->greenDealCenterSchema($key)) {
            return $gdc;
        }
        if ($infra = $this->infrastructureSchema($key)) {
            return $infra;
        }
        if ($misc = $this->miscSchema($key)) {
            return $misc;
        }

        return parent::blockSchema($key);
    }

    /**
     * Green-Deal-Center секции (page=green-deal-center).
     * 8 типов, по 1-2 блока каждый. Каждый имеет icon + один-два
     * repeater-массива контента.
     */
    protected function greenDealCenterSchema(string $key): ?array
    {
        $icon = ['key' => 'icon', 'label' => 'Иконка секции', 'type' => 'text'];
        return match ($key) {
            'content-section' => ['items' => [
                $icon,
                ['key' => 'cards', 'label' => 'Карточки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка',     'type' => 'text'],
                        ['key' => 'title',       'label' => 'Заголовок',  'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',   'type' => 'translatable_textarea'],
                    ],
                ],
            ]],
            'features-section' => ['items' => [
                $icon,
                ['key' => 'priority_directions', 'label' => 'Приоритетные направления', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon', 'label' => 'Иконка', 'type' => 'text'],
                        ['key' => 'text', 'label' => 'Текст', 'type' => 'translatable'],
                    ],
                ],
                ['key' => 'regional_features_intro', 'label' => 'Вступление к региональным фичам', 'type' => 'translatable_textarea'],
                ['key' => 'regional_features', 'label' => 'Региональные фичи (translatable items)', 'type' => 'array',
                    // legacy shape — каждый item это сам translatable {ru,kk,en}.
                    // Vue admin не умеет такое напрямую; Save сохранит JSON как есть.
                    'item_fields' => [
                        ['key' => 'ru', 'label' => 'RU', 'type' => 'text'],
                        ['key' => 'kk', 'label' => 'KK', 'type' => 'text'],
                        ['key' => 'en', 'label' => 'EN', 'type' => 'text'],
                    ],
                ],
            ]],
            'news-section' => ['items' => [
                $icon,
                ['key' => 'local_news', 'label' => 'Локальные новости', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title',       'label' => 'Заголовок', 'type' => 'translatable'],
                        ['key' => 'date',        'label' => 'Дата',      'type' => 'date'],
                        ['key' => 'description', 'label' => 'Описание',  'type' => 'translatable_textarea'],
                    ],
                ],
                ['key' => 'european_news', 'label' => 'Европейские новости', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title', 'label' => 'Заголовок', 'type' => 'translatable'],
                    ],
                ],
            ]],
            'research-section' => ['items' => [
                $icon,
                ['key' => 'projects', 'label' => 'Проекты', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка',     'type' => 'text'],
                        ['key' => 'title',       'label' => 'Название',   'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',   'type' => 'translatable_textarea'],
                        ['key' => 'grant',       'label' => 'Грант',      'type' => 'text'],
                    ],
                ],
                ['key' => 'publications', 'label' => 'Публикации (краткий список)', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title', 'label' => 'Название', 'type' => 'translatable'],
                    ],
                ],
            ]],
            'publications-section' => ['items' => [
                $icon,
                ['key' => 'recent_publications', 'label' => 'Последние публикации', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title',       'label' => 'Заголовок', 'type' => 'translatable'],
                        ['key' => 'author',      'label' => 'Автор',     'type' => 'text'],
                        ['key' => 'journal',     'label' => 'Журнал',    'type' => 'text'],
                        ['key' => 'description', 'label' => 'Описание',  'type' => 'translatable_textarea'],
                    ],
                ],
                ['key' => 'statistics', 'label' => 'Статистика', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'value', 'label' => 'Значение', 'type' => 'text'],
                        ['key' => 'label', 'label' => 'Подпись',  'type' => 'translatable'],
                    ],
                ],
            ]],
            'contacts-section' => ['items' => [
                $icon,
                ['key' => 'contact_info', 'label' => 'Контакт. инфо (raw JSON-объект)', 'type' => 'text', 'help' => 'address/phone/email — редактируется отдельно через site-settings'],
                ['key' => 'team', 'label' => 'Команда', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'initials', 'label' => 'Инициалы', 'type' => 'text'],
                        ['key' => 'name',     'label' => 'Имя',      'type' => 'translatable'],
                        ['key' => 'position', 'label' => 'Должность','type' => 'translatable'],
                        ['key' => 'email',    'label' => 'Email',    'type' => 'text'],
                    ],
                ],
                ['key' => 'social_media', 'label' => 'Соц. сети', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon', 'label' => 'Иконка', 'type' => 'text'],
                        ['key' => 'url',  'label' => 'URL',    'type' => 'url'],
                    ],
                ],
            ]],
            'rich-content' => ['items' => [
                $icon,
                ['key' => 'team_name',  'label' => 'Имя команды', 'type' => 'translatable'],
                ['key' => 'team_emoji', 'label' => 'Emoji',       'type' => 'text'],
            ]],
            'programs-table' => ['items' => [
                ['key' => 'programs', 'label' => 'Программы', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'code',        'label' => 'Код',       'type' => 'text'],
                        ['key' => 'name',        'label' => 'Название',  'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',  'type' => 'translatable_textarea'],
                    ],
                ],
            ]],
            default => null,
        };
    }

    /**
     * Infrastructure (page=infrastructure) — 7 блоков, у каждого свой
     * top-level массив. Все живут в одной странице, по 1 блоку.
     */
    protected function infrastructureSchema(string $key): ?array
    {
        $titleDescIconColor = [
            ['key' => 'title',       'label' => 'Заголовок', 'type' => 'translatable'],
            ['key' => 'description', 'label' => 'Описание',  'type' => 'translatable_textarea'],
            ['key' => 'icon',        'label' => 'Иконка',    'type' => 'text'],
            ['key' => 'color',       'label' => 'Цвет',      'type' => 'text'],
        ];
        return match ($key) {
            'buildings' => ['items' => [
                ['key' => 'buildings', 'label' => 'Корпуса', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title',    'label' => 'Название',          'type' => 'translatable'],
                        ['key' => 'icon',     'label' => 'Иконка',            'type' => 'text'],
                        ['key' => 'features', 'label' => 'Фичи (JSON-list)',   'type' => 'text'],
                        ['key' => 'info',     'label' => 'Доп. инфо',         'type' => 'translatable_textarea'],
                    ],
                ],
            ]],
            'labs' => ['items' => [
                ['key' => 'labs', 'label' => 'Лаборатории', 'type' => 'array', 'item_fields' => $titleDescIconColor],
            ]],
            'gallery_slider' => ['items' => [
                ['key' => 'slides', 'label' => 'Слайды', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'image',       'label' => 'Изображение', 'type' => 'image'],
                        ['key' => 'title',       'label' => 'Заголовок',   'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',    'type' => 'translatable_textarea'],
                    ],
                ],
            ]],
            'it_infrastructure' => ['items' => [
                ['key' => 'items',    'label' => 'IT-сервисы',     'type' => 'array', 'item_fields' => $titleDescIconColor],
                ['key' => 'featured', 'label' => 'Featured (JSON)', 'type' => 'text'],
            ]],
            'sports' => ['items' => [
                ['key' => 'categories', 'label' => 'Категории спорта', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title', 'label' => 'Название',         'type' => 'translatable'],
                        ['key' => 'icon',  'label' => 'Иконка',           'type' => 'text'],
                        ['key' => 'color', 'label' => 'Цвет',             'type' => 'text'],
                        ['key' => 'items', 'label' => 'Items (JSON-list)','type' => 'text', 'help' => 'Vue admin не редактирует nested-array — JSON напрямую'],
                    ],
                ],
            ]],
            'dormitories' => ['items' => [
                ['key' => 'items', 'label' => 'Общежития', 'type' => 'array', 'item_fields' => $titleDescIconColor],
                ['key' => 'stats', 'label' => 'Статистика', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'value', 'label' => 'Значение', 'type' => 'text'],
                        ['key' => 'label', 'label' => 'Подпись',  'type' => 'translatable'],
                    ],
                ],
            ]],
            'virtual_tour' => ['items' => [
                ['key' => 'image', 'label' => 'Изображение панорамы', 'type' => 'image'],
                ['key' => 'hotspots', 'label' => 'Точки на изображении', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'x',        'label' => 'X (%)',    'type' => 'number'],
                        ['key' => 'y',        'label' => 'Y (%)',    'type' => 'number'],
                        ['key' => 'label',    'label' => 'Подпись',  'type' => 'translatable'],
                        ['key' => 'category', 'label' => 'Категория','type' => 'text'],
                    ],
                ],
                ['key' => 'filters', 'label' => 'Фильтры', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',     'label' => 'Иконка',    'type' => 'text'],
                        ['key' => 'label',    'label' => 'Подпись',   'type' => 'translatable'],
                        ['key' => 'category', 'label' => 'Категория', 'type' => 'text'],
                    ],
                ],
                ['key' => 'button', 'label' => 'Кнопка (JSON)', 'type' => 'text'],
            ]],
            default => null,
        };
    }

    /**
     * Прочие штучные блоки на разных страницах.
     */
    protected function miscSchema(string $key): ?array
    {
        return match ($key) {
            'legal-document', 'section-header' => ['items' => []],  // только title/content из встроенных

            'achievements' => ['items' => [
                ['key' => 'achievements', 'label' => 'Достижения', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'image',        'label' => 'Изображение', 'type' => 'image'],
                        ['key' => 'icon',         'label' => 'Иконка',      'type' => 'text'],
                        ['key' => 'title',        'label' => 'Заголовок',   'type' => 'translatable'],
                        ['key' => 'description',  'label' => 'Описание',    'type' => 'translatable_textarea'],
                        ['key' => 'badge',        'label' => 'Бейдж',       'type' => 'translatable'],
                        ['key' => 'badge_color',  'label' => 'Цвет бейджа', 'type' => 'text'],
                    ],
                ],
            ]],

            'faculty' => ['items' => [
                ['key' => 'faculty', 'label' => 'Состав преподавателей', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'name',        'label' => 'Имя',       'type' => 'translatable'],
                        ['key' => 'position',    'label' => 'Должность', 'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'О человеке','type' => 'translatable_textarea'],
                        ['key' => 'image',       'label' => 'Фото',      'type' => 'image'],
                    ],
                ],
            ]],

            'contact' => ['items' => [
                ['key' => 'phone',   'label' => 'Телефон', 'type' => 'text'],
                ['key' => 'email',   'label' => 'Email',   'type' => 'text'],
                ['key' => 'address', 'label' => 'Адрес',   'type' => 'translatable_textarea'],
            ]],

            'form' => ['items' => [
                ['key' => 'categories', 'label' => 'Категории', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'value', 'label' => 'value',  'type' => 'text'],
                        ['key' => 'label', 'label' => 'Подпись','type' => 'translatable'],
                    ],
                ],
                ['key' => 'fields', 'label' => 'Поля формы (JSON)', 'type' => 'text', 'help' => 'Сложная структура — редактировать как JSON'],
            ]],

            'settings' => ['items' => [
                ['key' => 'email',           'label' => 'Email',         'type' => 'text'],
                ['key' => 'phone',           'label' => 'Телефон',       'type' => 'text'],
                ['key' => 'reception_days',  'label' => 'Приёмные дни',  'type' => 'translatable'],
                ['key' => 'reception_time',  'label' => 'Часы приёма',   'type' => 'text'],
            ]],

            default => null,
        };
    }

    /**
     * Schemas для legacy-блоков главной (page=home, header, footer).
     *
     * 11 типов от первой версии ETU CMS, где каждый виджет на главной
     * был отдельным block_type вместо унификации (грид-карточек,
     * features-секций и т.п.). Большинство хранят только translatable
     * title; редкие — добавляют 1-3 поля типа image/icon/url.
     */
    protected function homePageSchema(string $key): ?array
    {
        return match ($key) {
            // Title-only — admin-core рисует translatable title через
            // built-in section, дополнительных полей не нужно.
            'stat', 'metric', 'ui_element', 'heading' => ['items' => []],

            // Иконочный виджет с подкладочным цветом + ссылкой.
            'feature_card', 'feature_item' => [
                'items' => [
                    ['key' => 'icon',  'label' => 'Иконка (FA)', 'type' => 'text', 'placeholder' => 'fas fa-users'],
                    ['key' => 'color', 'label' => 'Цвет',         'type' => 'text'],
                    ['key' => 'url',   'label' => 'Ссылка',       'type' => 'url'],
                ],
            ],

            // Карточка новости — image + ссылка. Текст в стандартном title.
            'news_card' => [
                'items' => [
                    ['key' => 'image', 'label' => 'Изображение', 'type' => 'image'],
                    ['key' => 'url',   'label' => 'Ссылка',      'type' => 'url'],
                ],
            ],

            // Карточка программы обучения — icon + цвет + URL.
            'program_card' => [
                'items' => [
                    ['key' => 'icon',  'label' => 'Иконка', 'type' => 'text'],
                    ['key' => 'color', 'label' => 'Цвет',   'type' => 'text'],
                    ['key' => 'url',   'label' => 'Ссылка', 'type' => 'url'],
                ],
            ],

            // Шаг абитуриентского пути — порядковый номер + (опц.) иконка.
            'admission_step' => [
                'items' => [
                    ['key' => 'step', 'label' => 'Шаг №', 'type' => 'number'],
                    ['key' => 'icon', 'label' => 'Иконка', 'type' => 'text'],
                ],
            ],

            // Пункт выпадающего списка формы — value (slug, не label).
            'form_option' => [
                'items' => [
                    ['key' => 'value', 'label' => 'value (slug)', 'type' => 'text'],
                ],
            ],

            // Псевдо-блок 'programs' хранит nested массив программ.
            'programs' => [
                'items' => [
                    [
                        'key' => 'programs', 'label' => 'Программы', 'type' => 'array',
                        'item_fields' => [
                            ['key' => 'icon',        'label' => 'Иконка',          'type' => 'text'],
                            ['key' => 'title',       'label' => 'Название',        'type' => 'translatable'],
                            ['key' => 'description', 'label' => 'Описание',        'type' => 'translatable_textarea'],
                            ['key' => 'url',         'label' => 'Ссылка',          'type' => 'url'],
                            ['key' => 'features',    'label' => 'Фичи (legacy)',   'type' => 'text', 'help' => 'JSON-массив строк'],
                        ],
                    ],
                ],
            ],

            default => null,
        };
    }

    /**
     * content — расширенная schema поверх admin-core defaults.
     *
     * Generic-`content` в ETU де-факто работает как discriminated union:
     * data.type выбирает sub-shape (grid → cards[], two-column → text+image,
     * программы → programs[], и т.д.). Без поля discriminator + repeater'ов
     * для cards/features/programs/categories у админа невидим примерно
     * половина контента — Vue открывает форму, но дополнительных полей нет,
     * сохранять/менять что-либо нельзя.
     *
     * Расширение добавляет:
     *   • `type` — select из реально-встречающихся sub-types в production-DB
     *     (grid, two-column, text-card, contact-cards, и т.д.).
     *   • `cards[]`, `features[]`, `programs[]`, `items[]` — самые частые
     *     repeater-поля. Vue скрывает пустые при первом редактировании
     *     блока, но для блоков где они уже наполнены — наконец-то редактируемы.
     *
     * Default 5 полей (icon/color/url/style/position) остаются для блоков
     * без `type` (которые в ETU production = 51 шт. из 107).
     */
    protected function contentSchema(): array
    {
        return [
            'items' => [
                ['key' => 'icon',     'label' => 'Иконка (FA или URL)', 'type' => 'text'],
                ['key' => 'color',    'label' => 'Цвет',                 'type' => 'text'],
                ['key' => 'url',      'label' => 'Ссылка',               'type' => 'url'],
                ['key' => 'style',    'label' => 'Стиль',                'type' => 'text'],
                ['key' => 'position', 'label' => 'Позиция (число)',      'type' => 'number'],
                ['key' => 'type',     'label' => 'Подтип вёрстки',       'type' => 'select',
                    'options' => [
                        ['value' => '',             'label' => '— по умолчанию —'],
                        ['value' => 'grid',         'label' => 'Сетка карточек'],
                        ['value' => 'two-column',   'label' => 'Две колонки'],
                        ['value' => 'text-card',    'label' => 'Текстовая карточка'],
                        ['value' => 'contact-cards','label' => 'Контактные карточки'],
                        ['value' => 'photo-grid',   'label' => 'Сетка фотографий'],
                        ['value' => 'features-grid','label' => 'Грид фич'],
                        ['value' => 'accordion',    'label' => 'Аккордеон'],
                        ['value' => 'two-cards',    'label' => 'Две карточки'],
                        ['value' => 'info-cards',   'label' => 'Info-карточки'],
                        ['value' => 'stats',        'label' => 'Статистика'],
                        ['value' => 'clubs-grid',   'label' => 'Грид клубов'],
                        ['value' => 'steps',        'label' => 'Шаги'],
                    ],
                ],
                ['key' => 'columns', 'label' => 'Колонок (1-4)', 'type' => 'number'],
                ['key' => 'icon_color',   'label' => 'Цвет иконки',         'type' => 'text'],
                ['key' => 'section_type', 'label' => 'Тип секции (legacy)', 'type' => 'text'],
                [
                    'key' => 'cards', 'label' => 'Карточки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',  'label' => 'Иконка',     'type' => 'text'],
                        ['key' => 'title', 'label' => 'Заголовок',  'type' => 'translatable'],
                        ['key' => 'text',  'label' => 'Описание',   'type' => 'translatable_textarea'],
                        ['key' => 'url',   'label' => 'Ссылка',     'type' => 'url'],
                        ['key' => 'color', 'label' => 'Цвет',       'type' => 'text'],
                    ],
                ],
                [
                    'key' => 'features', 'label' => 'Фичи', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',  'label' => 'Иконка',    'type' => 'text'],
                        ['key' => 'title', 'label' => 'Заголовок', 'type' => 'translatable'],
                        ['key' => 'text',  'label' => 'Описание',  'type' => 'translatable_textarea'],
                    ],
                ],
                [
                    'key' => 'items', 'label' => 'Элементы списка', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',  'label' => 'Иконка',    'type' => 'text'],
                        ['key' => 'title', 'label' => 'Заголовок', 'type' => 'translatable'],
                        ['key' => 'text',  'label' => 'Текст',     'type' => 'translatable_textarea'],
                    ],
                ],
                [
                    'key' => 'programs', 'label' => 'Программы (legacy)', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'code',        'label' => 'Код',        'type' => 'text'],
                        ['key' => 'icon',        'label' => 'Иконка',     'type' => 'text'],
                        ['key' => 'title',       'label' => 'Название',   'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',   'type' => 'translatable_textarea'],
                        ['key' => 'duration',    'label' => 'Длительность', 'type' => 'text'],
                    ],
                ],
            ],
        ];
    }

    /**
     * menu_item — пункт главного меню сайта (page_name='menu').
     *
     * Используется в HeaderComposer как источник переводов для лейблов
     * меню в header'е. Структура минимальная: только translatable
     * title (хранится в стандартной колонке page_blocks.title — Vue
     * рендерит её через built-in title-секцию формы). Все три локали
     * редактируются переключателем рядом с полем.
     *
     * `items: []` — нет дополнительных custom-полей. Этого достаточно
     * чтобы admin-форма открылась и кнопка Save заработала.
     */
    protected function menuItemSchema(): array
    {
        return [
            'items' => [],
        ];
    }

    /**
     * partners-section — расширяем default catalog схему чтобы вернуть
     * поле `category` (плоский text на каждом партнёре) и `icon`
     * заголовочной секции. ETU давно хранила партнёров с категорией;
     * после переезда блок-каталога в admin-core v1.0 поле потерялось,
     * и админка перестала позволять выбирать партнёра в категорию.
     *
     * Render-метод PartnersSectionBlock умеет читать и flat-формат
     * (`data.partners[]`), и legacy categories-формат
     * (`data.categories[].partners[]`) — после миграции данных flat
     * становится официальной формой.
     */
    protected function partnersSectionSchema(): array
    {
        return [
            'items' => [
                ['key' => 'icon', 'label' => 'Иконка секции', 'type' => 'text', 'placeholder' => 'fas fa-handshake'],
                [
                    'key' => 'partners', 'label' => 'Партнёры', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'logo',     'label' => 'Логотип',   'type' => 'image'],
                        ['key' => 'name',     'label' => 'Название',  'type' => 'translatable'],
                        ['key' => 'url',      'label' => 'Ссылка',    'type' => 'url'],
                        ['key' => 'category', 'label' => 'Категория', 'type' => 'translatable'],
                    ],
                ],
            ],
        ];
    }

}
