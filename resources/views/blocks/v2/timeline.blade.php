@if(!empty($events))
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        @if($title || $subtitle)
            <div class="text-center mb-10">
                @if($title)<h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">{{ $title }}</h2>@endif
                @if($subtitle)<p class="text-lg text-gray-600">{{ $subtitle }}</p>@endif
            </div>
        @endif
        <div class="relative max-w-4xl mx-auto">
            <div class="absolute top-0 bottom-0 left-4 md:left-1/2 md:-translate-x-px w-0.5 bg-university-red/20"></div>
            <div class="space-y-8">
                @foreach($events as $i => $event)
                    <div class="relative pl-12 md:pl-0 @if($i % 2 == 0) md:pr-1/2 md:text-right @else md:pl-1/2 md:text-left @endif">
                        <div class="absolute left-0 md:left-1/2 md:-translate-x-1/2 w-8 h-8 rounded-full bg-university-red flex items-center justify-center text-white z-10">
                            <i class="{{ $event['icon'] }} text-xs"></i>
                        </div>
                        <div class="bg-white rounded-xl p-5 shadow-sm @if($i % 2 == 0) md:mr-8 @else md:ml-8 @endif">
                            <span class="inline-block text-university-red font-bold text-lg">{{ $event['year'] }}</span>
                            <h4 class="font-semibold text-gray-900 mt-1">{{ $event['title'] }}</h4>
                            @if($event['description'])<p class="text-sm text-gray-600 mt-2">{{ $event['description'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
