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
    <title>{{ $subject ?? $s['company_name'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f4f6;color:{{ $dark }};font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f4f6;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e3e8eb;">
                    {{-- Header --}}
                    <tr>
                        <td style="padding:20px 28px;border-bottom:3px solid {{ $primary }};">
                            @if ($logo)
                                <img src="{{ $logo }}" alt="{{ $s['company_name'] }}" style="height:40px;">
                            @else
                                <span style="font-size:19px;font-weight:bold;color:{{ $primary }};">CREATIVE </span><span style="font-size:19px;font-weight:bold;color:{{ $grey }};">SOLUTION ZONE</span>
                            @endif
                        </td>
                    </tr>
                    {{-- Body --}}
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 28px;background:#fafbfc;border-top:1px solid #e3e8eb;font-size:12px;color:{{ $grey }};">
                            <strong style="color:{{ $dark }};">{{ $s['company_name'] }}</strong><br>
                            {{ $s['company_address'] }}<br>
                            {{ collect([$s['company_phone'], $s['company_email'], $s['company_website']])->filter()->implode('  &middot;  ') }}
                            <div style="margin-top:10px;color:#9aa0a6;">This is an automated message from the {{ $s['company_name'] }} Contract Portal. Please do not reply directly to this email.</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
