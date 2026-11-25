@extends('emails.layout')

@section('content')
    @php use App\Services\ContractRenderer; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ \App\Support\Settings::get('brand_dark') }};">Contract approved</h1>
    <p style="margin:0 0 14px;">{{ $approvedBy }} approved <strong>{{ $contract->agreement_no }}</strong>. The text is frozen and the final PDF is saved. You can now send it to the client for signing.</p>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
        'Fee' => ContractRenderer::money($contract->fee, $contract->currency).' '.($contract->billing_cycle?->per() ?? ''),
    ]])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open the contract'])
@endsection
