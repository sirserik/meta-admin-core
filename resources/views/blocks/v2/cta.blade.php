{{-- CTA block v2 --}}
@php
    $bgClass = match ($background) {
        'gold' => 'bg-gradient-to-br from-university-gold via-yellow-500 to-orange-500',
        'blue' => 'bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800',
        'dark' => 'bg-gradient-to-br from-gray-800 via-gray-900 to-black',
        'gray' => 'bg-university-gray',
        'white' => 'bg-white',
        default => 'bg-gradient-to-br from-university-red via-red-600 to-red-700',
    };
    $isLightBg = in_array($background, ['gray', 'white']);
    $textColor = $isLightBg ? 'text-university-dark' : 'text-white';
    $subtitleColor = $isLightBg ? 'text-gray-600' : 'text-white/80';
@endphp

<section class="relative {{ $bgClass }} py-20 md:py-28 overflow-hidden">
    <div class="container mx-auto px-6 relative z-10">

        @if($style === 'split')
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    @if($badge)
                        <span class="inline-block bg-white/20 {{ $textColor }} px-4 py-1 rounded-full text-sm font-semibold mb-4">{{ $badge }}</span>
                    @endif
                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 {{ $textColor }}">{{ $title }}</h2>
                    @if($subtitle)<p class="text-xl {{ $subtitleColor }}">{{ $subtitle }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-4 lg:justify-end">
                    @foreach($buttons as $btn)
                        @php
                            $primary = ($btn['style'] ?? 'primary') === 'primary';
                            $btnClass = $primary
                                ? ($isLightBg ? 'bg-university-red hover:bg-red-700 text-white' : 'bg-white text-university-red hover:bg-gray-100')
                                : ($isLightBg ? 'border-2 border-university-red text-university-red hover:bg-university-red hover:text-white' : 'border-2 border-white text-white hover:bg-white hover:text-university-red');
                        @endphp
                        <a href="{{ $btn['url'] }}" class="inline-flex items-center {{ $btnClass }} px-8 py-4 rounded-xl font-semibold transition-all text-lg">{{ $btn['text'] }}</a>
                    @endforeach
                </div>
            </div>

        @else
            {{-- centered / minimal --}}
            <div class="text-center max-w-4xl mx-auto">
                @if($badge)
                    <span class="inline-block bg-white/20 {{ $textColor }} px-4 py-1 rounded-full text-sm font-semibold mb-4">{{ $badge }}</span>
                @endif
                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 {{ $textColor }}">{{ $title }}</h2>
                @if($subtitle)<p class="text-lg md:text-xl {{ $subtitleColor }} mb-8">{{ $subtitle }}</p>@endif
                @if(!empty($buttons))
                    <div class="flex flex-wrap gap-4 justify-center">
                        @foreach($buttons as $btn)
                            @php
                                $primary = ($btn['style'] ?? 'primary') === 'primary';
                                $btnClass = $primary
                                    ? ($isLightBg ? 'bg-university-red hover:bg-red-700 text-white' : 'bg-white text-university-red hover:bg-gray-100')
                                    : ($isLightBg ? 'border-2 border-university-red text-university-red hover:bg-university-red hover:text-white' : 'border-2 border-white text-white hover:bg-white hover:text-university-red');
                            @endphp
                            <a href="{{ $btn['url'] }}" class="inline-flex items-center {{ $btnClass }} px-8 py-4 rounded-xl font-semibold transition-all text-lg">{{ $btn['text'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </div>
</section>
