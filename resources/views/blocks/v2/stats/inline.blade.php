{{-- Stats variant: inline.
     Horizontal row, no coloured background, divider between items. --}}
<section class="py-16" style="background: var(--theme-surface);">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if (!empty($title))
            <h2 class="text-3xl font-bold text-center mb-12" style="color: var(--theme-ink);">
                {{ $title }}
            </h2>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-8 divide-x"
             style="--tw-divide-opacity: 1; border-color: var(--theme-border);">
            @foreach ($stats as $i => $s)
                <div class="flex flex-col items-center text-center px-6 {{ $i === 0 ? '' : 'border-l' }}"
                     style="border-color: var(--theme-border);">
                    @if (!empty($s['icon']))
                        <i class="{{ $s['icon'] }} text-2xl mb-2" style="color: var(--theme-primary);"></i>
                    @endif
                    <div class="text-3xl md:text-4xl font-bold" style="color: var(--theme-primary);">
                        {{ $s['value'] }}
                    </div>
                    <div class="text-sm mt-1" style="color: var(--theme-ink-muted);">
                        {{ $s['label'] }}
                    </div>
                </div>
            @endforeach
        </div>

        @if (!empty($subtitle))
            <p class="text-center mt-8 text-sm" style="color: var(--theme-ink-muted);">
                {{ $subtitle }}
            </p>
        @endif
    </div>
</section>
