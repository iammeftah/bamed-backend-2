<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'at' => $this->created_at ? $this->created_at->getTimestamp() * 1000 : 0,
            'actorId' => (string) ($this->actor_id ?? ''),
            'actorName' => $this->actor_name,
            'action' => $this->action,
            'entity' => $this->entity,
            'entityId' => $this->entity_id,
            'summary' => $this->summary,
        ];
    }
}