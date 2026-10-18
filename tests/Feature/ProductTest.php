<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Admin
    {
        $admin = Admin::create([
            'name' => 'Mehdi', 'phone' => '+212652403812',
            'password' => 'secret123', 'active' => true,
        ]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Chemises', 'slug' => 'chemises',
            'description' => '', 'size_scale' => 'letters',
        ]);
    }

    private function product(Category $c, array $o = []): Product
    {
        return Product::create($o + [
            'sku' => 'BAM-'.random_int(2000, 9999),
            'name' => 'Chemise',
            'category_id' => $c->id,
            'price' => 100,
            'cost' => 40,
            'stock' => 5,
            'sizes' => ['S'],
            'active' => true,
        ]);
    }

    public function test_public_list_hides_private_fields_and_inactive_products(): void
    {
        $c = $this->category();
        $this->product($c, ['sku' => 'BAM-1001']);
        $this->product($c, ['sku' => 'BAM-1002', 'active' => false]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.type', 'chemises')
            ->assertJsonPath('0.tag', 'Nouveau')
            ->assertJsonMissingPath('0.cost')
            ->assertJsonMissingPath('0.stock')
            ->assertJsonMissingPath('0.sku');
    }

    public function test_public_show_404_for_inactive(): void
    {
        $p = $this->product($this->category(), ['active' => false]);

        $this->getJson("/api/products/{$p->id}")->assertNotFound();
    }

    public function test_admin_routes_need_a_token(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->postJson('/api/admin/products', [])->assertUnauthorized();
    }

    public function test_admin_creates_a_product_with_auto_sku_and_log(): void
    {
        $this->signIn();
        $c = $this->category();

        $this->postJson('/api/admin/products', [
            'name' => 'Chemise Oxford',
            'categoryId' => $c->id,
            'price' => 490,
            'cost' => 250,
            'stock' => 20,
            'lowStockAt' => 10,
            'sizes' => ['S', 'M'],
            'active' => true,
            'colors' => [[
                'name' => 'Marine', 'hex' => '#1F2A44',
                'images' => [['src' => '/images/a.jpg', 'alt' => '']],
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('sku', 'BAM-1001')
            ->assertJsonPath('stock', 20)
            ->assertJsonPath('colors.0.hex', '#1f2a44');

        $this->assertDatabaseHas('activity_logs', ['action' => 'create', 'entity' => 'product']);
    }

    public function test_size_outside_the_category_scale_is_refused(): void
    {
        $this->signIn();
        $c = $this->category();

        $this->postJson('/api/admin/products', [
            'name' => 'X', 'categoryId' => $c->id, 'price' => 10, 'stock' => 1,
            'lowStockAt' => 1, 'sizes' => ['41'], 'active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('sizes');
    }

    public function test_stock_adjustment(): void
    {
        $this->signIn();
        $p = $this->product($this->category(), ['stock' => 5]);

        $this->postJson("/api/admin/products/{$p->id}/stock", ['delta' => -6])
            ->assertStatus(422);

        $this->postJson("/api/admin/products/{$p->id}/stock", ['delta' => 3, 'reason' => 'réassort'])
            ->assertOk()
            ->assertJsonPath('stock', 8);

        $this->assertDatabaseHas('activity_logs', ['action' => 'stock']);
    }
}