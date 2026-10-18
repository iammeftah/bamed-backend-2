<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockRequest;
use App\Http\Resources\StockProductResource;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    /** POST /admin/products/{product}/stock  { delta, reason } */
    public function adjust(StockRequest $request, Product $product)
    {
        $delta = (int) $request->validated('delta');
        $reason = trim((string) $request->validated('reason', ''));

        $updated = DB::transaction(function () use ($request, $product, $delta, $reason) {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);

            if ($locked->stock + $delta < 0) {
                throw ValidationException::withMessages([
                    'delta' => 'Le stock ne peut pas être négatif.',
                ]);
            }

            $locked->update(['stock' => $locked->stock + $delta]);

            $this->log->record(
                $request->user(), 'stock', 'product', $locked->id,
                sprintf(
                    'Stock « %s » : %s%d%s',
                    $locked->name,
                    $delta > 0 ? '+' : '',
                    $delta,
                    $reason !== '' ? " ({$reason})" : '',
                ),
            );

            return $locked;
        });

        $updated->load(['category', 'colors.images']);

        return new StockProductResource($updated);
    }
}