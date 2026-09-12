<x-app-layout><x-slot name="header"><div><p class="eyebrow">KEEP THE DETAILS CURRENT</p><h1>Edit {{ $lead->name }}</h1></div></x-slot>
<form method="POST" action="{{ route('leads.update',$lead) }}" class="panel form-panel">@csrf @method('PATCH') @include('leads.form')<div class="form-actions"><button class="btn">Save changes</button><a class="link" href="{{ route('leads.show',$lead) }}">Cancel</a></div></form></x-app-layout>

