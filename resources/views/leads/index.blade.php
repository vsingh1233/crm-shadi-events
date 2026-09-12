<x-app-layout>
<x-slot name="header"><div><p class="eyebrow">RELATIONSHIPS, IN ONE PLACE</p><h1>{{ request('archived')?'Deleted leads':'Your enquiries' }}</h1></div><a class="btn" href="{{ route('leads.create') }}">+ Add enquiry</a></x-slot>
<form method="GET" class="panel filter-panel">
<div class="filter-grid">
<label>Search<input name="search" value="{{ request('search') }}" placeholder="Name, email or phone"></label>
<label>Status<select name="status"><option value="">All stages</option>@foreach(\App\Models\Lead::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Temperature<select name="temperature"><option value="">All temperatures</option>@foreach(\App\Models\Lead::TEMPERATURES as $value=>$label)<option value="{{ $value }}" @selected(request('temperature')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Owner<select name="owner_id"><option value="">All owners</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(request('owner_id')==$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
<label>Wedding from<input type="date" name="date_from" value="{{ request('date_from') }}"></label><label>Wedding to<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
<label>Wedding location<input name="wedding_location" value="{{ request('wedding_location') }}"></label><label>Client location<input name="client_location" value="{{ request('client_location') }}"></label>
<label>Follow-up<select name="follow_up"><option value="">Any</option>@foreach(['pending','upcoming','overdue','completed','cancelled'] as $value)<option @selected(request('follow_up')===$value) value="{{ $value }}">{{ ucfirst($value) }}</option>@endforeach</select></label>
<label>Sort by<select name="sort">@foreach(['created_at'=>'Creation date','wedding_start_date'=>'Wedding date','wedding_location'=>'Wedding location','client_location'=>'Client location','name'=>'Name'] as $value=>$label)<option value="{{ $value }}" @selected(request('sort','created_at')===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Direction<select name="direction"><option value="desc" @selected(request('direction','desc')==='desc')>Descending</option><option value="asc" @selected(request('direction')==='asc')>Ascending</option></select></label>
@if(auth()->user()->isAdministrator())<label>View<select name="archived"><option value="0">Current leads</option><option value="1" @selected(request('archived')==='1')>Deleted leads</option></select></label>@endif
</div><div class="form-actions"><button class="btn">Apply filters</button><a class="link" href="{{ route('leads.index') }}">Clear filters</a><span class="muted">{{ $leads->total() }} enquiries</span></div>
</form>
<div class="panel table-panel"><div class="table-scroll"><table><thead><tr>@foreach(['Client','Contact','Wedding','Stage','Temperature','Owner','Created'] as $title)<th scope="col">{{ $title }}</th>@endforeach</tr></thead><tbody>
@forelse($leads as $lead)
<tr><td>@if($lead->trashed())<strong>{{ $lead->name }}</strong><form method="POST" action="{{ route('leads.restore',$lead->id) }}">@csrf<button class="link">Restore</button></form>@else<a class="link" href="{{ route('leads.show',$lead) }}">{{ $lead->name }}</a>@endif<p class="muted">{{ $lead->client_location ?? 'Location not provided' }}</p></td>
<td>{{ $lead->phone ?? '—' }}<p class="muted">{{ $lead->email ?? '—' }}</p></td><td>{{ $lead->wedding_location ?? 'Undecided' }}<p class="muted">{{ $lead->wedding_start_date?->format('d M Y') ?? 'Dates undecided' }}</p></td>
<td><span class="badge">{{ \App\Models\Lead::STATUSES[$lead->status] ?? $lead->status }}</span></td><td><span class="temperature {{ $lead->temperature }}">{{ ucfirst($lead->temperature ?? 'Unassessed') }}</span></td><td>{{ $lead->owner?->name }}</td><td class="whitespace-nowrap">{{ $lead->created_at->timezone(config('crm.timezone'))->format('d M Y') }}</td></tr>
@empty<tr><td colspan="7" class="empty">No enquiries match these filters.</td></tr>@endforelse
</tbody></table></div><div class="p-5">{{ $leads->links() }}</div></div>
</x-app-layout>

