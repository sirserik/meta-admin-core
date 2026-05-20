{{-- Stats block v2 --}}
@php
    $bgClass = match ($background) {
        'gold' => 'bg-gradient-to-br from-university-gold via-yellow-500 to-orange-500 text-white',
        'dark' => 'bg-gradient-to-br from-gray-800 via-gray-900 to-black text-white',
        'white' => 'bg-white',
        'gray' => 'bg-university-gray',
        default => 'bg-gradient-to-br from-university-red via-red-600 to-red-700 text-white',
    };
    $isDark = in_array($background, ['red', 'gold', 'dark']);
    $accentClass = $isDark ? 'text-university-gold' : 'text-university-red';
    $labelClass = $isDark ? 'text-white/80' : 'text-gray-600';
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-3',
        5 => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
        6 => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
        default => 'grid-cols-2 md:grid-cols-4',
    };
@endphp

@if(!empty($stats))
<section class="{{ $bgClass }} py-16">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg {{ $labelClass }}">{{ $subtitle }}</p>@endif
            </div>
        @endif

        <div class="grid {{ $gridClass }} gap-6">
            @foreach($stats as $stat)
                <div class="text-center">
                    @if(!empty($stat['icon']))
                        <i class="{{ $stat['icon'] }} {{ $accentClass }} text-3xl mb-3"></i>
                    @endif
                    <div class="text-4xl md:text-5xl font-bold {{ $accentClass }}">{{ $stat['value'] }}</div>
                    <div class="text-sm md:text-base {{ $labelClass }} mt-2">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
