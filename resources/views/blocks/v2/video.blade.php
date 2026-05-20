@if($embedUrl)
<section class="py-16 bg-white">
    <div class="container mx-auto px-4 max-w-4xl">
        @if($title || $description)
            <div class="text-center mb-8">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($description)<p class="text-lg text-gray-600">{{ $description }}</p>@endif
            </div>
        @endif
        <div class="relative aspect-video rounded-2xl overflow-hidden shadow-xl">
            @if($source === 'direct')
                <video src="{{ $embedUrl }}" controls class="w-full h-full"></video>
            @else
                <iframe src="{{ $embedUrl }}" class="absolute inset-0 w-full h-full" frameborder="0" allowfullscreen loading="lazy"></iframe>
            @endif
        </div>
    </div>
</section>
@endif
