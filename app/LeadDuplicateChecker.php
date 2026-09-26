<?php

namespace App;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class LeadDuplicateChecker
{
    /**
     * @param array{
     *     email?: string|null,
     *     phone?: string|null,
     *     wedding_location?: string|null,
     *     wedding_start_date?: string|null,
     *     wedding_end_date?: string|null
     * } $data
     */
    public function check(Lead $lead, array $data, bool $confirmed): void
    {
        if ($lead->exists) {
            $unchanged = true;

            foreach ([
                'email',
                'phone',
                'wedding_location',
                'wedding_start_date',
                'wedding_end_date',
            ] as $field) {
                $before = in_array($field, [
                    'wedding_start_date',
                    'wedding_end_date',
                ], true)
                    ? $lead->$field?->format('Y-m-d')
                    : $lead->$field;

                if (($data[$field] ?? null) !== $before) {
                    $unchanged = false;
                }
            }

            if ($unchanged) {
                return;
            }
        }

        $phone = Lead::normalizePhone($data['phone'] ?? null);

        $matches = Lead::withTrashed()
            ->where(function (Builder $query) use ($data, $phone): void {
                $query->whereRaw('1 = 0');

                if (! empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }

                if ($phone) {
                    $query->orWhere('normalized_phone', $phone);
                }
            })
            ->when(
                $lead->exists,
                fn (Builder $query) => $query->where('id', '!=', $lead->id)
            )
            ->get();

        foreach ($matches as $match) {
            $known = ! empty($data['wedding_location'])
                && $match->wedding_location
                && ! empty($data['wedding_start_date'])
                && ! empty($data['wedding_end_date'])
                && $match->wedding_start_date
                && $match->wedding_end_date;

            if (! $known) {
                if (! $confirmed) {
                    throw ValidationException::withMessages([
                        'confirm_duplicate' => 'A matching contact exists, but wedding details are incomplete. Review the enquiry and confirm before creating a separate lead.',
                    ]);
                }

                continue;
            }

            $differentLocation = mb_strtolower(trim($data['wedding_location']))
                !== mb_strtolower(trim($match->wedding_location));

            $differentDates = $data['wedding_start_date']
                !== $match->wedding_start_date->format('Y-m-d')
                || $data['wedding_end_date']
                !== $match->wedding_end_date->format('Y-m-d');

            if (! $differentLocation || ! $differentDates) {
                throw ValidationException::withMessages([
                    'email' => 'This contact already has an enquiry. Both wedding location and dates must differ for a separate lead; otherwise update the existing lead or ask an administrator.',
                ]);
            }
        }
    }
}