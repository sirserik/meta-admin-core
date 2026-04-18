<?php

namespace Meta\AdminCore\Support;

use Meta\AdminCore\Contracts\BlockCatalog;

/**
 * Full META-University block catalog shipped with meta/admin-core.
 *
 * Consumer sites get every page and every block type out of the box —
 * enough to assemble a full university website without touching code.
 * Override by rebinding `Meta\AdminCore\Contracts\BlockCatalog` in a
 * service provider.
 *
 * Source of truth for:
 *   - Human-readable labels of pages and block types
 *   - Descriptions of what each block type does
 *   - FontAwesome icons + ASCII previews used in the picker
 *   - Grouping of pages by section of the site
 *
 * To add a new block type — extend this class, override BLOCK_TYPES,
 * rebind the contract. Or for a per-site tweak, append/remove entries
 * in a subclass.
 */
class DefaultBlockCatalog implements BlockCatalog
{
    public const PAGES = [
        'Основные' => [
            'home'              => 'Главная страница',
            'about'             => 'О нас / История',
            'contact'           => 'Контакты',
            'documents'         => 'Документы',
            'accreditation'     => 'Аккредитация',
            'infrastructure'    => 'Инфраструктура',
            'programs'          => 'Программы',
            'green-deal-center' => 'Центр Зелёной Сделки',
        ],
        'Поступление' => [
            'admissions'           => 'Процесс поступления',
            'admission-commission' => 'Приёмная комиссия',
            'bachelor'             => 'Бакалавриат',
            'masters'              => 'Магистратура',
            'foreign-students'     => 'Иностранным студентам',
        ],
        'Студентам' => [
            'students'             => 'Студентам',
            'student-clubs'        => 'Студенческие клубы',
            'student-house'        => 'Дом студентов',
            'library'              => 'Библиотека',
            'electronic-resources' => 'Электронные ресурсы',
            'academic-calendar'    => 'Академический календарь',
        ],
        'Школы' => [
            'schools-main'       => 'Школы (главная)',
            'engineering-school' => 'Инженерная школа',
            'econlaw-school'     => 'Экономика и Право',
            'business-school'    => 'META Business School',
        ],
        'Международное' => [
            'international'      => 'Международное сотрудничество',
            'lincoln-university' => 'Lincoln University',
            'language-courses'   => 'Языковые курсы',
        ],
        'Другое' => [
            'science' => 'Наука',
            'careers' => 'Карьера',
        ],
        'SDG Портал' => [
            'sdg-portal'      => 'SDG Портал (главная)',
            'sdg-portal-news' => 'SDG Портал (новости)',
        ],
        'Служебные' => [
            'header'   => 'Шапка сайта',
            'footer'   => 'Подвал',
            'settings' => 'Глобальные настройки',
            'rector'   => 'Ректор (настройки)',
        ],
    ];

    public const BLOCK_TYPES = [
        // ---------- Hero / заголовки ----------
        'hero'           => ['label' => 'Главный баннер',     'description' => 'Большой верхний баннер страницы с заголовком, подзаголовком и кнопкой', 'icon' => 'fa-flag',    'category' => 'Hero',    'preview' => '▇▇▇▇▇'],
        'heading'        => ['label' => 'Заголовок секции',   'description' => 'Разделитель с большим заголовком перед группой других блоков',          'icon' => 'fa-heading', 'category' => 'Hero',    'preview' => '# ▁▁'],
        'section-header' => ['label' => 'Шапка секции',       'description' => 'Заголовок + подзаголовок + опциональная линия',                          'icon' => 'fa-minus',   'category' => 'Hero',    'preview' => '≡ ▁'],

        // ---------- Контент ----------
        'content'         => ['label' => 'Текстовый блок',     'description' => 'Обычный текст с заголовком, подзаголовком и подробным содержимым', 'icon' => 'fa-paragraph',  'category' => 'Контент', 'preview' => '▇ ▇ ▇'],
        'content-section' => ['label' => 'Контентная секция',  'description' => 'Контейнер для форматированного контента с иконкой и подписью',      'icon' => 'fa-file-lines', 'category' => 'Контент', 'preview' => '▇▇ · ▇'],
        'rich-content'    => ['label' => 'Богатый контент',    'description' => 'HTML-контент с произвольной версткой (только для разработчиков)',   'icon' => 'fa-code',       'category' => 'Контент', 'preview' => '</>  '],
        'text-card'       => ['label' => 'Текстовая карточка', 'description' => 'Компактный блок-карточка с заголовком и коротким текстом',         'icon' => 'fa-id-card',    'category' => 'Контент', 'preview' => '▢ ▁'],

        // ---------- Списки / сетки ----------
        'features'         => ['label' => 'Преимущества',            'description' => 'Сетка иконок с подписями — для перечисления особенностей',      'icon' => 'fa-list-check', 'category' => 'Сетки', 'preview' => '✓ ✓ ✓'],
        'features-section' => ['label' => 'Секция преимуществ',      'description' => 'Заголовок + список особенностей в единой секции',               'icon' => 'fa-star',       'category' => 'Сетки', 'preview' => '★ ★ ★'],
        'stats'            => ['label' => 'Статистика',              'description' => 'Блок больших чисел (студенты / выпускники / годы и т.п.)',     'icon' => 'fa-chart-simple','category' => 'Сетки','preview' => '12 · 34 · 56'],
        'grid-cards'       => ['label' => 'Сетка карточек',          'description' => 'Гибкая сетка произвольных карточек (текст + иконка + ссылка)',  'icon' => 'fa-grip',       'category' => 'Сетки', 'preview' => '▢ ▢ ▢'],
        'two-cards'        => ['label' => 'Две карточки',            'description' => 'Две карточки рядом, для сравнения или выбора',                  'icon' => 'fa-clone',      'category' => 'Сетки', 'preview' => '▢ ▢'],
        'info-cards'       => ['label' => 'Информационные карточки', 'description' => 'Карточки с заголовком, иконкой, описанием и ссылкой',           'icon' => 'fa-circle-info','category' => 'Сетки', 'preview' => 'i ▢ ▢'],
        'benefits-grid'    => ['label' => 'Сетка бенефитов',         'description' => 'Плотная сетка с иконками и подписями для льгот и плюсов',      'icon' => 'fa-award',      'category' => 'Сетки', 'preview' => '✦ ✦ ✦'],
        'reasons'          => ['label' => 'Причины выбрать',         'description' => 'Список пронумерованных причин / тезисов',                       'icon' => 'fa-list-ol',    'category' => 'Сетки', 'preview' => '1 2 3'],
        'discounts'        => ['label' => 'Скидки и льготы',         'description' => 'Блок со списком акций и скидок',                                'icon' => 'fa-tag',        'category' => 'Сетки', 'preview' => '% %'],
        'achievements'     => ['label' => 'Достижения',              'description' => 'Карточки достижений с изображениями, описанием и бейджами',    'icon' => 'fa-trophy',     'category' => 'Сетки', 'preview' => '🏆 ▢'],

        // ---------- CTA / Ссылки ----------
        'cta'          => ['label' => 'Призыв к действию',    'description' => 'Контрастный блок с кнопкой — подать заявку, связаться и т.п.', 'icon' => 'fa-bullhorn',  'category' => 'CTA', 'preview' => '▶ Кнопка'],
        'cta-contacts' => ['label' => 'CTA с контактами',      'description' => 'Призыв к действию + телефон/email/адрес',                      'icon' => 'fa-phone',     'category' => 'CTA', 'preview' => '☎ ▶'],
        'quick-links'  => ['label' => 'Быстрые ссылки',        'description' => 'Набор кнопок-ссылок для навигации по разделам',                'icon' => 'fa-link',      'category' => 'CTA', 'preview' => '→ → →'],
        'links'        => ['label' => 'Документы / Ссылки',    'description' => 'Список прикреплённых документов или внешних ссылок',          'icon' => 'fa-paperclip', 'category' => 'CTA', 'preview' => '📎 📎'],

        // ---------- Галерея / Медиа ----------
        'gallery'         => ['label' => 'Галерея',           'description' => 'Сетка изображений с подписями',          'icon' => 'fa-images',       'category' => 'Медиа', 'preview' => '▢ ▢ ▢'],
        'gallery-slider'  => ['label' => 'Слайдер галереи',   'description' => 'Карусель изображений на всю ширину',     'icon' => 'fa-image',        'category' => 'Медиа', 'preview' => '◀ ▇▇ ▶'],
        'photo-grid'      => ['label' => 'Фото-сетка',        'description' => 'Плотная сетка фотографий без подписей',  'icon' => 'fa-table-cells',  'category' => 'Медиа', 'preview' => '▢▢▢'],

        // ---------- Образование / Приём ----------
        'programs'                => ['label' => 'Список программ',          'description' => 'Превью-карточки образовательных программ',                   'icon' => 'fa-graduation-cap', 'category' => 'Образование', 'preview' => '🎓'],
        'programs-table'          => ['label' => 'Таблица программ',         'description' => 'Таблица программ со столбцами уровня, длительности, кредитов','icon' => 'fa-table',          'category' => 'Образование', 'preview' => '▤ ▤'],
        'admission-step'          => ['label' => 'Шаг поступления',          'description' => 'Один шаг процесса поступления',                              'icon' => 'fa-shoe-prints',    'category' => 'Образование', 'preview' => '① →'],
        'admission-process'       => ['label' => 'Процесс поступления',      'description' => 'Многошаговый процесс с нумерованными этапами',              'icon' => 'fa-list-check',     'category' => 'Образование', 'preview' => '① ② ③'],
        'admission-documents'     => ['label' => 'Документы для поступления','description' => 'Список документов с иконками-типами',                        'icon' => 'fa-file-lines',     'category' => 'Образование', 'preview' => '📋 📋'],
        'accreditation-documents' => ['label' => 'Документы аккредитации',   'description' => 'Сетка сертификатов и лицензий',                              'icon' => 'fa-certificate',    'category' => 'Образование', 'preview' => '🏅'],
        'downloadable-docs'       => ['label' => 'Скачиваемые документы',    'description' => 'Список PDF-файлов с кнопкой скачать',                        'icon' => 'fa-file-pdf',       'category' => 'Образование', 'preview' => '⬇ pdf'],
        'creative-exams'          => ['label' => 'Творческие экзамены',      'description' => 'Карточки творческих специальностей и экзаменов',            'icon' => 'fa-palette',        'category' => 'Образование', 'preview' => '🎨'],

        // ---------- Люди ----------
        'team'          => ['label' => 'Команда',            'description' => 'Карточки членов команды с фото',                'icon' => 'fa-people-group',   'category' => 'Люди', 'preview' => '👥'],
        'faculty'       => ['label' => 'Факультет / ППС',    'description' => 'Сетка преподавателей и научных сотрудников',    'icon' => 'fa-chalkboard-user','category' => 'Люди', 'preview' => '👨‍🏫'],
        'contact-cards' => ['label' => 'Карточки контактов', 'description' => 'Сотрудники с фото + контакты (телефон/email)',  'icon' => 'fa-address-card',   'category' => 'Люди', 'preview' => '☎ 👤'],

        // ---------- Повседневные ----------
        'timeline'         => ['label' => 'Лента времени',     'description' => 'Хронология событий с датами и описаниями',     'icon' => 'fa-timeline',           'category' => 'Повседневные', 'preview' => '—●—●—'],
        'faq'              => ['label' => 'FAQ / Вопрос-Ответ','description' => 'Аккордеон с вопросами и развёрнутыми ответами','icon' => 'fa-circle-question',    'category' => 'Повседневные', 'preview' => '? ▼'],
        'news-section'     => ['label' => 'Секция новостей',   'description' => 'Блок с подборкой последних новостей',          'icon' => 'fa-newspaper',          'category' => 'Повседневные', 'preview' => '📰'],
        'partners-section' => ['label' => 'Партнёры',          'description' => 'Сетка логотипов партнёров',                    'icon' => 'fa-handshake',          'category' => 'Повседневные', 'preview' => '▭ ▭ ▭'],
        'two-column'       => ['label' => 'Две колонки',       'description' => 'Контент в две колонки (текст + изображение)',  'icon' => 'fa-table-columns',      'category' => 'Повседневные', 'preview' => '▇ | ▇'],
        'paragraphs'       => ['label' => 'Серия параграфов',  'description' => 'Последовательные текстовые параграфы',         'icon' => 'fa-align-left',         'category' => 'Повседневные', 'preview' => '= = ='],

        // ---------- Клубы / Инфраструктура ----------
        'clubs-grid'        => ['label' => 'Сетка клубов',        'description' => 'Карточки студенческих клубов',                      'icon' => 'fa-people-arrows', 'category' => 'Студенты',        'preview' => '🎭'],
        'buildings'         => ['label' => 'Корпуса / Здания',    'description' => 'Список корпусов университета',                      'icon' => 'fa-building',      'category' => 'Инфраструктура',  'preview' => '🏢'],
        'labs'              => ['label' => 'Лаборатории',         'description' => 'Карточки лабораторий с описанием',                  'icon' => 'fa-flask',         'category' => 'Инфраструктура',  'preview' => '⚗'],
        'it_infrastructure' => ['label' => 'IT-инфраструктура',   'description' => 'Описание компьютерных классов и IT-ресурсов',      'icon' => 'fa-server',        'category' => 'Инфраструктура',  'preview' => '💻'],
        'sports'            => ['label' => 'Спортивные объекты',  'description' => 'Спортзалы, площадки, секции',                       'icon' => 'fa-dumbbell',      'category' => 'Инфраструктура',  'preview' => '⚽'],
        'dormitories'       => ['label' => 'Общежития',           'description' => 'Информация об общежитиях и условиях проживания',    'icon' => 'fa-bed',           'category' => 'Инфраструктура',  'preview' => '🛏'],
        'virtual_tour'      => ['label' => 'Виртуальный тур',     'description' => 'Встроенный 360°-тур по кампусу',                    'icon' => 'fa-street-view',   'category' => 'Инфраструктура',  'preview' => '360°'],

        // ---------- GDC ----------
        'research-section'     => ['label' => 'GDC: Исследования', 'description' => 'Блок научных исследований Центра',        'icon' => 'fa-microscope', 'category' => 'GDC', 'preview' => '🔬'],
        'publications-section' => ['label' => 'GDC: Публикации',   'description' => 'Список публикаций сотрудников Центра',    'icon' => 'fa-book',       'category' => 'GDC', 'preview' => '📚'],
        'contacts-section'     => ['label' => 'GDC: Контакты',     'description' => 'Контактная информация Центра',            'icon' => 'fa-envelope',   'category' => 'GDC', 'preview' => '✉'],

        // ---------- Меню / соц-сети (управляются SiteSettings) ----------
        'menu_settings' => ['label' => 'Настройки меню', 'description' => 'Включение/выключение пунктов главного меню и их порядок', 'icon' => 'fa-bars',         'category' => 'Навигация', 'preview' => '≡'],
        'menu_item'     => ['label' => 'Пункт меню',     'description' => 'Элемент навигационного меню',                             'icon' => 'fa-bars',         'category' => 'Навигация', 'preview' => '—'],
        'social-links'  => ['label' => 'Соцсети',        'description' => 'Ссылки на социальные сети',                               'icon' => 'fa-share-nodes',  'category' => 'Навигация', 'preview' => '⚙ ⚙'],

        // ---------- Прочие / Системные ----------
        'ui_element'      => ['label' => 'UI-элемент',           'description' => 'Обобщённый UI-блок (кнопка, бейдж, метка)', 'icon' => 'fa-puzzle-piece',  'category' => 'Системные', 'preview' => '▢'],
        'feature_card'    => ['label' => 'Карточка-особенность', 'description' => 'Одиночная карточка-фича',                  'icon' => 'fa-square',        'category' => 'Системные', 'preview' => '▢'],
        'feature_item'    => ['label' => 'Элемент фичи',         'description' => 'Один пункт в списке фич',                  'icon' => 'fa-check',         'category' => 'Системные', 'preview' => '✓'],
        'program_card'    => ['label' => 'Карточка программы',   'description' => 'Карточка одной образовательной программы','icon' => 'fa-square',        'category' => 'Системные', 'preview' => '🎓'],
        'stat'            => ['label' => 'Статистика (одна)',    'description' => 'Один показатель статистики',               'icon' => 'fa-chart-line',    'category' => 'Системные', 'preview' => '123'],
        'metric'          => ['label' => 'Метрика',              'description' => 'Числовая метрика',                         'icon' => 'fa-gauge',         'category' => 'Системные', 'preview' => '—'],
        'form_option'     => ['label' => 'Опция формы',          'description' => 'Элемент выпадающего списка формы',         'icon' => 'fa-list',          'category' => 'Системные', 'preview' => '▼'],
        'news_card'       => ['label' => 'Карточка новости',     'description' => 'Одна карточка в секции новостей',          'icon' => 'fa-newspaper',     'category' => 'Системные', 'preview' => '📰'],
        'legal-document'  => ['label' => 'Юридический документ', 'description' => 'Текст договора / политики',                'icon' => 'fa-file-contract', 'category' => 'Системные', 'preview' => '§'],
        'contact'         => ['label' => 'Контакт',              'description' => 'Одна контактная запись',                   'icon' => 'fa-address-book',  'category' => 'Системные', 'preview' => '☎'],
        'form'            => ['label' => 'Форма',                'description' => 'Встроенная форма (подача заявки)',         'icon' => 'fa-wpforms',       'category' => 'Системные', 'preview' => '▤'],
        'settings'        => ['label' => 'Настройки',            'description' => 'Конфигурационный блок',                    'icon' => 'fa-cog',           'category' => 'Системные', 'preview' => '⚙'],
        'image'           => ['label' => 'Изображение',          'description' => 'Одно изображение',                         'icon' => 'fa-image',         'category' => 'Системные', 'preview' => '🖼'],
    ];

