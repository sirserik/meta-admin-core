@php
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        3 => 'md:grid-cols-3',
        default => 'md:grid-cols-2 lg:grid-cols-4',
    };
    $resolveImg = fn ($u) => $u ? (str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/')) : '/assets/img/avatar-placeholder.png';
@endphp
@if(!empty($members))
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif
        <div class="grid grid-cols-2 {{ $gridClass }} gap-6">
            @foreach($members as $m)
                <div class="text-center">
                    <img src="{{ $resolveImg($m['photo']) }}" alt="{{ $m['name'] }}" loading="lazy"
                         class="w-32 h-32 md:w-40 md:h-40 rounded-full object-cover mx-auto mb-4 shadow-md">
                    <h4 class="font-semibold text-gray-900">{{ $m['name'] }}</h4>
                    @if($m['position'])<p class="text-sm text-gray-600">{{ $m['position'] }}</p>@endif
                    @if($m['email'])<p class="text-xs text-university-red mt-1"><a href="mailto:{{ $m['email'] }}">{{ $m['email'] }}</a></p>@endif
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
