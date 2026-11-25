<x-public-layout title="Client area">
    <div class="card">
        <h1>We couldn’t sign you in</h1>
        <p>{{ $message }}</p>
        <p class="muted">Please return to WHMCS and open the client area again, or contact {{ \App\Support\Settings::get('support_email') }} for help.</p>
    </div>
</x-public-layout>
