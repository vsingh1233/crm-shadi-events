<x-app-layout><x-slot name="header"><div><p class="eyebrow">A NEW CONVERSATION</p><h1>Add an enquiry</h1></div><a class="link" href="{{ route('leads.index') }}">Back to leads</a></x-slot>
<form method="POST" action="{{ route('leads.store') }}" class="panel form-panel">@csrf @include('leads.form')<div class="form-actions"><button class="btn">Create enquiry</button><a class="link" href="{{ route('leads.index') }}">Cancel</a></div></form></x-app-layout>

