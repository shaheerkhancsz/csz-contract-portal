<x-public-layout title="Session ended">
    <div class="card">
        <h1>Your session has ended</h1>
        <p>For your security, the client area signs you out after a period of inactivity.</p>
        <p class="muted">To continue, open the client area again from your WHMCS account at {{ \App\Support\Settings::get('company_name') }}.</p>
    </div>
</x-public-layout>
