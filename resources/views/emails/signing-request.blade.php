@extends('emails.layout')

@section('content')
    @php use App\Services\ContractRenderer; use App\Support\Settings; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ Settings::get('brand_dark') }};">You have a contract to sign</h1>
    <p style="margin:0 0 14px;">{{ Settings::get('company_name') }} has prepared <strong>{{ $contract->agreement_no }}</strong> for your signature. Please review it and sign online — it only takes a minute.</p>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
        'Fee' => ContractRenderer::money($contract->fee, $contract->currency).' '.($contract->billing_cycle?->per() ?? ''),
    ]])

    <p style="margin:0 0 4px;">When you open the link, we will email you a one-time code to confirm it is you before you sign.</p>

    @include('emails.partials.button', ['url' => $url, 'label' => 'Review and sign'])

    @if ($expiresAt)
        <p style="font-size:13px;color:{{ Settings::get('brand_grey') }};margin:0;">This link expires on {{ $expiresAt->format('j F Y') }}. If it expires, contact us and we will send a new one.</p>
    @endif
@endsection
