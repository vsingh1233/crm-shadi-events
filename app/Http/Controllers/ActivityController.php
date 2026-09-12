<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Notifications\CrmNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function store(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate(['type' => ['required', 'in:call,meeting,discussion'], 'occurred_at' => ['required', 'date_format:Y-m-d\TH:i'], 'notes' => ['required', 'string', 'max:10000']]);
        $time = Carbon::createFromFormat('Y-m-d\TH:i', $data['occurred_at'], config('crm.timezone'))->utc();
        if ($time->isFuture()) {
            return back()->withErrors(['occurred_at' => 'Discussion time cannot be in the future.'])->withInput();
        }
        $activity = new Activity;
        $activity->type = $data['type'];
        $activity->occurred_at = $time;
        $activity->notes = $data['notes'];
        $activity->user_id = $request->user()->id;
        DB::transaction(function () use ($lead, $activity, $request) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('update', $lead);
            $lead->activities()->save($activity);
            $lead->load('owner', 'collaborators');
            $lead->collaborators->push($lead->owner)->unique('id')->where('id', '!=', $request->user()->id)
                ->each(fn ($member) => $member->notify(new CrmNotification('New discussion', 'A team member recorded a discussion on your enquiry.', $lead->id)));
        });

        return to_route('leads.show', $lead)->with('success', 'Discussion added to the timeline.');
    }
}
