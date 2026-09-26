<?php

namespace App;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateLead
{
    public function __construct(
        private LeadDuplicateChecker $duplicateChecker
    ) {}

    /**
     * @param array{
     *     name: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     client_location?: string|null,
     *     wedding_location?: string|null,
     *     wedding_start_date?: string|null,
     *     wedding_end_date?: string|null,
     *     temperature?: string|null,
     *     status: string,
     *     lost_reason?: string|null,
     *     confirm_duplicate?: mixed,
     *     notes?: string|null
     * } $data
     */
    public function create(
        array $data,
        User $user,
        string $source = 'manual',
        string $description = 'Manual enquiry'
    ): Lead {
        return DB::transaction(function () use (
            $data,
            $user,
            $source,
            $description
        ): Lead {
            $lead = new Lead;

            $this->duplicateChecker->check(
                $lead,
                $data,
                ! empty($data['confirm_duplicate'])
            );

            foreach ([
                'name',
                'email',
                'phone',
                'client_location',
                'wedding_location',
                'wedding_start_date',
                'wedding_end_date',
                'temperature',
                'status',
            ] as $field) {
                $lead->$field = $data[$field] ?? null;
            }

            $lead->lost_reason = $data['status'] === 'lost'
                ? ($data['lost_reason'] ?? null)
                : null;

            $lead->owner_id = $user->id;
            $lead->created_by = $user->id;
            $lead->source = $source;
            $lead->save();

            $lead->recordChange('created', null, $description, $user->id);

            if (! empty($data['notes'])) {
                $activity = new Activity;
                $activity->user_id = $user->id;
                $activity->type = 'discussion';
                $activity->occurred_at = now();
                $activity->notes = $data['notes'];

                $lead->activities()->save($activity);
            }

            return $lead;
        });
    }
}