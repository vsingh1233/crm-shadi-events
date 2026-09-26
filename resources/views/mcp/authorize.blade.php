<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="eyebrow">CONNECTED APPLICATION</p>
            <h1>Connect to Shadi Events CRM</h1>
        </div>
    </x-slot>

    <section class="panel form-panel">
        <h2>{{ $client->name }} is requesting access</h2>

        <p class="muted mt-3">
            You are signed in as {{ $user->email }}.
        </p>

        <p class="mt-4">
            This connection can use the CRM assistant tools permitted
            for your account, including creating leads and their
            discussion notes.
        </p>

        @if (count($scopes) > 0)
            <ul class="list-disc pl-5 mt-4">
                @foreach ($scopes as $scope)
                    <li>{{ $scope->description }}</li>
                @endforeach
            </ul>
        @endif

        @if ($user->can('createViaApi', \App\Models\Lead::class))
            <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="mt-5">
                @csrf

                <input type="hidden" name="client_id" value="{{ $client->id }}">

                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <button type="submit" class="btn">
                    Authorize connection
                </button>
            </form>
        @else
            <p class="notice mt-4">
                Your CRM account does not currently have API access.
                Ask your administrator to enable it.
            </p>
        @endif

        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="mt-4">
            @csrf
            @method('DELETE')

            <input type="hidden" name="client_id" value="{{ $client->id }}">

            <input type="hidden" name="auth_token" value="{{ $authToken }}">

            <button type="submit" class="link">
                Cancel connection
            </button>
        </form>
    </section>
</x-app-layout>
