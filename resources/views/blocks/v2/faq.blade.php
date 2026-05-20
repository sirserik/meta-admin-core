{{-- FAQ block v2 — accordion --}}
@if(!empty($items))
<section class="py-16 bg-white">
    <div class="container mx-auto px-4 max-w-3xl">
        @if($title || $subtitle)
            <div class="text-center mb-10">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif

        <div class="space-y-3" x-data="{ open: null }">
            @foreach($items as $idx => $item)
                <div class="border border-gray-200 rounded-xl overflow-hidden" :class="open === {{ $idx }} ? 'shadow-md border-university-red' : 'hover:border-gray-300'">
                    <button type="button" @click="open = open === {{ $idx }} ? null : {{ $idx }}"
                            class="w-full text-left p-5 flex items-center justify-between gap-4">
                        <span class="font-semibold text-gray-900">{{ $item['question'] }}</span>
                        <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open === {{ $idx }} ? 'rotate-180 text-university-red' : ''"></i>
                    </button>
                    <div x-show="open === {{ $idx }}" x-collapse class="px-5 pb-5 pt-0">
                        <div class="prose prose-sm max-w-none text-gray-700">
                            @cleanHtml($item['answer'])
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
