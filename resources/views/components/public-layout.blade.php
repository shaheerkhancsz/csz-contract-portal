@props(['title' => null])
@php
    use App\Support\Settings;
    use Illuminate\Support\Facades\Storage;

    $s = Settings::all();
    $primary = $s['brand_primary'];
    $dark = $s['brand_dark'];
    $grey = $s['brand_grey'];
    $logo = ($s['logo_path'] ?? '') && Storage::disk('local')->exists($s['logo_path'])
        ? 'data:'.(Storage::disk('local')->mimeType($s['logo_path']) ?: 'image/png').';base64,'.base64_encode(Storage::disk('local')->get($s['logo_path']))
        : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $s['company_name'] }}</title>
    <style>
        :root { --primary: {{ $primary }}; --dark: {{ $dark }}; --grey: {{ $grey }}; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f4; color: var(--dark); font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; line-height: 1.55; }
        .wrap { max-width: 820px; margin: 0 auto; padding: 24px 16px 60px; }
        .brandbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 0 18px; }
        .brandbar img { height: 38px; }
        .brandbar .word b { color: var(--primary); } .brandbar .word span { color: var(--grey); }
        .card { background: #fff; border: 1px solid #e3e8eb; border-radius: 10px; padding: 26px; box-shadow: 0 1px 3px rgba(16,24,40,.06); }
        .card + .card { margin-top: 18px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        h2 { font-size: 17px; margin: 22px 0 8px; }
        p { margin: 0 0 12px; }
        .muted { color: var(--grey); font-size: 14px; }
        .summary { width: 100%; border-collapse: collapse; border: 1px solid #e3e8eb; border-radius: 8px; margin: 10px 0 18px; }
        .summary td { padding: 9px 13px; font-size: 14px; border-bottom: 1px solid #eef1f3; }
        .summary tr:last-child td { border-bottom: none; }
        .summary td:first-child { color: var(--grey); width: 38%; }
        .summary td:last-child { font-weight: 600; }
        table.list { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.list th, table.list td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #eef1f3; font-size: 14px; vertical-align: middle; }
        table.list th { color: var(--grey); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: rgba(100,188,233,.15); color: var(--dark); }
        .btn { display: inline-block; border: none; cursor: pointer; background: var(--primary); color: #fff; font-size: 14px; font-weight: 600; padding: 8px 16px; border-radius: 7px; text-decoration: none; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-ghost { background: #fff; color: var(--dark); border: 1px solid #cfd6db; }
        .btn-sm { padding: 6px 12px; font-size: 13px; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 14px 0 5px; }
        input[type=text], input[type=email] { width: 100%; padding: 11px 13px; font-size: 15px; border: 1px solid #cfd6db; border-radius: 7px; }
        input.code { letter-spacing: 10px; font-size: 24px; text-align: center; font-weight: 700; }
        .err { color: #b42318; font-size: 14px; margin: 8px 0 0; }
        .ok { color: #067647; font-size: 14px; margin: 8px 0 0; }
        .check { display: flex; align-items: flex-start; gap: 9px; margin: 16px 0; font-weight: 400; }
        .check input { margin-top: 3px; }
        iframe.doc { width: 100%; height: 520px; border: 1px solid #cfd6db; border-radius: 8px; background: #fff; }
        canvas.pad { border: 1px dashed #9aa7b1; border-radius: 8px; background: #fcfdfe; touch-action: none; width: 100%; max-width: 380px; height: 150px; display: block; }
        .foot { text-align: center; color: var(--grey); font-size: 12px; margin-top: 24px; }
    </style>
    @livewireStyles
</head>
<body>
    <div class="wrap">
        <div class="brandbar">
            <div>
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $s['company_name'] }}">
                @else
                    <span class="word" style="font-size:19px;font-weight:700;"><b>CREATIVE</b> <span>SOLUTION ZONE</span></span>
                @endif
            </div>
            @isset($actions)
                <div>{{ $actions }}</div>
            @endisset
        </div>

        {{ $slot }}

        <div class="foot">
            {{ $s['company_name'] }} &middot; {{ $s['company_website'] }}<br>
            Secure client area. Need help? Contact {{ $s['support_email'] }}.
        </div>
    </div>
    @livewireScripts
</body>
</html>
