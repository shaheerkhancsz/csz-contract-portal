@php use App\Support\Settings; $primary = Settings::get('brand_primary'); @endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
    <tr>
        <td align="center" style="border-radius:6px;background:{{ $primary }};">
            <a href="{{ $url }}" style="display:inline-block;padding:11px 26px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:6px;">{{ $label }}</a>
        </td>
    </tr>
</table>
