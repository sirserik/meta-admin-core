<!DOCTYPE html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Доступ к инструментам — {{ $brand }}</title>
<style>
 body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh;padding:16px}
 .box{background:#1e293b;border:1px solid #334155;border-radius:14px;padding:28px;max-width:380px;width:100%}
 h1{font-size:19px;margin:0 0 6px}.sub{color:#94a3b8;font-size:13.5px;margin:0 0 20px;line-height:1.5}
 label{display:block;font-size:13px;color:#94a3b8;margin-bottom:6px}
 input{width:100%;padding:12px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#e2e8f0;font-size:20px;letter-spacing:.3em;text-align:center}
 button{width:100%;margin-top:14px;cursor:pointer;border:0;border-radius:10px;padding:12px;font-size:15px;font-weight:600;background:#3b82f6;color:#fff}
 .err{background:rgba(239,68,68,.12);border:1px solid #ef4444;color:#fecaca;padding:10px 12px;border-radius:10px;margin-bottom:14px;font-size:13.5px}
 a.back{color:#94a3b8;font-size:13px;text-decoration:none;display:inline-block;margin-bottom:16px}
</style></head><body>
<div class="box">
 <a class="back" href="/{{ $prefix }}">← в админку</a>
 <h1>Доступ к серверным инструментам</h1>
 <p class="sub">Firewall и бэкапы защищены отдельным PIN-кодом. Введите PIN, чтобы продолжить.</p>
 @if ($errors->any())<div class="err">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
 <form method="POST" action="{{ route('admin-core.ops.unlock.verify') }}">@csrf
   <label>PIN-код</label>
   <input type="password" name="pin" inputmode="numeric" autocomplete="off" autofocus required>
   <button type="submit">Разблокировать</button>
 </form>
</div></body></html>
