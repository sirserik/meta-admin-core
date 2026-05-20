{{-- AdmissionStep block v2 --}}
@php
    $accentClass = match ($color) {
        'gold' => 'from-university-gold to-yellow-500',
        'blue' => 'from-blue-500 to-blue-700',
        'green' => 'from-green-500 to-emerald-600',
        default => 'from-university-red to-red-700',
    };
@endphp

<div class="bg-white rounded-2xl p-6 border border-gray-200 hover:shadow-lg transition-shadow relative overflow-hidden">
    <div class="flex items-start gap-4">
        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br {{ $accentClass }} flex items-center justify-center shadow-md">
            <span class="text-white font-bold text-xl">{{ $step }}</span>
        </div>
        <div class="flex-1">
            @if($icon)
                <i class="{{ $icon }} text-gray-300 text-2xl float-right"></i>
            @endif
            <h3 class="text-lg font-bold text-gray-900 mb-2">{{ $title }}</h3>
            @if($content)
                <div class="text-sm text-gray-700 prose prose-sm max-w-none">
                    @cleanHtml($content)
                </div>
            @endif
        </div>
    </div>
</div>
