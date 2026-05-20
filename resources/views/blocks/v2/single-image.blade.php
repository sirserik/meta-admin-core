{{-- Single Image block v2 — крупный скан/изображение --}}
@php
    $resolveUrl = fn ($u) => str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/');
    $widthClass = match ($width) {
        'full' => 'w-full',
        'wide' => 'max-w-6xl mx-auto',
        default => 'container mx-auto px-4',
    };
    $aspectClass = match ($aspect) {
        'square' => 'aspect-square',
        '4-3' => 'aspect-[4/3]',
        '16-9' => 'aspect-video',
        'a4' => 'aspect-[1/1.414]',
        default => '',
    };
    $bgClass = match ($background) {
        'gray' => 'bg-gray-100',
        'soft' => 'bg-gradient-to-br from-gray-50 to-white',
        default => '',
    };
@endphp

@if(!empty($image))
<section class="py-8 {{ $bgClass }}">
    <div class="{{ $widthClass }}">
        <figure
            x-data="{ zoom: false }"
            class="@if($enableZoom) cursor-zoom-in @endif">
            <div class="overflow-hidden rounded-xl shadow-lg @if($aspectClass) {{ $aspectClass }} @endif bg-gray-50">
                <img src="{{ $resolveUrl($image) }}"
                     alt="{{ $alt }}"
                     loading="lazy"
                     @if($enableZoom) @click="zoom = true" @endif
                     class="w-full h-full object-contain">
            </div>
            @if($caption)
                <figcaption class="mt-3 text-center text-sm text-gray-600">{{ $caption }}</figcaption>
            @endif

            @if($enableZoom)
                <div x-show="zoom" x-cloak
                     x-transition.opacity
                     @keydown.escape.window="zoom = false"
                     @click.self="zoom = false"
                     class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4 cursor-zoom-out">
                    <button type="button" @click="zoom = false" class="absolute top-4 right-4 text-white/80 hover:text-white text-2xl"
                            aria-label="Закрыть"><i class="fas fa-times"></i></button>
                    <img src="{{ $resolveUrl($image) }}" alt="{{ $alt }}" class="max-w-full max-h-full object-contain">
                </div>
            @endif
        </figure>
    </div>
</section>
@endif
