@php
    $resolveImg = fn ($u) => $u ? (str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/')) : null;
@endphp
@if(!empty($partners))
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10">
                <i class="{{ $icon }} text-3xl text-university-red mb-3"></i>
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600 max-w-2xl mx-auto">{{ $subtitle }}</p>@endif
            </div>
        @endif
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
            @foreach($partners as $p)
                <a @if(!empty($p['url'])) href="{{ $p['url'] }}" target="_blank" rel="noopener" @endif
                   class="group p-4 bg-gray-50 rounded-xl hover:shadow-md transition-all flex items-center justify-center @if(!empty($p['url'])) cursor-pointer @endif"
                   title="{{ $p['name'] }}">
                    @if(!empty($p['logo']))
                        <img src="{{ $resolveImg($p['logo']) }}" alt="{{ $p['name'] }}" loading="lazy"
                             class="max-h-16 object-contain opacity-70 group-hover:opacity-100 transition-opacity">
                    @else
                        <span class="text-sm text-gray-700 text-center">{{ $p['name'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif
