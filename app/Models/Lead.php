<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'requirement_discussed' => 'Requirement Discussed', 'proposal_sent' => 'Proposal Sent', 'negotiation' => 'Negotiation', 'won' => 'Won', 'lost' => 'Lost'];

    public const TEMPERATURES = ['hot' => 'Hot', 'warm' => 'Warm', 'cold' => 'Cold'];

    protected function casts(): array
    {
        return ['wedding_start_date' => 'date', 'wedding_end_date' => 'date', 'owner_id' => 'integer', 'created_by' => 'integer'];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone ?? '');

        return $digits === '' ? null : (str_starts_with($digits, '00') ? substr($digits, 2) : $digits);
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            $lead->normalized_phone = self::normalizePhone($lead->phone);
            $lead->email = $lead->email ? strtolower(trim($lead->email)) : null;
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdministrator() ? $query : $query->where(fn (Builder $q) => $q->where('owner_id', $user->id)->orWhereHas('collaborators', fn (Builder $c) => $c->where('users.id', $user->id)));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lead_collaborators')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(LeadChange::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['won', 'lost'], true);
    }

    public function recordChange(string $field, mixed $before, mixed $after, ?int $actor): void
    {
        $change = new LeadChange;
        $change->user_id = $actor;
        $change->field = $field;
        $change->old_value = is_array($before) ? implode(', ', $before) : $before;
        $change->new_value = is_array($after) ? implode(', ', $after) : $after;
        $this->changes()->save($change);
    }
}
