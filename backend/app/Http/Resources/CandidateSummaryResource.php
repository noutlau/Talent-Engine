<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rank' => $this->rank_position !== null ? (int) $this->rank_position : null,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'city' => $this->city,
            'country' => $this->country,
            'score' => $this->score,
            'category' => CandidateResource::enum($this->category) + ['action' => $this->category->action()],
            'availability' => CandidateResource::enum($this->availability),
            'flags' => $this->flags ?? [],
            'review_status' => CandidateResource::enum($this->review_status),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
