{{-- Links block v2 --}}
@php
    $gridClass = match ($columns) {
        2 => 'md:grid-cols-2',
        4 => 'md:grid-cols-2 lg:grid-cols-4',
        default => 'md:grid-cols-2 lg:grid-cols-3',
    };
@endphp

@if(!empty($links))
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif

        @if($layout === 'list')
            <ul class="max-w-3xl mx-auto divide-y divide-gray-200 bg-white rounded-2xl shadow-sm">
                @foreach($links as $link)
                    <li>
                        <a href="{{ $link['url'] }}"
                           @if($link['external']) target="_blank" rel="noopener" @endif
                           class="flex items-center gap-4 p-5 hover:bg-gray-50 transition-colors">
                            <i class="{{ $link['icon'] }} text-university-red text-xl w-8 text-center"></i>
                            <div class="flex-1">
                                <div class="font-semibold text-gray-900">{{ $link['title'] }}</div>
                                @if($link['description'])<div class="text-sm text-gray-600 mt-0.5">{{ $link['description'] }}</div>@endif
                            </div>
                            <i class="fas fa-{{ $link['external'] ? 'external-link-alt' : 'chevron-right' }} text-gray-400"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="grid grid-cols-1 {{ $gridClass }} gap-4">
                @foreach($links as $link)
                    <a href="{{ $link['url'] }}"
                       @if($link['external']) target="_blank" rel="noopener" @endif
                       class="group block p-6 bg-white rounded-2xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all border border-gray-100">
                        <div class="w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center mb-4 group-hover:bg-university-red transition-colors">
                            <i class="{{ $link['icon'] }} text-university-red group-hover:text-white text-lg"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900 group-hover:text-university-red transition-colors">{{ $link['title'] }}</h3>
                        @if($link['description'])<p class="text-sm text-gray-600 mt-1">{{ $link['description'] }}</p>@endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
