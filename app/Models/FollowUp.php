<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'closed_at' => 'datetime', 'reminder_queued_at' => 'datetime', 'lead_id' => 'integer', 'responsible_id' => 'integer', 'created_by' => 'integer'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
