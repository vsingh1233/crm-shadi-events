<?php

namespace App;

use App\Http\Resources\FollowUpResource;
use App\Http\Resources\LeadResource;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CrmNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CrmLeadActions
{
    public function authorize(User $user): void
    {
        Gate::forUser($user)->authorize('createViaApi', Lead::class);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function search(User $user, array $input): array
    {
        $this->authorize($user);
        $data = Validator::make($input, ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', Rule::in(array_keys(Lead::STATUSES))], 'page' => ['sometimes', 'integer', 'min:1']])->validate();
        $query = Lead::visibleTo($user)->search($data['search'] ?? '');
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        $leads = $query->orderByDesc('id')->paginate(20, ['*'], 'page', $data['page'] ?? 1);

        return ['data' => LeadResource::collection($leads->getCollection())->resolve(), 'meta' => ['page' => $leads->currentPage(), 'last_page' => $leads->lastPage(), 'total' => $leads->total()]];
    }

    /** @return array<string, mixed> */
    public function details(User $user, int $leadId, int $page = 1): array
    {
        $this->authorize($user);
        abort_if($page < 1, 422, 'Page must be positive.');
        $lead = Lead::visibleTo($user)->findOrFail($leadId);
        $followUps = $lead->followUps()->with('responsible')->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('due_at')->orderBy('id')->paginate(20, ['*'], 'page', $page);
        $members = User::where('is_active', true)->where(fn ($query) => $query->where('role', 'administrator')->orWhere('id', $lead->owner_id)->orWhereIn('id', $lead->collaborators()->select('users.id')))->orderBy('name')->get(['id', 'name']);

        return ['data' => (new LeadResource($lead))->resolve(), 'follow_ups' => FollowUpResource::collection($followUps->getCollection())->resolve(), 'follow_up_pagination' => ['page' => $followUps->currentPage(), 'last_page' => $followUps->lastPage(), 'total' => $followUps->total()], 'responsible_members' => $members->toArray(), 'timezone' => config('crm.timezone')];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateCoupleName(User $user, int $leadId, array $input): array
    {
        $this->authorize($user);
        $data = Validator::make($input, ['couple_name' => ['present', 'nullable', 'string', 'max:255'], 'expected_couple_name' => ['present', 'nullable', 'string', 'max:255']])->validate();
        $name = is_string($data['couple_name']) ? (trim($data['couple_name']) ?: null) : null;

        return DB::transaction(function () use ($user, $leadId, $data, $name): array {
            $lead = Lead::visibleTo($user)->lockForUpdate()->findOrFail($leadId);
            Gate::forUser($user)->authorize('update', $lead);
            if ($lead->couple_name !== $name) {
                abort_unless($lead->couple_name === $data['expected_couple_name'], 409, 'The couple name changed. Read the lead again before updating.');
                $lead->recordChange('couple_name', $lead->couple_name, $name, $user->id);
                $lead->couple_name = $name;
                $lead->save();
                $lead->collaborators->push($lead->owner)->unique('id')->where('id', '!=', $user->id)->each(fn (User $member) => $member->notify(new CrmNotification('Lead updated', 'An enquiry you are involved in has changed.', $lead->id)));
            }

            return ['data' => (new LeadResource($lead))->resolve()];
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function schedule(User $user, int $leadId, array $input): array
    {
        $this->authorize($user);
        $data = Validator::make($input, ['request_id' => ['required', 'uuid'], 'responsible_id' => ['required', 'integer', 'min:1'], 'due_at' => ['required', 'date_format:Y-m-d\TH:iP'], 'notes' => ['required', 'string', 'max:5000']])->validate();
        $due = Carbon::createFromFormat('!Y-m-d\TH:iP', $data['due_at'])->utc();
        $requestId = strtolower($data['request_id']);
        $hash = hash('sha256', json_encode(['lead_id' => $leadId, 'responsible_id' => (int) $data['responsible_id'], 'due_at' => $due->format('Y-m-d H:i:s'), 'notes' => $data['notes']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $leadId, $data, $due, $requestId, $hash): array {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->authorize($user);
            $lead = Lead::visibleTo($user)->lockForUpdate()->findOrFail($leadId);
            Gate::forUser($user)->authorize('update', $lead);
            $existing = FollowUp::where('created_by', $user->id)->where('api_request_id', $requestId)->first();
            if ($existing) {
                abort_unless(hash_equals((string) $existing->api_payload_hash, $hash), 409, 'This request ID was already used with different details.');

                return ['data' => (new FollowUpResource($existing))->resolve(), 'meta' => ['replayed' => true]];
            }
            abort_if($lead->isClosed(), 422, 'Reopen the lead before scheduling another follow-up.');
            abort_unless($due->isFuture(), 422, 'Choose a future follow-up time.');
            $responsible = User::find($data['responsible_id']);
            abort_unless($responsible && $responsible->can('view', $lead), 422, 'Choose an active member with access to this lead.');
            $followUp = new FollowUp;
            $followUp->responsible_id = $responsible->id;
            $followUp->created_by = $user->id;
            $followUp->due_at = $due;
            $followUp->notes = $data['notes'];
            $followUp->status = 'pending';
            $followUp->api_request_id = $requestId;
            $followUp->api_payload_hash = $hash;
            $lead->followUps()->save($followUp);
            $lead->recordChange('follow_up', null, 'Scheduled #'.$followUp->id.' for '.$responsible->name.' at '.$due->copy()->timezone(config('crm.timezone'))->format('d M Y H:i'), $user->id);
            if ($responsible->id !== $user->id) {
                $responsible->notify(new CrmNotification('Follow-up assigned', 'A follow-up has been assigned to you.', $lead->id));
            }

            return ['data' => (new FollowUpResource($followUp))->resolve(), 'meta' => ['replayed' => false]];
        });
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateFollowUp(User $user, int $leadId, int $followUpId, array $input): array
    {
        $this->authorize($user);
        $data = Validator::make($input, ['status' => ['required', Rule::in(['completed', 'cancelled'])]])->validate();

        return DB::transaction(function () use ($user, $leadId, $followUpId, $data): array {
            $lead = Lead::visibleTo($user)->lockForUpdate()->findOrFail($leadId);
            Gate::forUser($user)->authorize('update', $lead);
            $followUp = $lead->followUps()->lockForUpdate()->findOrFail($followUpId);
            if ($followUp->status !== $data['status']) {
                abort_unless($followUp->status === 'pending', 409, 'This follow-up is already closed with a different status.');
                $followUp->status = $data['status'];
                $followUp->closed_at = now();
                $followUp->save();
                $lead->recordChange('follow_up', 'Pending #'.$followUp->id, ucfirst($data['status']), $user->id);
                if ($followUp->responsible_id !== $user->id) {
                    $followUp->responsible->notify(new CrmNotification('Follow-up '.$data['status'], 'A follow-up assigned to you was '.$data['status'].'.', $lead->id));
                }
            }

            return ['data' => (new FollowUpResource($followUp))->resolve()];
        });
    }
}
