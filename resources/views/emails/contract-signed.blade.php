@extends('emails.layout')

@section('content')
    @php use App\Support\Settings; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ Settings::get('brand_dark') }};">Contract signed by the client</h1>
    <p style="margin:0 0 14px;"><strong>{{ $contract->agreement_no }}</strong> has been signed online@if ($signerName) by {{ $signerName }}@endif@if ($signedAt) on {{ $signedAt->format('j F Y, H:i') }}@endif. The signed PDF and signature certificate are saved under the contract's documents.</p>

    @include('emails.partials.summary', ['rows' => [
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Client' => $contract->client?->company_name,
    ]])

    @include('emails.partials.button', ['url' => $url, 'label' => 'Open the contract'])
@endsection
