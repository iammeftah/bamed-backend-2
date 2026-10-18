<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    /** GET /admin/sales?from&to&sellerId&query&page&size  (from/to in epoch ms) */
    public function index(Request $request)
    {
        $query = Sale::query();

        if (is_numeric($request->query('from'))) {
            $query->where('created_at', '>=', Carbon::createFromTimestampMs((int) $request->query('from')));
        }
        if (is_numeric($request->query('to'))) {
            $query->where('created_at', '<=', Carbon::createFromTimestampMs((int) $request->query('to')));
        }
        if (is_numeric($request->query('sellerId'))) {
            $query->where('sold_by_id', (int) $request->query('sellerId'));
        }

        $term = str_replace(['%', '_', '\\'], '', trim((string) $request->query('query', '')));
        if ($term !== '') {
            $query->whereRaw('LOWER(product_name) LIKE ?', ['%'.mb_strtolower($term).'%']);
        }

        $size = min(500, max(1, (int) $request->query('size', $request->query('per_page', 100))));
        $page = max(1, (int) $request->query('page', 1));

        return SaleResource::collection(
            $query->orderByDesc('created_at')->orderByDesc('id')->forPage($page, $size)->get()
        );
    }

    public function store(SaleRequest $request, SaleService $sales)
    {
        $sale = $sales->record($request->user(), $request->validated());

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }
}