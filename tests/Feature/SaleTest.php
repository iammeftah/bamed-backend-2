<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_decrements_stock_and_snapshots_prices(): void
    {
        $admin = Admin::create([
            'name' => 'Mehdi', 'phone' => '+212652403812',
            'password' => 'secret123', 'active' => true,
        ]);
        Sanctum::actingAs($admin);

        $c = Category::create([
            'name' => 'Polos', 'slug' => 'polos', 'description' => '', 'size_scale' => 'letters',
        ]);
        $p = Product::create([
            'sku' => 'BAM-1001', 'name' => 'Polo', 'category_id' => $c->id,
            'price' => 100, 'cost' => 40, 'stock' => 5, 'sizes' => ['S'], 'active' => true,
        ]);

        $this->postJson('/api/admin/sales', [
            'productId' => $p->id, 'qty' => 2, 'soldById' => $admin->id,
        ])
            ->assertCreated()
            ->assertJsonPath('total', 200)
            ->assertJsonPath('soldByName', 'Mehdi');

        $this->assertSame(3, $p->fresh()->stock);

        $this->postJson('/api/admin/sales', [
            'productId' => $p->id, 'qty' => 9, 'soldById' => $admin->id,
        ])->assertStatus(422)->assertJsonValidationErrors('qty');

        $this->getJson('/api/admin/sales')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/admin/logs')->assertOk()->assertJsonPath('0.action', 'sale');
    }
}