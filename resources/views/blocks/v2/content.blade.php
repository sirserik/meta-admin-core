{{-- Content block v2 --}}
@php
    $bgClass = match ($background) {
        'white' => 'bg-white',
        'gray' => 'bg-university-gray',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-gradient-to-b from-gray-50 to-white',
    };
    $textColor = $background === 'dark' ? 'text-white' : 'text-gray-900';
    $subtitleColor = $background === 'dark' ? 'text-gray-300' : 'text-gray-600';
@endphp

@if($title || $content || $subtitle)
<section class="py-16 {{ $bgClass }} content-block-section"
         style="--gradient-from: {{ $gradientFrom }}; --gradient-to: {{ $gradientTo }};">
    <div class="container mx-auto px-4">
        <div class="max-w-5xl mx-auto">
            @if($title)
                <div class="text-center mb-10">
                    @if($icon)
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
                             style="background: linear-gradient(135deg, {{ $gradientFrom }}, {{ $gradientTo }});">
                            <i class="{{ $icon }} text-white text-2xl"></i>
                        </div>
                    @endif
                    <h2 class="text-3xl md:text-4xl font-bold {{ $textColor }}">{{ $title }}</h2>
                    @if($subtitle)
                        <p class="text-lg {{ $subtitleColor }} mt-3">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            @if($content)
                <div class="prose max-w-none {{ $background === 'dark' ? 'prose-invert' : '' }}">
                    @cleanHtml($content)
                </div>
            @endif
        </div>
    </div>
</section>
@endif
