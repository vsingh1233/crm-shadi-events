<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'client_location' => $this->client_location,
            'wedding_location' => $this->wedding_location,
            'wedding_start_date' => $this->wedding_start_date?->format('Y-m-d'),
            'wedding_end_date' => $this->wedding_end_date?->format('Y-m-d'),
            'status' => $this->status,
            'temperature' => $this->temperature,
            'source' => $this->source,
            'owner_id' => $this->owner_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'url' => route('leads.show', $this->resource),
        ];
    }
}