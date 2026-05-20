{{-- Gallery block v2 --}}
@php
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
    $resolveUrl = fn ($u) => str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/');
@endphp

@if(!empty($images))
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif

        @if($layout === 'carousel')
            <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory pb-4">
                @foreach($images as $img)
                    <figure class="flex-shrink-0 w-80 snap-center">
                        <img src="{{ $resolveUrl($img['url']) }}" alt="{{ $img['alt'] }}" loading="lazy"
                             class="w-full h-56 object-cover rounded-xl shadow-md">
                        @if($img['caption'])<figcaption class="mt-2 text-sm text-gray-600 text-center">{{ $img['caption'] }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-1 {{ $gridClass }} gap-4">
                @foreach($images as $img)
                    <figure class="group overflow-hidden rounded-xl shadow-md hover:shadow-xl transition-shadow">
                        <img src="{{ $resolveUrl($img['url']) }}" alt="{{ $img['alt'] }}" loading="lazy"
                             class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                        @if($img['caption'])
                            <figcaption class="p-3 text-sm text-gray-700 bg-gray-50">{{ $img['caption'] }}</figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
