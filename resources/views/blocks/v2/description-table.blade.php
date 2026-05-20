{{-- Description Table block v2 — таблица «описание + ссылка» --}}
@php
    $resolveUrl = fn ($u) => str_starts_with($u, '/') || str_starts_with($u, 'http') ? $u : '/storage/' . ltrim($u, '/');
    $iconFor = fn ($t) => match ($t) {
        'video' => 'fa-video',
        'image' => 'fa-image',
        'external' => 'fa-arrow-up-right-from-square',
        default => 'fa-file-pdf',
    };
@endphp

@if($rows->isNotEmpty())
<section class="py-12">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-8 max-w-3xl mx-auto">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-sm bg-white">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            @if($showIndex)<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 w-12">№</th>@endif
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Описание</th>
                            @if($showDate)<th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 hidden md:table-cell whitespace-nowrap">Дата</th>@endif
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 whitespace-nowrap">Ссылка</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $i => $row)
                            <tr class="@if($striped && $i % 2) bg-gray-50/40 @endif hover:bg-gray-50 transition-colors">
                                @if($showIndex)<td class="px-4 py-3 text-sm text-gray-500 align-top">{{ $i + 1 }}</td>@endif
                                <td class="px-4 py-3 text-sm text-gray-900 align-top">
                                    <div class="prose prose-sm max-w-none">{!! $row['description'] !!}</div>
                                </td>
                                @if($showDate)
                                    <td class="px-4 py-3 text-sm text-gray-500 align-top hidden md:table-cell whitespace-nowrap">
                                        @if($row['date']){{ \Carbon\Carbon::parse($row['date'])->isoFormat('D MMM YYYY') }}@endif
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-center align-middle whitespace-nowrap">
                                    @if($row['link_url'])
                                        <a href="{{ $resolveUrl($row['link_url']) }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-2 rounded-lg bg-university-red px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors whitespace-nowrap shadow-sm hover:shadow">
                                            <i class="fas {{ $iconFor($row['link_type']) }}"></i>
                                            <span>{{ $row['link_label'] }}</span>
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-gray-400 italic"><i class="fas fa-clock"></i> скоро</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endif