    public function pagesGrouped(): array
    {
        return static::PAGES;
    }

    public function pageLabel(string $slug): string
    {
        foreach (static::PAGES as $pages) {
            if (isset($pages[$slug])) return $pages[$slug];
        }
        return $slug;
    }

    /**
     * Flat list [{slug, label, group}] — useful for grouped dropdowns.
     */
    public function pagesFlat(): array
    {
        $out = [];
        foreach (static::PAGES as $group => $pages) {
            foreach ($pages as $slug => $label) {
                $out[] = ['slug' => $slug, 'label' => $label, 'group' => $group];
            }
        }
        return $out;
    }

    public function blockTypesGrouped(): array
    {
        $grouped = [];
        foreach (static::BLOCK_TYPES as $key => $info) {
            $cat = $info['category'] ?? 'Другое';
            $grouped[$cat] ??= [];
            $grouped[$cat][] = array_merge($info, ['key' => $key]);
        }
        return $grouped;
    }

    public function blockTypesFlat(): array
    {
        $out = [];
        foreach (static::BLOCK_TYPES as $key => $info) {
            $out[] = array_merge($info, ['key' => $key]);
        }
        return $out;
    }

    public function blockType(string $key): array
    {
        if (!isset(static::BLOCK_TYPES[$key])) {
            return [
                'key'         => $key,
                'label'       => $key,
                'icon'        => 'fa-puzzle-piece',
                'description' => '',
                'category'    => 'Прочее',
            ];
        }
        return array_merge(static::BLOCK_TYPES[$key], ['key' => $key]);
    }

