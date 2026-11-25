@php use App\Support\Settings; $grey = Settings::get('brand_grey'); $dark = Settings::get('brand_dark'); @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;border:1px solid #e3e8eb;border-radius:6px;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="padding:8px 14px;font-size:13px;color:{{ $grey }};width:40%;border-bottom:{{ $loop->last ? 'none' : '1px solid #eef1f3' }};">{{ $label }}</td>
            <td style="padding:8px 14px;font-size:13px;color:{{ $dark }};font-weight:bold;border-bottom:{{ $loop->last ? 'none' : '1px solid #eef1f3' }};">{{ $value }}</td>
        </tr>
    @endforeach
</table>
