<x-app-layout>
<x-slot name="header"><div><p class="eyebrow">YOUR PLANNING DESK</p><h1>A little clarity for a beautiful day.</h1><p class="muted">Welcome back, {{ auth()->user()->name }}. Here’s where your enquiries stand.</p></div><a class="btn" href="{{ route('leads.create') }}">+ Add enquiry</a></x-slot>
<div class="stats">
@foreach(['Total enquiries'=>$total,'Open conversations'=>$open,'Confirmed bookings'=>$won,'Overdue follow-ups'=>$overdue] as $label=>$value)
<div class="stat"><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
@endforeach
</div>
<section class="panel"><div class="section-head"><h2>Your notifications</h2><a class="link" href="{{ route('notifications.index') }}">View all</a></div>
@forelse(auth()->user()->notifications()->latest()->limit(5)->get() as $notification)
<article class="list-row"><div><strong>{{ $notification->data['title'] ?? 'CRM update' }}</strong><p class="muted mt-1">{{ $notification->data['message'] ?? '' }}</p><p class="muted mt-1">{{ $notification->created_at->timezone(config('crm.timezone'))->format('d M Y H:i') }}</p>@if(!empty($notification->data['lead_id']))<a class="link" href="{{ route('leads.show',$notification->data['lead_id']) }}">Open enquiry</a>@endif</div>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read',$notification->id) }}">@csrf @method('PATCH')<button class="btn-secondary">Mark read</button></form>@endif</article>
@empty<p class="empty">You’re all caught up. Reminders and team updates will appear here.</p>@endforelse
</section>
<div class="two-columns">
<section class="panel"><div class="section-head"><h2>Next conversations</h2><a class="link" href="{{ route('follow-ups.index') }}">View all</a></div>
@forelse($upcoming as $followUp)<div class="list-row"><div><a class="link" href="{{ route('leads.show',$followUp->lead) }}">{{ $followUp->lead->name }}</a><p class="muted">{{ $followUp->responsible->name }}</p></div><span class="{{ $followUp->due_at->isPast()?'overdue':'muted' }}">{{ $followUp->due_at->timezone(config('crm.timezone'))->format('d M, H:i') }}</span></div>
@empty<p class="empty">No pending follow-ups. Open an enquiry to schedule one.</p>@endforelse
</section>
<section class="panel"><div class="section-head"><h2>Recent enquiries</h2><a class="link" href="{{ route('leads.index') }}">View all</a></div>
@forelse($recent as $lead)<div class="list-row"><div><a class="link" href="{{ route('leads.show',$lead) }}">{{ $lead->name }}</a><p class="muted">{{ $lead->wedding_location ?? 'Destination undecided' }}</p></div><span class="badge">{{ \App\Models\Lead::STATUSES[$lead->status] ?? $lead->status }}</span></div>@empty<p class="empty">Your first enquiry starts here. Choose “Add enquiry”.</p>@endforelse
</section>
</div>
</x-app-layout>

