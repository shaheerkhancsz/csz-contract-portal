@php use App\Enums\ContractStatus; @endphp
<x-public-layout title="Your contracts">
    <x-slot:actions>
        <form method="POST" action="{{ route('client.logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </x-slot:actions>

    <div class="card">
        <h1>Welcome, {{ $client->company_name }}</h1>
        <p class="muted">Here are your agreements with {{ \App\Support\Settings::get('company_name') }}. You can review and sign the ones awaiting your signature, and download signed copies.</p>

        @if ($contracts->isEmpty())
            <p>You don’t have any agreements to view yet.</p>
        @else
            <table class="list">
                <thead>
                    <tr><th>Agreement</th><th>Status</th><th>Term</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($contracts as $c)
                        @php
                            $signable = $c->status === ContractStatus::Sent ? $c->activeSigningRequest() : null;
                            $signedDoc = $c->documents->firstWhere('kind', 'signed');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $c->agreement_no }}</strong><br>
                                <span class="muted">{{ $c->title }}</span>
                            </td>
                            <td><span class="badge">{{ $c->status->getLabel() }}</span></td>
                            <td class="muted">{{ $c->start_date->format('j M Y') }} – {{ $c->end_date->format('j M Y') }}</td>
                            <td style="text-align:right;white-space:nowrap;">
                                @if ($signable)
                                    <a class="btn btn-sm" href="{{ $signable->signingUrl() }}">Review &amp; sign</a>
                                @elseif ($signedDoc)
                                    <a class="btn btn-ghost btn-sm" href="{{ route('client.contract.pdf', $c) }}" target="_blank" rel="noopener">Download signed PDF</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-public-layout>
