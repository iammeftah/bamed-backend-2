<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\LogResource;
use App\Http\Resources\SaleResource;
use App\Http\Resources\StockProductResource;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use App\Http\Resources\BrandResource;
use App\Models\Brand;



class DashboardController extends Controller
{
    private const LIMIT = 500;

    /** GET /admin/overview -> everything the dashboard needs, in ONE request. */
    public function index(Request $request)
    {
        $categories = Category::orderBy('id')->get();
        $brands = Brand::query()->withCount('products')->orderBy('name')->get();

        $products = Product::query()
            ->with(['colors.images'])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        // The super admin never appears in the shop team or in the seller list
        $admins = Admin::regular()->orderBy('id')->get();

        $sales = Sale::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $logs = ActivityLog::query()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories)->resolve($request),
            'brands' => BrandResource::collection($brands)->resolve($request),
            'products' => StockProductResource::collection($products)->resolve($request),
            'admins' => AdminResource::collection($admins)->resolve($request),
            'sales' => SaleResource::collection($sales)->resolve($request),
            'logs' => LogResource::collection($logs)->resolve($request),
        ]);
    }
}