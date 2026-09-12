<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FollowUpController extends Controller
{
    public function index(Request $request): View
    {
        $query = FollowUp::with('lead', 'responsible')->whereHas('lead', fn ($q) => $q->visibleTo($request->user()));
        if ($request->boolean('mine')) {
            $query->where('responsible_id', $request->user()->id);
        }
        $filter = $request->input('filter', 'pending');
        if ($filter === 'overdue') {
            $query->where('status', 'pending')->where('due_at', '<', now());
        } elseif ($filter === 'upcoming') {
            $query->where('status', 'pending')->where('due_at', '>=', now());
        } elseif (in_array($filter, ['pending', 'completed', 'cancelled'], true)) {
            $query->where('status', $filter);
        }

        return view('follow-ups.index', ['followUps' => $query->orderBy('due_at')->paginate(20)->withQueryString()]);
    }

    public function store(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        $data = $request->validate(['responsible_id' => ['required', 'integer', 'exists:users,id'], 'due_at' => ['required', 'date_format:Y-m-d\TH:i'], 'notes' => ['required', 'string', 'max:5000']]);
        $due = Carbon::createFromFormat('Y-m-d\TH:i', $data['due_at'], config('crm.timezone'))->utc();
        if (! $due->isFuture()) {
            return back()->withErrors(['due_at' => 'Choose a future follow-up time.'])->withInput();
        }
        $responsible = User::findOrFail($data['responsible_id']);
        DB::transaction(function () use ($lead, $request, $data, $due, $responsible) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('update', $lead);
            abort_if($lead->isClosed(), 422, 'Reopen the lead before scheduling another follow-up.');
            abort_unless($responsible->can('view', $lead), 422, 'Choose an active member with access to this lead.');
            $followUp = new FollowUp;
            $followUp->responsible_id = $responsible->id;
            $followUp->created_by = $request->user()->id;
            $followUp->due_at = $due;
            $followUp->notes = $data['notes'];
            $lead->followUps()->save($followUp);
            $lead->recordChange('follow_up', null, 'Scheduled #'.$followUp->id.' for '.$responsible->name.' at '.$due->copy()->timezone(config('crm.timezone'))->format('d M Y H:i'), $request->user()->id);
            if ($responsible->id !== $request->user()->id) {
                $responsible->notify(new CrmNotification('Follow-up assigned', 'A follow-up has been assigned to you.', $lead->id));
            }
        });

        return to_route('leads.show', $lead)->with('success', 'Follow-up scheduled.');
    }

    public function update(Request $request, Lead $lead, FollowUp $followUp): RedirectResponse
    {
        Gate::authorize('update', $lead);
        abort_unless($followUp->lead_id === $lead->id, 404);
        $data = $request->validate(['status' => ['required', 'in:completed,cancelled']]);
        DB::transaction(function () use ($lead, $followUp, $data, $request) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('update', $lead);
            $followUp = FollowUp::lockForUpdate()->findOrFail($followUp->id);
            abort_unless($followUp->status === 'pending', 422, 'This follow-up is already closed.');
            $followUp->status = $data['status'];
            $followUp->closed_at = now();
            $followUp->save();
            $lead->recordChange('follow_up', 'Pending #'.$followUp->id, ucfirst($data['status']), $request->user()->id);
            if ($followUp->responsible_id !== $request->user()->id) {
                $followUp->responsible->notify(new CrmNotification('Follow-up '.$data['status'], 'A follow-up assigned to you was '.$data['status'].'.', $lead->id));
            }
        });

        return back()->with('success', 'Follow-up updated. Schedule a new one whenever needed.');
    }
}
