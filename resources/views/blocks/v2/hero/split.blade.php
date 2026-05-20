{{-- Hero variant: split.
     Two-column: text on left, hero image on right. --}}
<section class="relative py-20 md:py-28"
         style="background: var(--theme-surface);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-center">

            <div>
                @if (!empty($badge))
                    <span class="inline-block text-xs font-semibold uppercase tracking-widest mb-4 px-3 py-1 rounded-full"
                          style="background: var(--theme-surface-2); color: var(--theme-primary);">
                        {{ $badge }}
                    </span>
                @endif

                <h1 class="text-4xl md:text-5xl font-bold mb-6 leading-tight"
                    style="color: var(--theme-ink);">
                    {{ $title }}
                </h1>

                @if (!empty($subtitle))
                    <p class="text-lg mb-8" style="color: var(--theme-ink-muted);">
                        {{ $subtitle }}
                    </p>
                @endif

                @if (!empty($buttons))
                    <div class="flex flex-col sm:flex-row gap-3">
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
            </div>

            <div class="relative">
                @if (!empty($backgroundImage))
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->exists($backgroundImage) ? asset('storage/' . ltrim($backgroundImage, '/')) : $backgroundImage }}"
                         alt="{{ $title }}"
                         class="w-full h-auto rounded-2xl shadow-2xl"
                         style="border-radius: var(--theme-radius-lg); box-shadow: var(--theme-shadow-lg);">
                @else
                    <div class="aspect-[4/3] rounded-2xl"
                         style="background: linear-gradient(135deg, var(--theme-primary), var(--theme-accent)); border-radius: var(--theme-radius-lg);">
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
