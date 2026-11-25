@extends('emails.layout')

@section('content')
    @php use App\Services\ContractRenderer; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ \App\Support\Settings::get('brand_dark') }};">A contract is awaiting your approval</h1>
    <p style="margin:0 0 14px;">{{ $submittedBy }} submitted <strong>{{ $contract->agreement_no }}</strong> for review. Please approve it or send it back with a note.</p>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
        'Fee' => ContractRenderer::money($contract->fee, $contract->currency).' '.($contract->billing_cycle?->per() ?? ''),
    ]])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Review the contract'])
@endsection
