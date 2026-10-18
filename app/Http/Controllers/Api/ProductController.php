<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockProductResource;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\ImageStorage;
use App\Services\SkuGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    private const WITH = ['category', 'brand', 'colors.images'];

    public function __construct(
        private ActivityLogger $log,
        private SkuGenerator $skus,
        private ImageStorage $images,
    ) {}

    /** Public: GET /products (active products only) */
    public function index(Request $request)
    {
        $query = Product::query()->where('active', true)->with(self::WITH);

        $term = str_replace(['%', '_', '\\'], '', trim((string) $request->query('query', '')));
        if ($term !== '') {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($term).'%']);
        }

        $type = $request->query('type');
        if (is_string($type) && $type !== '') {
            $query->whereHas('category', fn ($c) => $c->where('slug', $type));
        }

        $brand = $request->query('brand');
        if (is_string($brand) && $brand !== '') {
            $query->whereHas('brand', fn ($b) => $b->where('slug', $brand));
        }

        if (is_numeric($request->query('minPrice'))) {
            $query->where('price', '>=', (float) $request->query('minPrice'));
        }
        if (is_numeric($request->query('maxPrice'))) {
            $query->where('price', '<=', (float) $request->query('maxPrice'));
        }
        if ($request->boolean('sale')) {
            $query->whereNotNull('old_price');
        }
        if ($request->boolean('featured')) {
            $query->where('featured', true);
        }
        // Same rule as the "Nouveau" tag: created in the last 30 days
        if ($request->boolean('new')) {
            $query->where('created_at', '>', now()->subDays(30));
        }

        match ($request->query('sort')) {
            'prix-asc' => $query->orderBy('price')->orderBy('id'),
            'prix-desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $size = min(100, max(1, (int) $request->query('size', 24)));
        $page = max(1, (int) $request->query('page', 1));

        return ProductResource::collection($query->forPage($page, $size)->get());
    }

    /** Public: GET /products/{id} */
    public function show(string $id)
    {
        $product = Product::query()->where('active', true)->with(self::WITH)->find($id);

        if (! $product) {
            return response()->json(['message' => 'Introuvable.'], 404);
        }

        return new ProductResource($product);
    }

    /** Admin: GET /admin/products */
    public function adminIndex(Request $request)
    {
        $size = min(500, max(1, (int) $request->query('size', $request->query('per_page', 100))));
        $page = max(1, (int) $request->query('page', 1));

        $products = Product::query()
            ->with(self::WITH)
            ->orderByDesc('id')
            ->forPage($page, $size)
            ->get();

        return StockProductResource::collection($products);
    }

    public function store(ProductRequest $request)
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($request, $data) {
            $product = Product::create([
                'sku' => $data['sku'] ?? $this->skus->next(),
                'name' => $data['name'],
                'brand_id' => $data['brandId'] ?? null,
                'category_id' => (int) $data['categoryId'],
                'price' => $data['price'],
                'old_price' => $data['oldPrice'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'stock' => (int) $data['stock'],
                'low_stock_at' => (int) $data['lowStockAt'],
                'sizes' => array_values($data['sizes'] ?? []),
                'active' => (bool) $data['active'],
            ]);

            $product->syncColors($data['colors'] ?? []);

            $this->log->record(
                $request->user(), 'create', 'product', $product->id,
                "Produit « {$product->name} » créé (stock {$product->stock})",
            );

            return $product;
        });

        $product->load(self::WITH);

        return (new StockProductResource($product))->response()->setStatusCode(201);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Photos the product had before this edit
        $before = $product->imageUrls();

        DB::transaction(function () use ($request, $data, $product) {
            // Stock is never changed here: only through /stock and sales
            $product->update([
                'sku' => $data['sku'] ?? $product->sku,
                'name' => $data['name'],
                'brand_id' => $data['brandId'] ?? null,
                'category_id' => (int) $data['categoryId'],
                'price' => $data['price'],
                'old_price' => $data['oldPrice'] ?? null,
                'cost' => $data['cost'] ?? 0,
                'low_stock_at' => (int) $data['lowStockAt'],
                'sizes' => array_values($data['sizes'] ?? []),
                'active' => (bool) $data['active'],
            ]);

            $product->syncColors($data['colors'] ?? []);

            $this->log->record(
                $request->user(), 'update', 'product', $product->id,
                "Produit « {$product->name} » modifié",
            );
        });

        // Files of removed photos or colours are deleted (kept if still referenced)
        $this->images->purgeUnused($before);

        $product->load(self::WITH);

        return new StockProductResource($product);
    }

    public function destroy(Request $request, Product $product)
    {
        $name = $product->name;
        $id = $product->id;
        $urls = $product->imageUrls();

        $product->delete();

        // Colours and photo rows are gone (cascade): now remove the files
        $this->images->purgeUnused($urls);

        $this->log->record(
            $request->user(), 'delete', 'product', $id,
            "Produit « {$name} » supprimé",
        );

        return response()->noContent();
    }
}