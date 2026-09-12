<x-app-layout>
<x-slot name="header"><div><a class="eyebrow" href="{{ route('leads.index') }}">← ALL ENQUIRIES</a><h1>{{ $lead->name }}</h1><p class="muted">{{ $lead->wedding_location ?? 'Destination undecided' }} · {{ $lead->wedding_start_date?->format('d M Y') ?? 'Dates to be decided' }}</p></div><div class="flex items-center gap-3"><span class="badge">{{ \App\Models\Lead::STATUSES[$lead->status] }}</span>@can('update',$lead)<a class="btn" href="{{ route('leads.edit',$lead) }}">Edit enquiry</a>@endcan</div></x-slot>
<div class="detail-columns"><div>
<section class="panel"><div class="section-head"><h2>The details</h2><span class="temperature {{ $lead->temperature }}">{{ ucfirst($lead->temperature ?? 'Unassessed') }}</span></div>
<dl class="details-grid">
@foreach(['Email'=>$lead->email,'Phone'=>$lead->phone,'Client location'=>$lead->client_location,'Wedding location'=>$lead->wedding_location,'Wedding start'=>$lead->wedding_start_date?->format('d M Y'),'Wedding end'=>$lead->wedding_end_date?->format('d M Y'),'Owner'=>$lead->owner->name,'Created by'=>$lead->creator->name,'Source'=>ucfirst($lead->source),'Created'=>$lead->created_at->timezone(config('crm.timezone'))->format('d M Y H:i'),'Updated'=>$lead->updated_at->timezone(config('crm.timezone'))->format('d M Y H:i')] as $label=>$value)
<div><dt>{{ $label }}</dt><dd>{{ $value ?? 'Not provided' }}</dd></div>@endforeach
@if($lead->lost_reason)<div class="full-width"><dt>Lost reason</dt><dd>{{ $lead->lost_reason }}</dd></div>@endif
</dl></section>
<section class="panel"><h2>Conversation timeline</h2><p class="muted mb-5">Discussions and important changes, newest first.</p>
<form method="POST" action="{{ route('activities.store',$lead) }}" class="inset-form">@csrf
<div class="form-grid"><label>Activity type<select name="type"><option value="call">Call</option><option value="meeting">Meeting</option><option value="discussion">Discussion</option></select></label><label>Date & time<input type="datetime-local" name="occurred_at" value="{{ old('occurred_at',now()->timezone(config('crm.timezone'))->format('Y-m-d\TH:i')) }}" required></label></div>
<label class="mt-3 block">Discussion notes<textarea name="notes" rows="3" required maxlength="10000">{{ old('notes') }}</textarea></label><button class="btn mt-3">Record discussion</button>
</form>
<div class="timeline">@forelse($timeline as $item)<article class="timeline-item"><div class="flex justify-between gap-3 flex-wrap"><strong>{{ ucfirst($item['kind']) }}</strong><time class="muted">{{ $item['time']->timezone(config('crm.timezone'))->format('d M Y, H:i') }}</time></div><p class="whitespace-pre-wrap break-words mt-2">{{ $item['text'] }}</p><p class="muted mt-2">{{ $item['by'] }} · Recorded {{ $item['recorded']->timezone(config('crm.timezone'))->format('d M Y, H:i') }}</p></article>@empty<p class="empty">No discussions yet. Start with a note above.</p>@endforelse</div>
</section></div><aside>
<section class="panel"><h2>Follow-ups</h2><p class="muted mb-5">Keep the next conversation in sight.</p>
@if(!$lead->isClosed())
<form method="POST" action="{{ route('follow-ups.store',$lead) }}" class="inset-form">@csrf
<label>When · Asia/Kolkata<input type="datetime-local" name="due_at" value="{{ old('due_at') }}" required></label>
<label class="block mt-3">Responsible member<select name="responsible_id">@foreach($responsibleMembers as $member)<option value="{{ $member->id }}" @selected(old('responsible_id',$lead->owner_id)==$member->id)>{{ $member->name }}</option>@endforeach</select></label>
<label class="block mt-3">Purpose<textarea name="notes" required rows="2" maxlength="5000"></textarea></label><button class="btn mt-3">Schedule follow-up</button>
</form>
@else<p class="notice">Reopen this enquiry to schedule more follow-ups.</p>@endif
@forelse($followUps as $followUp)<article class="follow-card"><div class="flex justify-between gap-2"><strong class="{{ $followUp->status==='pending' && $followUp->due_at->isPast()?'overdue':'' }}">{{ $followUp->due_at->timezone(config('crm.timezone'))->format('d M Y, H:i') }}</strong><span class="badge">{{ $followUp->status==='pending' && $followUp->due_at->isPast()?'Overdue':ucfirst($followUp->status) }}</span></div><p class="mt-2 whitespace-pre-wrap">{{ $followUp->notes }}</p><p class="muted mt-2">{{ $followUp->responsible->name }}</p>
@if($followUp->status==='pending')<form method="POST" action="{{ route('follow-ups.update',[$lead,$followUp]) }}" class="flex gap-4 mt-3">@csrf @method('PATCH')<button name="status" value="completed" class="link">Complete</button><button name="status" value="cancelled" class="link">Cancel</button></form>@else<p class="muted">{{ ucfirst($followUp->status) }} {{ $followUp->closed_at?->timezone(config('crm.timezone'))->format('d M Y H:i') }}</p>@endif
</article>@empty<p class="empty">No follow-ups scheduled.</p>@endforelse
</section>
<section class="panel"><h2>Involved team</h2><p class="muted mb-4">Owner: {{ $lead->owner->name }}</p>
@foreach($lead->collaborators as $member)<span class="badge mr-1 mb-2">{{ $member->name }}</span>@endforeach
@can('manageTeam',$lead)
<form method="POST" action="{{ route('leads.team',$lead) }}" class="mt-4">@csrf @method('PATCH')
@if(auth()->user()->isAdministrator())<label>Owner<select name="owner_id">@foreach($members as $member)<option value="{{ $member->id }}" @selected($lead->owner_id===$member->id)>{{ $member->name }}</option>@endforeach</select></label>@else<input type="hidden" name="owner_id" value="{{ $lead->owner_id }}">@endif
<fieldset class="mt-4"><legend class="muted mb-2">Collaborators</legend>@foreach($members as $member)<label class="checkbox-row"><input type="checkbox" name="collaborators[]" value="{{ $member->id }}" @checked($lead->collaborators->contains('id',$member->id))>{{ $member->name }}</label>@endforeach</fieldset>
<p class="muted mt-3">Removing a member cancels their pending follow-ups if they lose access. History is retained.</p><button class="btn-secondary mt-3">Save team</button></form>
@endcan</section>
@can('delete',$lead)<section class="panel"><h2>Delete enquiry</h2><p class="muted mb-3">Deletion cancels pending follow-ups. The enquiry can be restored later.</p><form method="POST" action="{{ route('leads.destroy',$lead) }}" onsubmit="return confirm('Move this lead to deleted leads and cancel pending follow-ups?')">@csrf @method('DELETE')<button class="link">Move to deleted leads</button></form></section>@endcan
</aside></div>
</x-app-layout>

