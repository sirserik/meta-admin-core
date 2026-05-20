@php
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp
@if(!empty($programs))
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif
        <div class="grid grid-cols-1 {{ $gridClass }} gap-6">
            @foreach($programs as $p)
                <a href="{{ $p['url'] }}" class="group bg-white rounded-2xl p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-university-red to-red-700 flex items-center justify-center mb-4">
                        <i class="{{ $p['icon'] }} text-white text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-university-red transition-colors">{{ $p['title'] }}</h3>
                    @if($p['description'])<p class="text-sm text-gray-600 mb-3 leading-relaxed">{{ $p['description'] }}</p>@endif
                    <div class="flex flex-wrap gap-2 text-xs text-gray-500">
                        @if($p['duration'])<span class="inline-flex items-center gap-1"><i class="far fa-clock"></i>{{ $p['duration'] }}</span>@endif
                        @if($p['level'])<span class="inline-flex items-center gap-1"><i class="fas fa-layer-group"></i>{{ $p['level'] }}</span>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
