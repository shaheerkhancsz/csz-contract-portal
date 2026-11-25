@extends('emails.layout')

@section('content')
    @php use App\Support\Settings; $primary = Settings::get('brand_primary'); $dark = Settings::get('brand_dark'); $grey = Settings::get('brand_grey'); @endphp
    <h1 style="font-size:19px;margin:0 0 14px;color:{{ $dark }};">Your verification code</h1>
    <p style="margin:0 0 14px;">Enter this code on the signing page for <strong>{{ $contract->agreement_no }}</strong> to confirm your email and continue.</p>

    <div style="text-align:center;margin:20px 0;">
        <span style="display:inline-block;padding:14px 28px;font-size:30px;font-weight:bold;letter-spacing:8px;color:{{ $dark }};background:#f2f7fb;border:1px solid {{ $primary }};border-radius:8px;">{{ $code }}</span>
    </div>

    <p style="font-size:13px;color:{{ $grey }};margin:0;">This code expires in {{ $ttlMinutes }} minutes. If you did not request it, you can ignore this email.</p>
@endsection
