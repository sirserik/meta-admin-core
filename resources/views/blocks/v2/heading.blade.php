{{-- Heading block v2 --}}
@php
    $alignClass = match ($alignment) {
        'left' => 'text-left',
        'right' => 'text-right',
        default => 'text-center',
    };
    $titleSize = match ($size) {
        'small' => 'text-2xl md:text-3xl',
        'large' => 'text-4xl md:text-5xl lg:text-6xl',
        default => 'text-3xl md:text-4xl',
    };
@endphp

@if($title || $subtitle)
<div class="py-10 {{ $alignClass }}">
    <div class="container mx-auto px-4 max-w-4xl">
        @if($icon)
            <div class="mb-4 @if($alignment === 'center') flex justify-center @endif">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-red-50">
                    <i class="{{ $icon }} text-university-red text-2xl"></i>
                </div>
            </div>
        @endif
        @if($title)
            <h2 class="font-bold text-gray-900 {{ $titleSize }}">{{ $title }}</h2>
        @endif
        @if($subtitle)
            <p class="text-lg text-gray-600 mt-3">{{ $subtitle }}</p>
        @endif
    </div>
</div>
@endif
