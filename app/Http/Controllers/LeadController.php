<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLeadRequest;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'string', 'max:40'],
            'temperature' => ['nullable', 'string', 'max:10'], 'owner_id' => ['nullable', 'integer'],
            'wedding_location' => ['nullable', 'string', 'max:255'], 'client_location' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'],
            'follow_up' => ['nullable', 'string', 'max:20'], 'sort' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'string', 'max:20'], 'archived' => ['nullable', 'boolean'],
        ]);
        $query = Lead::visibleTo($request->user())->with('owner');
        if ($request->boolean('archived') && $request->user()->isAdministrator()) {
            $query->onlyTrashed();
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                if ($phone = Lead::normalizePhone($search)) {
                    $q->orWhere('normalized_phone', 'like', "%{$phone}%");
                }
            });
        }
        foreach (['status', 'temperature', 'owner_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        foreach (['wedding_location', 'client_location'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, 'like', '%'.$request->input($field).'%');
            }
        }
        $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date']]);
        if ($request->filled('date_from')) {
            $query->whereDate('wedding_start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('wedding_start_date', '<=', $request->date_to);
        }
        if ($request->filled('follow_up')) {
            $query->whereHas('followUps', function ($q) use ($request) {
                if ($request->follow_up === 'overdue') {
                    $q->where('status', 'pending')->where('due_at', '<', now());
                } elseif ($request->follow_up === 'upcoming') {
                    $q->where('status', 'pending')->where('due_at', '>=', now());
                } else {
                    $q->where('status', $request->follow_up);
                }
            });
        }
        $sort = in_array($request->sort, ['created_at', 'wedding_start_date', 'wedding_location', 'client_location', 'name'], true) ? $request->sort : 'created_at';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';
        $leads = $query->orderBy($sort, $direction)->orderBy('id', $direction)->paginate(15)->withQueryString();

        return view('leads.index', compact('leads') + ['owners' => User::whereIn('id', Lead::visibleTo($request->user())->select('owner_id'))->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('leads.create', ['lead' => new Lead]);
    }

    public function edit(Lead $lead): View
    {
        Gate::authorize('update', $lead);

        return view('leads.edit', compact('lead'));
    }

    public function show(Lead $lead): View
    {
        Gate::authorize('view', $lead);
        $lead->load(['owner', 'creator', 'collaborators']);
        $timeline = $lead->activities()->with('user')->get()->map(fn ($a) => ['time' => $a->occurred_at, 'recorded' => $a->created_at, 'kind' => $a->type, 'text' => $a->notes, 'by' => $a->user?->name])
            ->concat($lead->changes()->with('user')->get()->map(fn ($c) => ['time' => $c->created_at, 'recorded' => $c->created_at, 'kind' => str_replace('_', ' ', $c->field), 'text' => ($c->old_value ?? '—').' → '.($c->new_value ?? '—'), 'by' => $c->user?->name ?? 'System']))
            ->sortByDesc('time');

        return view('leads.show', compact('lead', 'timeline') + [
            'followUps' => $lead->followUps()->with('responsible')->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('due_at')->get(),
            'members' => User::where('is_active', true)->orderBy('name')->get(),
            'responsibleMembers' => User::where('is_active', true)->where(fn ($q) => $q->where('role', 'administrator')->orWhere('id', $lead->owner_id)->orWhereIn('id', $lead->collaborators->pluck('id')))->orderBy('name')->get(),
        ]);
    }

    public function store(SaveLeadRequest $request): RedirectResponse
    {
        $lead = DB::transaction(function () use ($request) {
            $lead = new Lead;
            $this->fillLead($lead, $request);
            $lead->owner_id = $request->user()->id;
            $lead->created_by = $request->user()->id;
            $lead->source = 'manual';
            $lead->save();
            $lead->recordChange('created', null, 'Manual enquiry', $request->user()->id);

            return $lead;
        });

        return to_route('leads.show', $lead)->with('success', 'Lead created successfully.');
    }

    public function update(SaveLeadRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);
        DB::transaction(function () use ($request, $lead) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('update', $lead);
            $this->fillLead($lead, $request);
            foreach ($lead->getDirty() as $field => $value) {
                $lead->recordChange($field, $lead->getRawOriginal($field), $value, $request->user()->id);
            }
            $lead->save();
            if ($lead->isClosed()) {
                $this->cancelFollowUps($lead, $request->user()->id, 'Lead closed');
            }
            $this->notifyTeam($lead, 'Lead updated', $request->user()->id);
        });

        return to_route('leads.show', $lead)->with('success', 'Lead updated successfully.');
    }

    private function fillLead(Lead $lead, SaveLeadRequest $request): void
    {
        $data = $request->validated();
        $this->checkDuplicates($lead, $data, $request->boolean('confirm_duplicate'));
        foreach (['name', 'email', 'phone', 'client_location', 'wedding_location', 'wedding_start_date', 'wedding_end_date', 'temperature', 'status'] as $field) {
            $lead->$field = $data[$field] ?? null;
        }
        $lead->lost_reason = $data['status'] === 'lost' ? $data['lost_reason'] : null;
    }

    private function checkDuplicates(Lead $lead, array $data, bool $confirmed): void
    {
        if ($lead->exists) {
            $unchanged = true;
            foreach (['email', 'phone', 'wedding_location', 'wedding_start_date', 'wedding_end_date'] as $field) {
                $before = str_starts_with($field, 'wedding_') && str_ends_with($field, '_date') ? $lead->$field?->format('Y-m-d') : $lead->$field;
                if (($data[$field] ?? null) !== $before) {
                    $unchanged = false;
                }
            }
            if ($unchanged) {
                return;
            }
        }
        $phone = Lead::normalizePhone($data['phone'] ?? null);
        $matches = Lead::withTrashed()->where(function ($q) use ($data, $phone) {
            $q->whereRaw('1 = 0');
            if (! empty($data['email'])) {
                $q->orWhere('email', $data['email']);
            }
            if ($phone) {
                $q->orWhere('normalized_phone', $phone);
            }
        })->when($lead->exists, fn ($q) => $q->where('id', '!=', $lead->id))->get();
        foreach ($matches as $match) {
            $known = ! empty($data['wedding_location']) && $match->wedding_location && ! empty($data['wedding_start_date']) && ! empty($data['wedding_end_date']) && $match->wedding_start_date && $match->wedding_end_date;
            if (! $known) {
                if (! $confirmed) {
                    throw ValidationException::withMessages(['confirm_duplicate' => 'A matching contact exists, but wedding details are incomplete. Review the enquiry and confirm below to save.']);
                }

                continue;
            }
            $differentLocation = mb_strtolower(trim($data['wedding_location'])) !== mb_strtolower(trim($match->wedding_location));
            $differentDates = $data['wedding_start_date'] !== $match->wedding_start_date->format('Y-m-d') || $data['wedding_end_date'] !== $match->wedding_end_date->format('Y-m-d');
            if (! $differentLocation || ! $differentDates) {
                throw ValidationException::withMessages(['email' => 'This contact already has an enquiry. Both wedding location and dates must differ for a separate lead; otherwise update the existing lead or ask an administrator.']);
            }
        }
    }

    public function team(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('manageTeam', $lead);
        $data = $request->validate(['owner_id' => ['required', 'integer', 'exists:users,id'], 'collaborators' => ['nullable', 'array'], 'collaborators.*' => ['integer', 'distinct', 'exists:users,id']]);
        abort_unless($request->user()->isAdministrator() || (int) $data['owner_id'] === $lead->owner_id, 403);
        $ids = collect($data['collaborators'] ?? [])->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === (int) $data['owner_id'])->values();
        if (User::whereIn('id', $ids->push((int) $data['owner_id']))->where('is_active', false)->exists()) {
            throw ValidationException::withMessages(['owner_id' => 'Only active users can be assigned.']);
        }
        $ids = $ids->reject(fn ($id) => $id === (int) $data['owner_id'])->values();
        DB::transaction(function () use ($request, $lead, $data, $ids) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('manageTeam', $lead);
            abort_unless($request->user()->isAdministrator() || (int) $data['owner_id'] === $lead->owner_id, 403);
            $before = $lead->collaborators()->orderBy('users.id')->get()->map(fn ($member) => $member->name.' (#'.$member->id.')')->all();
            $oldOwnerId = $lead->owner_id;
            $oldOwner = $lead->owner->name.' (#'.$oldOwnerId.')';
            $lead->owner_id = $data['owner_id'];
            $lead->save();
            $lead->collaborators()->sync($ids);
            $lead->unsetRelation('owner');
            if ($oldOwnerId !== $lead->owner_id) {
                $lead->recordChange('owner', $oldOwner, $lead->owner->name.' (#'.$lead->owner_id.')', $request->user()->id);
            }
            $after = $lead->collaborators()->orderBy('users.id')->get()->map(fn ($member) => $member->name.' (#'.$member->id.')')->all();
            if ($before !== $after) {
                $lead->recordChange('collaborators', $before, $after, $request->user()->id);
            }
            foreach ($lead->followUps()->where('status', 'pending')->with('responsible')->get() as $followUp) {
                if (! $followUp->responsible->can('view', $lead)) {
                    $followUp->status = 'cancelled';
                    $followUp->closed_at = now();
                    $followUp->save();
                    $lead->recordChange('follow_up', null, 'Cancelled #'.$followUp->id.': responsible member removed', $request->user()->id);
                }
            }
            $this->notifyTeam($lead, 'Lead team updated', $request->user()->id);
        });

        return to_route('leads.index')->with('success', 'Lead team updated. Follow-ups belonging to removed members were cancelled.');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);
        DB::transaction(function () use ($lead, $request) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            Gate::authorize('delete', $lead);
            $this->cancelFollowUps($lead, $request->user()->id, 'Lead deleted');
            $lead->recordChange('deleted', null, 'Moved to deleted leads', $request->user()->id);
            $lead->delete();
        });

        return to_route('leads.index')->with('success', 'Lead deleted. An administrator can restore it.');
    }

    public function restore(Request $request, int $lead): RedirectResponse
    {
        $record = Lead::onlyTrashed()->findOrFail($lead);
        Gate::authorize('restore', $record);
        DB::transaction(function () use ($record, $request) {
            $record->restore();
            $record->recordChange('restored', null, 'Lead restored; previous follow-ups remain cancelled', $request->user()->id);
        });

        return to_route('leads.show', $record)->with('success', 'Lead restored.');
    }

    private function cancelFollowUps(Lead $lead, int $actor, string $reason): void
    {
        foreach ($lead->followUps()->where('status', 'pending')->get() as $followUp) {
            $followUp->status = 'cancelled';
            $followUp->closed_at = now();
            $followUp->save();
            $lead->recordChange('follow_up', 'Pending #'.$followUp->id, 'Cancelled: '.$reason, $actor);
        }
    }

    private function notifyTeam(Lead $lead, string $title, int $actor): void
    {
        $lead->load('collaborators', 'owner');
        $lead->collaborators->push($lead->owner)->unique('id')->where('id', '!=', $actor)->each(fn ($user) => $user->notify(new CrmNotification($title, 'An enquiry you are involved in has changed.', $lead->id)));
    }
}
