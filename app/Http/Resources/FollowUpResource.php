<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'responsible_id' => $this->responsible_id,
            'responsible_name' => $this->responsible?->name,
            'due_at' => $this->due_at->copy()->utc()->toIso8601String(),
            'due_at_local' => $this->due_at->copy()->timezone(config('crm.timezone'))->toIso8601String(),
            'timezone' => config('crm.timezone'),
            'notes' => $this->notes,
            'status' => $this->status,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'url' => route('leads.show', $this->lead_id),
        ];
    }
}
