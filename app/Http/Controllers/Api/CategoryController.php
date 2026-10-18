<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\StoreCategoryResource;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private ActivityLogger $log) {}

    /** Public: GET /categories (with the number of active products, in one query) */
    public function index()
    {
        return StoreCategoryResource::collection(
            Category::query()
                ->withCount(['products as active_products_count' => fn ($q) => $q->where('active', true)])
                ->orderBy('id')
                ->get()
        );
    }

    /** Admin: GET /admin/categories */
    public function adminIndex()
    {
        return CategoryResource::collection(Category::orderBy('id')->get());
    }

    public function store(CategoryRequest $request)
    {
        $category = Category::create($this->data($request));

        $this->log->record(
            $request->user(), 'create', 'category', $category->id,
            "Catégorie « {$category->name} » créée",
        );

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($this->data($request));

        $this->log->record(
            $request->user(), 'update', 'category', $category->id,
            "Catégorie « {$category->name} » modifiée",
        );

        return new CategoryResource($category);
    }

    public function destroy(Request $request, Category $category)
    {
        $used = $category->productsCount();

        if ($used > 0) {
            $s = $used > 1 ? 's' : '';
            $nt = $used > 1 ? 'nt' : '';

            return response()->json([
                'message' => "Impossible : {$used} produit{$s} utilise{$nt} cette catégorie.",
            ], 422);
        }

        $category->delete();

        $this->log->record(
            $request->user(), 'delete', 'category', $category->id,
            "Catégorie « {$category->name} » supprimée",
        );

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function data(CategoryRequest $request): array
    {
        $v = $request->validated();

        return [
            'name' => $v['name'],
            'slug' => $v['slug'],
            'description' => $v['description'] ?? '',
            'size_scale' => $v['sizeScale'],
        ];
    }
}