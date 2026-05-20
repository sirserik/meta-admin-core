{{-- Document group block v2 — PDF/DOC с группировкой по категории/году/без --}}
@php
    $resolveUrl = fn ($u) => str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/');
    $iconFor = fn ($ext) => match (strtolower($ext)) {
        'pdf' => 'fa-file-pdf text-red-500',
        'doc', 'docx' => 'fa-file-word text-blue-500',
        'xls', 'xlsx' => 'fa-file-excel text-emerald-500',
        'ppt', 'pptx' => 'fa-file-powerpoint text-orange-500',
        'zip', 'rar' => 'fa-file-archive text-amber-500',
        'jpg','jpeg','png','webp' => 'fa-file-image text-violet-500',
        default => 'fa-file text-gray-500',
    };
    $tabsId = 'docgrp-' . substr(md5(($title ?? '') . $totalCount . microtime()), 0, 6);
    $groupKeys = $groups->keys()->all();
    $firstKey = $groupKeys[0] ?? '';
@endphp

@if($totalCount > 0)
<section class="py-12">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-8 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif

        @if($layout === 'tabs' && $groupBy !== 'none')
            <div x-data="{ active: @js($firstKey) }" class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-wrap gap-1 border-b border-gray-200 bg-gray-50 px-3 pt-3" role="tablist">
                    @foreach($groupKeys as $key)
                        <button type="button" role="tab"
                                @click="active = @js($key)"
                                :class="active === @js($key) ? 'bg-white border-gray-200 border-b-white text-university-red' : 'border-transparent text-gray-600 hover:text-gray-900'"
                                class="px-4 py-2 -mb-px border border-b-0 rounded-t-lg text-sm font-semibold transition-colors">
                            {{ $key ?: 'Без категории' }}
                            <span class="ml-1 inline-flex items-center justify-center text-xs font-medium rounded-full bg-gray-200 text-gray-700 px-2 py-0.5">{{ $groups[$key]->count() }}</span>
                        </button>
                    @endforeach
                </div>

                @foreach($groupKeys as $key)
                    <div x-show="active === @js($key)" x-cloak role="tabpanel" class="p-4 md:p-6">
                        <ul class="divide-y divide-gray-100">
                            @foreach($groups[$key] as $doc)
                                <li class="py-3 flex items-start gap-3">
                                    <i class="fas {{ $iconFor($doc['ext']) }} text-2xl pt-1"></i>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate">{{ $doc['title'] }}</div>
                                        @if($doc['description'])
                                            <div class="text-sm text-gray-500 line-clamp-2">{{ $doc['description'] }}</div>
                                        @endif
                                        <div class="mt-0.5 text-xs text-gray-400 flex flex-wrap gap-2">
                                            @if($doc['date'])<span><i class="far fa-calendar mr-1"></i>{{ \Carbon\Carbon::parse($doc['date'])->isoFormat('D MMMM YYYY') }}</span>@endif
                                            @if($doc['size'])<span>{{ $doc['size'] }}</span>@endif
                                            <span class="uppercase tracking-wider">{{ $doc['ext'] }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ $resolveUrl($doc['file']) }}" target="_blank" rel="noopener"
                                       class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-university-red px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                                        <i class="fas fa-external-link-alt"></i>
                                        <span class="hidden sm:inline">Открыть</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @elseif($layout === 'accordion' && $groupBy !== 'none')
            <div x-data="{ open: @js($firstKey) }" class="space-y-3">
                @foreach($groupKeys as $key)
                    <div class="rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm">
                        <button type="button" @click="open = open === @js($key) ? null : @js($key)"
                                class="w-full flex items-center justify-between px-5 py-4 text-left">
                            <span class="font-semibold text-gray-900">{{ $key ?: 'Без категории' }} <span class="ml-2 text-sm font-normal text-gray-500">({{ $groups[$key]->count() }})</span></span>
                            <i class="fas fa-chevron-down transition-transform" :class="open === @js($key) ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === @js($key)" x-collapse class="px-5 pb-5">
                            <ul class="divide-y divide-gray-100">
                                @foreach($groups[$key] as $doc)
                                    <li class="py-3 flex items-start gap-3">
                                        <i class="fas {{ $iconFor($doc['ext']) }} text-xl pt-1"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900">{{ $doc['title'] }}</div>
                                            @if($doc['description'])<div class="text-sm text-gray-500">{{ $doc['description'] }}</div>@endif
                                        </div>
                                        <a href="{{ $resolveUrl($doc['file']) }}" target="_blank" rel="noopener"
                                           class="text-university-red hover:underline text-sm font-semibold whitespace-nowrap">Открыть</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- list --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @foreach($groups as $key => $items)
                    @if($groupBy !== 'none' && $key !== '')
                        <div class="px-5 py-3 bg-gray-50 text-sm font-semibold text-gray-700 uppercase tracking-wide">{{ $key }}</div>
                    @endif
                    @foreach($items as $doc)
                        <div class="px-5 py-3 flex items-start gap-3">
                            <i class="fas {{ $iconFor($doc['ext']) }} text-xl pt-1"></i>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-900">{{ $doc['title'] }}</div>
                                @if($doc['description'])<div class="text-sm text-gray-500">{{ $doc['description'] }}</div>@endif
                            </div>
                            <a href="{{ $resolveUrl($doc['file']) }}" target="_blank" rel="noopener"
                               class="text-university-red hover:underline text-sm font-semibold">Открыть</a>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif
