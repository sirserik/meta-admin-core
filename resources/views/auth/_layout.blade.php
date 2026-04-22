<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? __('Восстановление доступа') }} — {{ config('admin-core.brand.name', 'Admin') }}</title>

    {{-- Minimal self-contained styles: the consumer's Vite bundle may not
         exist (fresh install, no npm run build yet) or may namespace
         classes we don't know about. Ship a tiny CSS baseline instead. --}}
    <style>
        :root { --brand: {{ config('admin-core.brand.color', '#C41E3A') }}; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; }
        body {
            font-family: ui-sans-serif, -apple-system, Inter, system-ui, Segoe UI, Roboto, sans-serif;
            color: #111827; background: #f9fafb;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { min-height: 100%; display: flex; align-items: center; justify-content: center; padding: 3rem 1.5rem; }
        .card { width: 100%; max-width: 28rem; }
        .badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 4rem; height: 4rem; border-radius: 1rem; margin: 0 auto 1rem;
            background: linear-gradient(135deg, var(--brand) 0%, #8B0000 100%);
            color: #fff; font-size: 1.5rem;
        }
        .title  { font-size: 1.5rem; font-weight: 700; text-align: center; margin: 0 0 .25rem; }
        .lead   { color: #4b5563; text-align: center; font-size: .875rem; margin: 0 0 2rem; }
        .field  { margin-bottom: 1.25rem; }
        .label  { display: block; font-size: .875rem; font-weight: 600; color: #374151; margin-bottom: .375rem; }
        .input  {
            width: 100%; padding: .75rem 1rem; border: 1px solid #d1d5db;
            border-radius: .75rem; background: #fff; color: #111827;
            font-size: 1rem; transition: box-shadow .15s, border-color .15s;
        }
        .input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand) 15%, transparent); }
        .btn {
            width: 100%; padding: .875rem 1rem; border: 0; border-radius: .75rem;
            background: linear-gradient(135deg, var(--brand) 0%, #8B0000 100%);
            color: #fff; font-weight: 600; font-size: 1rem; cursor: pointer;
            box-shadow: 0 10px 25px -10px color-mix(in srgb, var(--brand) 80%, transparent);
            transition: transform .05s;
        }
        .btn:active { transform: scale(.98); }
        .alert      { padding: .75rem; border-radius: .5rem; font-size: .875rem; margin-bottom: 1rem; }
        .alert-err  { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-ok   { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .foot       { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; text-align: center; }
        .foot a     { color: #6b7280; text-decoration: none; font-size: .875rem; }
        .foot a:hover { color: var(--brand); }
        .hint       { color: #6b7280; font-size: .75rem; margin-top: .375rem; }
        .mono       { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, monospace; letter-spacing: .15em; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div style="text-align:center">
                <div class="badge">{!! $icon ?? '&#128273;' !!}</div>
                <h2 class="title">{{ $title ?? __('Восстановление доступа') }}</h2>
                @if(!empty($lead))<p class="lead">{{ $lead }}</p>@endif
            </div>

            @if (session('status'))
                <div class="alert alert-ok">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-err">{{ $errors->first() }}</div>
            @endif

            {{ $slot }}

            <div class="foot">
                @php $back = \Illuminate\Support\Facades\Route::has('login') ? route('login') : '/'; @endphp
                <a href="{{ $back }}">&larr; {{ __('Вернуться ко входу') }}</a>
            </div>
        </div>
    </div>
</body>
</html>
