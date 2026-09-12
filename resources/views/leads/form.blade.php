<h2>Client & celebration</h2><p class="muted mb-6">Name and a phone number or email are required. Wedding details can come later. Use a country code for international phone numbers.</p>
<div class="form-grid">
@foreach([['name','Client name','text'],['email','Email address','email'],['phone','Phone number','tel'],['client_location','Client location','text'],['wedding_location','Preferred wedding location','text'],['wedding_start_date','Wedding start date','date'],['wedding_end_date','Wedding end date','date']] as [$field,$label,$type])
<label>{{ $label }} @if($field==='name')<span aria-hidden="true">*</span>@endif
<input name="{{ $field }}" type="{{ $type }}" value="{{ old($field,$type==='date' ? $lead->$field?->format('Y-m-d') : $lead->$field) }}" @required($field==='name') maxlength="{{ $field==='phone'?30:255 }}">
</label>
@endforeach
<label>Temperature<select name="temperature"><option value="">Not assessed</option>@foreach(\App\Models\Lead::TEMPERATURES as $value=>$label)<option value="{{ $value }}" @selected(old('temperature',$lead->temperature)===$value)>{{ $label }}</option>@endforeach</select></label>
<label>Lifecycle status<select name="status">@foreach(\App\Models\Lead::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(old('status',$lead->status ?? 'new')===$value)>{{ $label }}</option>@endforeach</select></label>
<label class="full-width">Lost reason <span class="muted">(required only for Lost)</span><textarea name="lost_reason" rows="3" maxlength="5000">{{ old('lost_reason',$lead->lost_reason) }}</textarea></label>
</div>
<p class="muted mt-4">Stages may be skipped. To reopen a Won or Lost enquiry, select an open stage. Closing an enquiry cancels pending follow-ups; their history is retained.</p>
@if($errors->has('confirm_duplicate'))
<label class="checkbox-row mt-5"><input type="checkbox" name="confirm_duplicate" value="1">I reviewed the matching-contact warning. Wedding details are incomplete, and this is a separate enquiry.</label>
@endif

