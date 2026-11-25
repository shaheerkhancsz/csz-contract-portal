@extends('emails.layout')

@section('content')
    @php use App\Services\ContractRenderer; $grey = \App\Support\Settings::get('brand_grey'); @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ \App\Support\Settings::get('brand_dark') }};">Your contract was sent back</h1>
    <p style="margin:0 0 14px;">{{ $returnedBy }} reviewed <strong>{{ $contract->agreement_no }}</strong> and sent it back to draft. It is editable again.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 4px;border-left:4px solid {{ \App\Support\Settings::get('brand_primary') }};background:#f7fafc;">
        <tr><td style="padding:12px 16px;">
            <div style="font-size:12px;color:{{ $grey }};margin-bottom:4px;">What needs to change</div>
            <div style="font-size:14px;">{{ $note }}</div>
        </td></tr>
    </table>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
    ]])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open and edit'])
@endsection
