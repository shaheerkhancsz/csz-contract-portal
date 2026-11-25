@extends('emails.layout')

@section('content')
    @php use App\Support\Settings; @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ Settings::get('brand_dark') }};">Thank you — your contract is signed</h1>
    <p style="margin:0 0 14px;">We have recorded your signature on <strong>{{ $contract->agreement_no }}</strong>@if ($signedAt) on {{ $signedAt->format('j F Y') }}@endif. A copy of the signed agreement, including a signature certificate, is attached to this email for your records.</p>

    @include('emails.partials.summary', ['rows' => array_filter([
        'Agreement' => $contract->agreement_no,
        'Title' => $contract->title,
        'Signed by' => $signerName,
    ])])

    <p style="margin:14px 0 0;">If you have any questions, reply to {{ Settings::get('support_email') }}.</p>
@endsection
