<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private ActivityLogger $log) {}

    /**
     * Records one in-shop sale. Everything happens in one transaction:
     * check, snapshot name/price/cost, decrement stock, write the log.
     *
     * @param  array{productId: int|string, qty: int|string, soldById: int|string}  $data
     */
    public function record(Admin $actor, array $data): Sale
    {
        return DB::transaction(function () use ($actor, $data) {
            $product = Product::query()->lockForUpdate()->find($data['productId']);

            if (! $product || ! $product->active) {
                throw ValidationException::withMessages([
                    'productId' => 'Produit introuvable ou inactif.',
                ]);
            }

            $qty = (int) $data['qty'];

            if ($qty > $product->stock) {
                throw ValidationException::withMessages([
                    'qty' => "Stock insuffisant ({$product->stock} disponible).",
                ]);
            }

            $seller = Admin::find($data['soldById']);

            if (! $seller || ! $seller->active) {
                throw ValidationException::withMessages([
                    'soldById' => 'Choisissez un vendeur actif.',
                ]);
            }

            $sale = Sale::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category_id' => $product->category_id,
                'qty' => $qty,
                'unit_price' => $product->price,
                'unit_cost' => $product->cost,
                'total' => $product->price * $qty,
                'sold_by_id' => $seller->id,
                'sold_by_name' => $seller->name,
            ]);

            $product->decrement('stock', $qty);

            $this->log->record(
                $actor,
                'sale',
                'sale',
                $sale->id,
                sprintf('Vente : %d × %s (%s) par %s', $qty, $product->name, $this->mad($sale->total), $seller->name),
            );

            return $sale;
        });
    }

    /** 1290 -> "1 290 MAD" (same format as the dashboard). */
    private function mad(float $amount): string
    {
        return number_format(round($amount), 0, '', "\u{00A0}")."\u{00A0}MAD";
    }
}