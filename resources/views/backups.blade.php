<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Бэкапы — {{ $brand }}</title>
    <style>
        :root { --bg:#0f172a; --card:#1e293b; --line:#334155; --txt:#e2e8f0; --mut:#94a3b8; --acc:#ef4444; --ok:#22c55e; --warn:#f59e0b; --blue:#3b82f6; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--txt); padding:32px 16px; }
        .wrap { max-width:920px; margin:0 auto; }
        h1 { font-size:22px; margin:0 0 4px; } h2 { font-size:16px; margin:0 0 14px; }
        .sub { color:var(--mut); font-size:14px; margin:0 0 24px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:20px; }
        .flash { background:rgba(34,197,94,.12); border:1px solid var(--ok); color:#bbf7d0; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; }
        .err { background:rgba(239,68,68,.12); border:1px solid var(--acc); color:#fecaca; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; }
        .st { padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; border:1px solid var(--line); background:#0b1220; }
        .st.ok { border-color:var(--ok); } .st.error { border-color:var(--acc); } .st.running { border-color:var(--warn); }
        .badge { display:inline-block; font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; text-transform:uppercase; letter-spacing:.04em; }
        .badge.ok { background:rgba(34,197,94,.2); color:#86efac; } .badge.error { background:rgba(239,68,68,.2); color:#fca5a5; } .badge.running { background:rgba(245,158,11,.2); color:#fcd34d; }
        button { cursor:pointer; border:0; border-radius:8px; padding:9px 14px; font-size:13.5px; font-weight:600; }
        .btn-blue { background:var(--blue); color:#fff; } .btn-ghost { background:#0b1220; color:var(--mut); border:1px solid var(--line); }
        .btn-restore { background:var(--warn); color:#1a1205; padding:6px 12px; font-size:13px; }
        .btn-dl { background:transparent; color:var(--blue); border:1px solid var(--blue); padding:6px 12px; font-size:13px; }
        .btn-del { background:transparent; color:var(--acc); border:1px solid var(--acc); padding:6px 12px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:13.5px; }
        th,td { text-align:left; padding:9px 8px; border-bottom:1px solid var(--line); vertical-align:middle; }
        th { color:var(--mut); font-weight:600; font-size:11.5px; text-transform:uppercase; letter-spacing:.04em; }
        code { background:#0b1220; padding:2px 6px; border-radius:6px; font-size:12.5px; }
        .hint { color:var(--mut); font-size:13px; line-height:1.55; }
        .actions { display:flex; gap:8px; justify-content:flex-end; flex-wrap:wrap; }
        .toolbar { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .disk { color:var(--mut); font-size:13px; }
        a.back { color:var(--mut); font-size:13px; text-decoration:none; }
        form.inline { display:inline; margin:0; }
    </style>
</head>
@php
    $fmt = function ($b) { $u=['Б','КБ','МБ','ГБ']; $i=0; $b=(float)$b; while ($b>=1024 && $i<3){ $b/=1024; $i++; } return round($b, $i?1:0).' '.$u[$i]; };
@endphp
<body>
<div class="wrap">
    <a class="back" href="/{{ $prefix }}">← в админку</a>
    <h1>Бэкапы и восстановление</h1>
    <p class="sub">Резервные копии БД и файлов/кода. Здесь можно создать копию вручную, скачать, удалить и восстановить БД из любой копии. Привилегированные операции выполняет root-агент по cron — веб-процесс лишь ставит задачу в очередь.</p>

    @if (session('status')) <div class="flash">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="err">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div> @endif

    @if ($status)
        <div class="st {{ $status['state'] ?? '' }}">
            Последняя операция: <span class="badge {{ $status['state'] ?? '' }}">{{ $status['state'] ?? '?' }}</span>
            {{ $status['action'] ?? '' }} {{ ($status['file'] ?? '') ? '· '.$status['file'] : '' }}<br>
            <span class="hint">{{ $status['message'] ?? '' }} — {{ $status['at'] ?? '' }}</span>
        </div>
    @endif
    @if ($pending > 0) <div class="st running">В очереди задач: {{ $pending }} (выполнятся в течение минуты, обновите страницу).</div> @endif

    <div class="card">
        <div class="toolbar">
            <form class="inline" method="POST" action="{{ route('admin-core.backups.backup') }}">@csrf
                <input type="hidden" name="type" value="db"><button class="btn-blue" type="submit">Создать бэкап БД сейчас</button>
            </form>
            <form class="inline" method="POST" action="{{ route('admin-core.backups.backup') }}">@csrf
                <input type="hidden" name="type" value="files"><button class="btn-ghost" type="submit">Создать бэкап файлов сейчас</button>
            </form>
            <span class="disk">Диск: свободно {{ $fmt($diskFree) }} из {{ $fmt($diskTotal) }}</span>
        </div>
    </div>

    <div class="card">
        <h2>Копии БД ({{ count($dbBackups) }})</h2>
        <table>
            <thead><tr><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr></thead>
            <tbody>
            @forelse ($dbBackups as $b)
                <tr>
                    <td><code>{{ $b['name'] }}</code></td>
                    <td>{{ $fmt($b['size']) }}</td>
                    <td class="hint">{{ date('d.m.Y H:i', $b['mtime']) }}</td>
                    <td><div class="actions">
                        <form class="inline" method="POST" action="{{ route('admin-core.backups.restore') }}"
                              onsubmit="return confirm('ВОССТАНОВИТЬ базу из {{ $b['name'] }}?\n\nТекущие данные будут заменены. Перед этим автоматически создаётся защитная копия. Продолжить?');">
                            @csrf<input type="hidden" name="file" value="{{ $b['name'] }}">
                            <button class="btn-restore" type="submit">Восстановить</button>
                        </form>
                        <a class="btn-dl" href="{{ route('admin-core.backups.download', ['type'=>'db','file'=>$b['name']]) }}">Скачать</a>
                        <form class="inline" method="POST" action="{{ route('admin-core.backups.delete') }}" onsubmit="return confirm('Удалить {{ $b['name'] }}?');">
                            @csrf @method('DELETE')<input type="hidden" name="type" value="db"><input type="hidden" name="file" value="{{ $b['name'] }}">
                            <button class="btn-del" type="submit">Удалить</button>
                        </form>
                    </div></td>
                </tr>
            @empty <tr><td colspan="4" class="hint">Копий пока нет.</td></tr> @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Копии файлов и кода ({{ count($fileBackups) }})</h2>
        <table>
            <thead><tr><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr></thead>
            <tbody>
            @forelse ($fileBackups as $b)
                <tr>
                    <td><code>{{ $b['name'] }}</code></td>
                    <td>{{ $fmt($b['size']) }}</td>
                    <td class="hint">{{ date('d.m.Y H:i', $b['mtime']) }}</td>
                    <td><div class="actions">
                        <a class="btn-dl" href="{{ route('admin-core.backups.download', ['type'=>'files','file'=>$b['name']]) }}">Скачать</a>
                        <form class="inline" method="POST" action="{{ route('admin-core.backups.delete') }}" onsubmit="return confirm('Удалить {{ $b['name'] }}?');">
                            @csrf @method('DELETE')<input type="hidden" name="type" value="files"><input type="hidden" name="file" value="{{ $b['name'] }}">
                            <button class="btn-del" type="submit">Удалить</button>
                        </form>
                    </div></td>
                </tr>
            @empty <tr><td colspan="4" class="hint">Копий пока нет.</td></tr> @endforelse
            </tbody>
        </table>
    </div>

    <p class="hint">
        Восстановление файлов/кода через веб не делается (риск перезаписать рабочие файлы) — скачайте архив и разверните вручную.
        Аварийный путь, если всё сломалось: распаковать <code>uploads-*.tar.gz</code> в каталог проекта вручную из консоли.
    </p>
</div>
</body>
</html>
