<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Admin;

/** Writes the activity log. The actor always comes from the token, never from the request body. */
class ActivityLogger
{
    /** Only the latest entries are kept. */
    private const KEEP = 500;

    public function record(
        ?Admin $actor,
        string $action,
        string $entity,
        string|int $entityId,
        string $summary,
    ): void {
        ActivityLog::create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'Système',
            'action' => $action,
            'entity' => $entity,
            'entity_id' => (string) $entityId,
            'summary' => mb_substr($summary, 0, 500),
        ]);

        $cut = ActivityLog::query()
            ->orderByDesc('id')
            ->offset(self::KEEP)
            ->limit(1)
            ->value('id');

        if ($cut) {
            ActivityLog::where('id', '<=', $cut)->delete();
        }
    }
}