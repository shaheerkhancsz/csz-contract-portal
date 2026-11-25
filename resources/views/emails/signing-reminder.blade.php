@extends('emails.layout')

@section('content')
    @php use App\Services\ContractRenderer; use App\Support\Settings; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ Settings::get('brand_dark') }};">A quick reminder to sign</h1>
    <p style="margin:0 0 14px;">We noticed <strong>{{ $contract->agreement_no }}</strong> is still waiting for your signature. When you’re ready, it only takes a minute — just open the link and confirm with the one-time code we email you.</p>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
        'Fee' => ContractRenderer::money($contract->fee, $contract->currency).' '.($contract->billing_cycle?->per() ?? ''),
    ]])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Review and sign'])

    @if ($expiresAt)
        <p style="font-size:13px;color:{{ Settings::get('brand_grey') }};margin:0;">This link expires on {{ $expiresAt->format('j F Y') }}. If it expires before you sign, contact us and we’ll send a new one.</p>
    @endif
@endsection
