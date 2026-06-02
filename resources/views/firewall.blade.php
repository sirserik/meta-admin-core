<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SSH-доступ (firewall) — {{ $brand }}</title>
    <style>
        :root { --bg:#0f172a; --card:#1e293b; --line:#334155; --txt:#e2e8f0; --mut:#94a3b8; --acc:#ef4444; --ok:#22c55e; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--txt); padding:32px 16px; }
        .wrap { max-width: 760px; margin:0 auto; }
        h1 { font-size:22px; margin:0 0 4px; }
        .sub { color:var(--mut); font-size:14px; margin:0 0 24px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:20px; margin-bottom:20px; }
        .flash { background:rgba(34,197,94,.12); border:1px solid var(--ok); color:#bbf7d0; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; }
        .err { background:rgba(239,68,68,.12); border:1px solid var(--acc); color:#fecaca; padding:12px 14px; border-radius:10px; margin-bottom:18px; font-size:14px; }
        label { display:block; font-size:13px; color:var(--mut); margin-bottom:6px; }
        input[type=text] { width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--line); background:#0b1220; color:var(--txt); font-size:14px; }
        .row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
        .row > div { flex:1; min-width:180px; }
        button { cursor:pointer; border:0; border-radius:8px; padding:10px 16px; font-size:14px; font-weight:600; }
        .btn-add { background:#3b82f6; color:#fff; }
        .btn-del { background:transparent; color:var(--acc); border:1px solid var(--acc); padding:6px 12px; font-size:13px; }
        .btn-ghost { background:#0b1220; color:var(--mut); border:1px solid var(--line); }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--line); }
        th { color:var(--mut); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
        code { background:#0b1220; padding:2px 6px; border-radius:6px; }
        .hint { color:var(--mut); font-size:13px; line-height:1.5; }
        .you { color:var(--ok); font-size:12px; }
        a.back { color:var(--mut); font-size:13px; text-decoration:none; }
    </style>
</head>
<body>
<div class="wrap">
    <a class="back" href="/{{ $prefix }}">← в админку</a>
    <h1>SSH-доступ к серверу</h1>
    <p class="sub">Список IP-адресов, которым разрешён вход по SSH (порт 22). Меняется здесь, применяется к firewall автоматически в течение минуты — поэтому при смене вашего адреса доступ можно вернуть прямо отсюда, не теряя сервер.</p>

    @if (session('status'))
        <div class="flash">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="err">
            @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin-core.firewall.store') }}">
            @csrf
            <div class="row">
                <div>
                    <label>IP-адрес или подсеть</label>
                    <input type="text" name="ip_address" placeholder="203.0.113.7 или 203.0.113.0/24" value="{{ old('ip_address') }}" required>
                </div>
                <div>
                    <label>Метка (необязательно)</label>
                    <input type="text" name="label" placeholder="дом / офис / телефон" value="{{ old('label') }}">
                </div>
                <div style="flex:0 0 auto;">
                    <button type="submit" class="btn-add">Добавить</button>
                </div>
            </div>
        </form>
        <p class="hint" style="margin-top:14px;">
            Ваш текущий адрес: <code>{{ $currentIp }}</code> <span class="you">(с него вы сейчас зашли в админку)</span>
        </p>
        <form method="POST" action="{{ route('admin-core.firewall.store') }}" style="margin-top:8px;">
            @csrf
            <input type="hidden" name="ip_address" value="{{ $currentIp }}">
            <input type="hidden" name="label" value="добавлено из админки">
            <button type="submit" class="btn-ghost">+ Разрешить SSH с моего текущего IP</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr><th>IP / подсеть</th><th>Метка</th><th>Добавлен</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    <tr>
                        <td><code>{{ $rule->ip_address }}</code></td>
                        <td>{{ $rule->label ?: '—' }}</td>
                        <td class="hint">{{ $rule->created_at?->format('d.m.Y H:i') }}</td>
                        <td style="text-align:right;">
                            <form method="POST" action="{{ route('admin-core.firewall.destroy', $rule) }}" onsubmit="return confirm('Удалить {{ $rule->ip_address }}? Доступ по SSH с этого адреса закроется.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-del">Удалить</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="hint">Список пуст. Аварийный адрес всё равно зашит в системный скрипт — без SSH вы не останетесь.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="hint">
        Веб-доступ к сайту (80/443) открыт всем независимо от этого списка — он управляет только SSH.
        Аварийный путь, если вдруг закрыли себе всё: веб-консоль (VNC) в панели хостинга.
    </p>
</div>
</body>
</html>
