{{-- Hero variant: centered.
     Light surface, centered content, no background image. --}}
<section class="relative py-24 md:py-32 text-center"
         style="background: var(--theme-surface-2);">
    <div class="max-w-3xl mx-auto px-4">
        @if (!empty($badge))
            <span class="inline-block text-xs font-semibold uppercase tracking-widest mb-4 px-3 py-1 rounded-full"
                  style="background: var(--theme-surface); color: var(--theme-primary);">
                {{ $badge }}
            </span>
        @endif

        <h1 class="text-4xl md:text-6xl font-bold mb-6"
            style="color: var(--theme-ink);">
            {{ $title }}
        </h1>

        @if (!empty($subtitle))
            <p class="text-lg md:text-xl mb-10 leading-relaxed"
               style="color: var(--theme-ink-muted);">
                {{ $subtitle }}
            </p>
        @endif

        @if (!empty($buttons))
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                @foreach ($buttons as $b)
                    <a href="{{ $b['url'] }}"
                       class="inline-flex items-center justify-center px-6 py-3 rounded-lg font-semibold transition"
                       @style([
                           'background: var(--theme-primary); color: var(--theme-on-primary);' => ($b['style'] === 'primary'),
                           'border: 2px solid var(--theme-border); color: var(--theme-ink);'    => ($b['style'] !== 'primary'),
                       ])>
                        {{ $b['text'] }}
                    </a>
                @endforeach
            </div>
        @endif

        @if (!empty($stats))
            <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-6 max-w-2xl mx-auto">
                @foreach ($stats as $s)
                    <div>
                        <div class="text-3xl md:text-4xl font-bold" style="color: var(--theme-primary);">{{ $s['number'] }}</div>
                        <div class="text-sm mt-1" style="color: var(--theme-ink-muted);">{{ $s['label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
