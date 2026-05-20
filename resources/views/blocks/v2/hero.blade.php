{{--
    Hero block (v2) — рендерится через App\Blocks\Definitions\HeroBlock::render().
    Все переменные уже локализованы и валидированы registry.

    Пропсы:
      $badge, $title, $subtitle : string|null (локализованы)
      $background : 'gradient'|'solid'|'image'
      $backgroundImage : string|null — путь в storage
      $buttons : [['text' => string, 'url' => string, 'style' => 'primary'|'outline']]
      $stats   : [['number' => string, 'label' => string]]
      $slides  : [['image' => string, 'alt' => string]]
--}}
@php
    $hasImage = $background === 'image' && !empty($backgroundImage);
    $bgClass = match ($background) {
        'solid' => 'bg-university-red',
        'image' => '',
        default => 'bg-gradient-to-br from-university-red via-red-600 to-red-700',
    };
    $bgStyle = $hasImage
        ? 'background-image:url(\'' . e(media_url($backgroundImage)) . '\');background-size:cover;background-position:center;'
        : '';
@endphp

<section class="relative overflow-hidden {{ $bgClass }} min-h-[500px] flex items-center"
         style="{{ $bgStyle }}">
    @if($hasImage)
        <div class="absolute inset-0 bg-black/40"></div>
    @endif

    <div class="relative container mx-auto px-4 py-20 text-white">
        @if($badge)
            <div class="inline-block px-4 py-1.5 mb-6 bg-white/10 backdrop-blur-sm border border-white/20 rounded-full text-sm font-medium">
                {{ $badge }}
            </div>
        @endif

        @if($title)
            <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 leading-tight">
                {!! nl2br(e($title)) !!}
            </h1>
        @endif

        @if($subtitle)
            <p class="text-lg md:text-xl text-white/90 max-w-3xl mb-8 leading-relaxed">
                {!! nl2br(e($subtitle)) !!}
            </p>
        @endif

        @if(!empty($buttons))
            <div class="flex flex-wrap gap-4 mb-12">
                @foreach($buttons as $btn)
                    @php
                        $primary = ($btn['style'] ?? 'primary') === 'primary';
                        $btnClass = $primary
                            ? 'bg-university-gold hover:bg-yellow-500 text-university-dark'
                            : 'bg-white/10 border border-white/30 hover:bg-white/20 text-white';
                    @endphp
                    <a href="{{ $btn['url'] }}" class="inline-flex items-center px-6 py-3 rounded-lg font-semibold transition-colors {{ $btnClass }}">
                        {{ $btn['text'] }}
                    </a>
                @endforeach
            </div>
        @endif

        @if(!empty($stats))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 max-w-4xl">
                @foreach($stats as $stat)
                    <div>
                        <div class="text-3xl md:text-4xl font-bold text-university-gold">{{ $stat['number'] }}</div>
                        <div class="text-sm text-white/80 mt-1">{{ $stat['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
