<x-guest-layout>
<p class="eyebrow">ACCOUNT REVIEW</p><h1 class="text-3xl mb-4">Welcome, {{ auth()->user()->name }}</h1>
<p class="mb-6">Your account is awaiting administrator approval. You can access the CRM once an administrator activates it.</p>
<a class="btn" href="{{ route('approval.pending') }}">Check approval status</a>
<form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<button class="link">Log out</button></form>
</x-guest-layout>

