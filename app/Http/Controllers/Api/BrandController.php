<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Http\Resources\StoreBrandResource;
use App\Models\Brand;
use App\Services\ActivityLogger;
use App\Services\ImageStorage;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private ActivityLogger $log,
        private ImageStorage $images,
    ) {}

    /** Public: GET /brands (with the number of active products, in one query) */
    public function index()
    {
        return StoreBrandResource::collection(
            Brand::query()
                ->withCount(['products as active_products_count' => fn ($q) => $q->where('active', true)])
                ->orderBy('name')
                ->get()
        );
    }

    /** Admin: GET /admin/brands */
    public function adminIndex()
    {
        return BrandResource::collection(
            Brand::query()->withCount('products')->orderBy('name')->get()
        );
    }

    public function store(BrandRequest $request)
    {
        $brand = Brand::create($this->data($request));

        $this->log->record(
            $request->user(), 'create', 'brand', $brand->id,
            "Marque « {$brand->name} » créée",
        );

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function update(BrandRequest $request, Brand $brand)
    {
        $before = $brand->logo;

        $brand->update($this->data($request));

        // The old logo file goes away when it was replaced or removed
        if ($before && $before !== $brand->logo) {
            $this->images->purgeUnused([$before]);
        }

        $this->log->record(
            $request->user(), 'update', 'brand', $brand->id,
            "Marque « {$brand->name} » modifiée",
        );

        return new BrandResource($brand->loadCount('products'));
    }

    public function destroy(Request $request, Brand $brand)
    {
        $name = $brand->name;
        $id = $brand->id;
        $logo = $brand->logo;

        // Products keep existing: brand_id becomes null (nullOnDelete)
        $brand->delete();

        if ($logo) {
            $this->images->purgeUnused([$logo]);
        }

        $this->log->record(
            $request->user(), 'delete', 'brand', $id,
            "Marque « {$name} » supprimée",
        );

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function data(BrandRequest $request): array
    {
        $v = $request->validated();

        return [
            'name' => $v['name'],
            'slug' => $v['slug'],
            'logo' => $v['logo'] ?? null,
        ];
    }
}