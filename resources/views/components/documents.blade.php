@props([
    /**
     * Items resolved from a `document-list` block (or any shape with icon,
     * title, description, url). Translatable fields may arrive either as
     * strings already resolved to the current locale by PresentedBlock, or
     * as {ru,kk,en} maps from a raw source.
     */
    'items'       => [],
    'title'       => null,
    'description' => null,
    /** grid-2 | grid-3 | grid-4 | list | cards */
    'layout'      => 'grid-3',
    /** Extra Tailwind classes for the outer <section>. */
    'sectionClass' => 'py-16 bg-white',
    /** Heading level for $title (h2/h3/…). */
    'headingTag'  => 'h2',
])

@php
    $locale   = app()->getLocale();
    $fallback = config('app.fallback_locale', 'ru');

    $pick = function ($value) use ($locale, $fallback) {
        if ($value === null || is_scalar($value)) return (string) ($value ?? '');
        if (is_array($value)) {
            foreach ([$locale, $fallback] as $lc) {
                if (!empty($value[$lc])) return (string) $value[$lc];
            }
            foreach ($value as $v) if (!empty($v)) return (string) $v;
        }
        return '';
    };

    $colorHex = [
        'red'    => '#C41E3A',
        'blue'   => '#2563EB',
        'green'  => '#16A34A',
        'gold'   => '#D4A017',
        'purple' => '#7C3AED',
        'gray'   => '#6B7280',
    ];

    $wrapperClass = match ($layout) {
        'grid-2' => 'grid grid-cols-1 md:grid-cols-2 gap-6',
        'grid-4' => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6',
        'list'   => 'flex flex-col gap-3',
        'cards'  => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8',
        default  => 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6',
    };

    $itemClass = match ($layout) {
        'list'  => 'flex items-center gap-4 p-4 bg-white rounded-lg border border-gray-200 hover:border-red-300 hover:shadow-md transition',
        'cards' => 'flex flex-col gap-3 p-6 bg-university-gray rounded-2xl shadow-lg border border-gray-200 hover:shadow-xl transition',
        default => 'flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-200 hover:border-red-300 hover:shadow-md transition',
    };

    $normalized = [];
    foreach ($items ?? [] as $it) {
        if (!is_array($it)) continue;
        $url = $pick($it['url'] ?? ($it['file'] ?? ''));
        if ($url === '' && empty($it['title'])) continue;
        $normalized[] = [
            'icon'        => $it['icon']        ?? 'fas fa-file-alt',
            'color'       => $it['color']       ?? '',
            'title'       => $pick($it['title'] ?? ''),
            'description' => $pick($it['description'] ?? ''),
            'url'         => $url,
            'target'      => $it['target']      ?? '_blank',
        ];
    }
@endphp

@if (empty($normalized) && empty($title))
    {{-- nothing to render --}}
@else
<section {{ $attributes->merge(['class' => $sectionClass]) }}>
    <div class="container mx-auto px-4">
        @if ($title || $description)
            <div class="text-center mb-10">
                @if ($title)
                    <{{ $headingTag }} class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">
                        {{ $pick($title) }}
                    </{{ $headingTag }}>
                @endif
                @if ($description)
                    <p class="text-lg text-gray-600 max-w-3xl mx-auto">{{ $pick($description) }}</p>
                @endif
            </div>
        @endif

        <div class="{{ $wrapperClass }} max-w-6xl mx-auto">
            @foreach ($normalized as $it)
                @php
                    $hex = $colorHex[$it['color']] ?? '#C41E3A';
                @endphp
                <a href="{{ $it['url'] }}"
                   target="{{ $it['target'] }}"
                   @if($it['target'] === '_blank') rel="noopener noreferrer" @endif
                   class="{{ $itemClass }} group">
                    <span class="w-11 h-11 rounded-lg flex items-center justify-center flex-shrink-0"
                          style="background: {{ $hex }}20; color: {{ $hex }};">
                        <i class="{{ $it['icon'] }} text-lg"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-semibold text-gray-900 group-hover:text-university-red transition">
                            {{ $it['title'] ?: $it['url'] }}
                        </span>
                        @if ($it['description'])
                            <span class="block mt-1 text-sm text-gray-600">{{ $it['description'] }}</span>
                        @endif
                    </span>
                    <i class="fas fa-arrow-right text-gray-400 text-xs group-hover:text-university-red transition"></i>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
