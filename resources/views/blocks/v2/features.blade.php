{{-- Features block v2 --}}
@php
    $bgClass = match ($background) {
        'white' => 'bg-white',
        'gradient' => 'bg-gradient-to-b from-gray-50 to-gray-100',
        default => 'bg-university-gray',
    };
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp

@if(!empty($features))
<section class="{{ $bgClass }} py-20"
         style="--gradient-from: {{ $gradientFrom }}; --gradient-to: {{ $gradientTo }};">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-14 max-w-3xl mx-auto">
                @if($title)
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="text-lg text-gray-600">{{ $subtitle }}</p>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 {{ $gridClass }} gap-6">
            @foreach($features as $feature)
                <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm hover:shadow-lg transition-shadow border border-gray-100">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center mb-4"
                         style="background: linear-gradient(135deg, {{ $gradientFrom }}, {{ $gradientTo }});">
                        <i class="{{ $feature['icon'] }} text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $feature['title'] }}</h3>
                    @if($feature['description'])
                        <p class="text-gray-600 leading-relaxed">{{ $feature['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
