<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    private const ENTITIES = ['product', 'category', 'admin', 'sale'];

    /** GET /admin/logs?actorId&entity&query&page&size  (newest first, 500 kept) */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        if (is_numeric($request->query('actorId'))) {
            $query->where('actor_id', (int) $request->query('actorId'));
        }

        $entity = $request->query('entity');
        if (is_string($entity) && in_array($entity, self::ENTITIES, true)) {
            $query->where('entity', $entity);
        }

        $term = str_replace(['%', '_', '\\'], '', trim((string) $request->query('query', '')));
        if ($term !== '') {
            $query->whereRaw('LOWER(summary) LIKE ?', ['%'.mb_strtolower($term).'%']);
        }

        $size = min(500, max(1, (int) $request->query('size', $request->query('per_page', 100))));
        $page = max(1, (int) $request->query('page', 1));

        return LogResource::collection(
            $query->orderByDesc('id')->forPage($page, $size)->get()
        );
    }
}