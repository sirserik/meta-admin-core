<?php

/**
 * META UNIVERSITY — Design Token System
 *
 * Все дизайн-токены в одном месте. Разработчик меняет только этот файл
 * (или настройки в админке), и весь сайт обновляется.
 *
 * Токены генерируются как CSS-переменные --t-* и доступны везде.
 */

return [

    /* ===== ЦВЕТА ===== */
    'colors' => [
        'primary'       => env('THEME_PRIMARY', '#C41E3A'),
        'primary-light' => env('THEME_PRIMARY_LIGHT', '#E53E3E'),
        'primary-dark'  => env('THEME_PRIMARY_DARK', '#8B0000'),
        'accent'        => env('THEME_ACCENT', '#F4A261'),
        'accent-light'  => env('THEME_ACCENT_LIGHT', '#FFB84D'),
        'dark'          => '#1f2937',
        'body'          => '#f8f9fa',
        'surface'       => '#ffffff',
        'text'          => '#1f2937',
        'text-muted'    => '#6b7280',
        'text-light'    => '#9ca3af',
        'border'        => '#e5e7eb',
        'success'       => '#16a34a',
        'warning'       => '#f59e0b',
        'danger'        => '#dc2626',
        'info'          => '#2563eb',
    ],

    /* ===== ТИПОГРАФИКА ===== */
    'fonts' => [
        'heading' => env('THEME_FONT_HEADING', "'Inter', system-ui, sans-serif"),
        'body'    => env('THEME_FONT_BODY', "'Inter', system-ui, sans-serif"),
        'mono'    => "'JetBrains Mono', monospace",
    ],

    'font_sizes' => [
        'h1'       => 'clamp(2.5rem, 5vw, 4.5rem)',  // 40px → 72px
        'h2'       => 'clamp(2rem, 4vw, 3rem)',       // 32px → 48px
        'h3'       => 'clamp(1.5rem, 3vw, 2rem)',     // 24px → 32px
        'h4'       => '1.25rem',                       // 20px
        'body'     => '1rem',                           // 16px
        'small'    => '0.875rem',                       // 14px
        'xs'       => '0.75rem',                        // 12px
    ],

    /* ===== ОТСТУПЫ ===== */
    'spacing' => [
        'section-y'  => '6rem',    // py секций
        'section-y-sm' => '4rem',  // py секций на мобильных
        'container'  => '1rem',    // px контейнера
        'card'       => '1.5rem',  // padding карточек
        'gap'        => '2rem',    // gap между элементами
        'gap-sm'     => '1rem',    // gap на мобильных
    ],

    /* ===== СКРУГЛЕНИЯ ===== */
    'radius' => [
        'sm'    => '0.5rem',
        'md'    => '0.75rem',
        'lg'    => '1.25rem',
        'xl'    => '1.5rem',
        'full'  => '9999px',
    ],

    /* ===== ТЕНИ ===== */
    'shadows' => [
        'sm'   => '0 1px 3px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.04)',
        'md'   => '0 4px 12px rgba(0,0,0,0.06), 0 2px 4px rgba(0,0,0,0.04)',
        'lg'   => '0 12px 40px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04)',
        'xl'   => '0 20px 60px rgba(0,0,0,0.1), 0 8px 16px rgba(0,0,0,0.06)',
        'glow' => '0 4px 16px rgba(196,30,58,0.2)',
    ],

    /* ===== ПЕРЕХОДЫ ===== */
    'transitions' => [
        'fast'    => '0.15s ease',
        'normal'  => '0.3s cubic-bezier(0.16, 1, 0.3, 1)',
        'slow'    => '0.5s cubic-bezier(0.16, 1, 0.3, 1)',
    ],

    /* ===== BREAKPOINTS ===== */
    'breakpoints' => [
        'sm' => '640px',
        'md' => '768px',
        'lg' => '1024px',
        'xl' => '1280px',
    ],

    /* ===== LAYOUT ===== */
    'layout' => [
        'container_max' => '1280px',
        'content_max'   => '768px',
    ],
];