    /**
     * Visual schemas for the common block types. When a schema exists,
     * the admin renders a proper form (array-of-records editor) instead
     * of a raw JSON textarea. Everything else falls back to JSON.
     */
    public const SCHEMAS = [
        'hero' => [
            'items' => [
                ['key' => 'image',     'label' => 'Фоновое изображение', 'type' => 'image'],
                ['key' => 'cta_label', 'label' => 'Текст кнопки',         'type' => 'text'],
                ['key' => 'cta_url',   'label' => 'Ссылка кнопки',        'type' => 'url'],
            ],
        ],
        'hero-slides' => [
            'items' => [
                [
                    'key'   => 'slides',
                    'label' => 'Слайды',
                    'type'  => 'array',
                    'item_fields' => [
                        ['key' => 'title',     'label' => 'Заголовок',    'type' => 'text'],
                        ['key' => 'subtitle',  'label' => 'Подзаголовок', 'type' => 'textarea'],
                        ['key' => 'image',     'label' => 'Картинка',     'type' => 'image'],
                        ['key' => 'cta_label', 'label' => 'Текст кнопки', 'type' => 'text'],
                        ['key' => 'cta_url',   'label' => 'Ссылка',       'type' => 'url'],
                    ],
                ],
            ],
        ],
        'stats' => [
            'items' => [
                [
                    'key' => 'items', 'label' => 'Числа', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'value', 'label' => 'Значение', 'type' => 'text'],
                        ['key' => 'label', 'label' => 'Подпись',  'type' => 'text'],
                        ['key' => 'icon',  'label' => 'Иконка',   'type' => 'text'],
                    ],
                ],
            ],
        ],
        'features' => [
            'items' => [
                [
                    'key' => 'items', 'label' => 'Преимущества', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка (FA)', 'type' => 'text'],
                        ['key' => 'title',       'label' => 'Заголовок',   'type' => 'text'],
                        ['key' => 'description', 'label' => 'Описание',    'type' => 'textarea'],
                    ],
                ],
            ],
        ],
        'info-cards' => [
            'items' => [
                [
                    'key' => 'cards', 'label' => 'Карточки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка',    'type' => 'text'],
                        ['key' => 'title',       'label' => 'Заголовок', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Описание',  'type' => 'textarea'],
                        ['key' => 'url',         'label' => 'Ссылка',    'type' => 'url'],
                    ],
                ],
            ],
        ],
        'grid-cards' => [
            'items' => [
                [
                    'key' => 'cards', 'label' => 'Карточки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка',    'type' => 'text'],
                        ['key' => 'title',       'label' => 'Заголовок', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Описание',  'type' => 'textarea'],
                        ['key' => 'url',         'label' => 'Ссылка',    'type' => 'url'],
                    ],
                ],
            ],
        ],
        'faq' => [
            'items' => [
                [
                    'key' => 'items', 'label' => 'Вопросы', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'question', 'label' => 'Вопрос', 'type' => 'text'],
                        ['key' => 'answer',   'label' => 'Ответ',  'type' => 'textarea'],
                    ],
                ],
            ],
        ],
        'gallery' => [
            'items' => [
                [
                    'key' => 'images', 'label' => 'Изображения', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'image',   'label' => 'Файл',    'type' => 'image'],
                        ['key' => 'caption', 'label' => 'Подпись', 'type' => 'text'],
                    ],
                ],
            ],
        ],
        'photo-grid' => [
            'items' => [
                [
                    'key' => 'images', 'label' => 'Фотографии', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'image', 'label' => 'Файл', 'type' => 'image'],
                    ],
                ],
            ],
        ],
        'timeline' => [
            'items' => [
                [
                    'key' => 'events', 'label' => 'События', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'date',        'label' => 'Дата',     'type' => 'text'],
                        ['key' => 'title',       'label' => 'Заголовок','type' => 'text'],
                        ['key' => 'description', 'label' => 'Описание', 'type' => 'textarea'],
                    ],
                ],
            ],
        ],
        'team' => [
            'items' => [
                [
                    'key' => 'members', 'label' => 'Команда', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'photo',    'label' => 'Фото',      'type' => 'image'],
                        ['key' => 'name',     'label' => 'Имя',       'type' => 'text'],
                        ['key' => 'position', 'label' => 'Должность', 'type' => 'text'],
                        ['key' => 'bio',      'label' => 'О нём',     'type' => 'textarea'],
                    ],
                ],
            ],
        ],
        'partners-section' => [
            'items' => [
                [
                    'key' => 'partners', 'label' => 'Партнёры', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'logo', 'label' => 'Логотип', 'type' => 'image'],
                        ['key' => 'name', 'label' => 'Название','type' => 'text'],
                        ['key' => 'url',  'label' => 'Ссылка',  'type' => 'url'],
                    ],
                ],
            ],
        ],
        'quick-links' => [
            'items' => [
                [
                    'key' => 'links', 'label' => 'Ссылки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',  'label' => 'Иконка',     'type' => 'text'],
                        ['key' => 'label', 'label' => 'Название',   'type' => 'text'],
                        ['key' => 'url',   'label' => 'URL',        'type' => 'url'],
                    ],
                ],
            ],
        ],
        'links' => [
            'items' => [
                ['key' => 'layout', 'label' => 'Layout', 'type' => 'text', 'placeholder' => 'grid / list'],
                [
                    'key' => 'links', 'label' => 'Документы / ссылки', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon',        'label' => 'Иконка (FA)',   'type' => 'text'],
                        ['key' => 'color',       'label' => 'Цвет',          'type' => 'text', 'placeholder' => 'blue / red / green / gold / purple'],
                        ['key' => 'title',       'label' => 'Название',      'type' => 'translatable'],
                        ['key' => 'description', 'label' => 'Описание',      'type' => 'translatable_textarea'],
                        ['key' => 'url',         'label' => 'URL / файл',    'type' => 'translatable_file'],
                    ],
                ],
            ],
        ],
        'downloadable-docs' => [
            'items' => [
                [
                    'key' => 'documents', 'label' => 'Документы', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title',    'label' => 'Название',     'type' => 'text'],
                        ['key' => 'url',      'label' => 'Файл (PDF/…)', 'type' => 'file'],
                        ['key' => 'category', 'label' => 'Категория',    'type' => 'text'],
                    ],
                ],
            ],
        ],
        'admission-documents' => [
            'items' => [
                [
                    'key' => 'documents', 'label' => 'Документы для поступления', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title', 'label' => 'Название', 'type' => 'text'],
                        ['key' => 'url',   'label' => 'Файл',     'type' => 'file'],
                    ],
                ],
            ],
        ],
        'accreditation-documents' => [
            'items' => [
                [
                    'key' => 'documents', 'label' => 'Сертификаты / лицензии', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'title', 'label' => 'Название',  'type' => 'text'],
                        ['key' => 'image', 'label' => 'Превью',    'type' => 'image'],
                        ['key' => 'url',   'label' => 'PDF-файл',  'type' => 'file'],
                    ],
                ],
            ],
        ],
        // Простой action-button (используется как CTA-кнопка-иконка внутри
        // страниц типа SDG portal).
        'content' => [
            'items' => [
                ['key' => 'icon',     'label' => 'Иконка (FA или URL)', 'type' => 'text'],
                ['key' => 'color',    'label' => 'Цвет',                'type' => 'text'],
                ['key' => 'url',      'label' => 'Ссылка',              'type' => 'url'],
                ['key' => 'style',    'label' => 'Стиль',               'type' => 'text'],
                ['key' => 'position', 'label' => 'Позиция (число)',     'type' => 'number'],
            ],
        ],
        'social-links' => [
            'items' => [
                [
                    'key' => 'networks', 'label' => 'Сети', 'type' => 'array',
                    'item_fields' => [
                        ['key' => 'icon', 'label' => 'Иконка (FA)',     'type' => 'text'],
                        ['key' => 'url',  'label' => 'URL профиля',     'type' => 'url'],
                    ],
                ],
            ],
        ],
        'cta' => [
            'items' => [
                ['key' => 'cta_label', 'label' => 'Текст кнопки', 'type' => 'text'],
                ['key' => 'cta_url',   'label' => 'Ссылка',        'type' => 'url'],
                ['key' => 'background','label' => 'Фоновое изображение', 'type' => 'image'],
            ],
        ],
        'image' => [
            'items' => [
                ['key' => 'image', 'label' => 'Изображение', 'type' => 'image'],
                ['key' => 'url',   'label' => 'Ссылка',      'type' => 'url'],
                ['key' => 'alt',   'label' => 'Alt-текст',   'type' => 'text'],
            ],
        ],
    ];

    public function blockSchema(string $key): ?array
    {
        return static::SCHEMAS[$key] ?? null;
    }
}
