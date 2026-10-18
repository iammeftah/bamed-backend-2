<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Sanctum::actingAs(Admin::create([
            'name' => 'Mehdi', 'phone' => '+212652403812',
            'password' => 'secret123', 'active' => true,
        ]));
    }

    private function upload(string $name = 'photo.png'): string
    {
        return $this->postJson('/api/admin/uploads', [
            'file' => UploadedFile::fake()->image($name, 2400, 1600),
        ])->assertCreated()->json('url');
    }

    private function productBody(int $categoryId, array $colors): array
    {
        return [
            'name' => 'Chemise', 'categoryId' => $categoryId, 'price' => 100,
            'stock' => 5, 'lowStockAt' => 1, 'sizes' => ['S'], 'active' => true,
            'colors' => $colors,
        ];
    }

    public function test_upload_is_converted_to_webp_and_resized(): void
    {
        $url = $this->upload();

        $this->assertStringEndsWith('.webp', $url);

        $file = Storage::disk('public')->allFiles('products')[0];
        $size = getimagesizefromstring(Storage::disk('public')->get($file));

        $this->assertSame(IMAGETYPE_WEBP, $size[2]);
        $this->assertSame(1600, $size[0]);
    }

    public function test_a_non_image_is_refused(): void
    {
        $this->postJson('/api/admin/uploads', [
            'file' => UploadedFile::fake()->createWithContent('x.png', 'not an image'),
        ])->assertStatus(422);
    }

    public function test_files_are_deleted_with_the_product(): void
    {
        $c = Category::create(['name' => 'Chemises', 'slug' => 'chemises', 'description' => '', 'size_scale' => 'letters']);
        $url = $this->upload();

        $id = $this->postJson('/api/admin/products', $this->productBody($c->id, [
            ['name' => 'Marine', 'hex' => '#1f2a44', 'images' => [['src' => $url, 'alt' => '']]],
        ]))->assertCreated()->json('id');

        $this->assertCount(1, Storage::disk('public')->allFiles('products'));

        $this->deleteJson("/api/admin/products/{$id}")->assertNoContent();

        $this->assertCount(0, Storage::disk('public')->allFiles('products'));
    }

    public function test_removed_photos_are_deleted_on_update_but_static_paths_are_ignored(): void
    {
        $c = Category::create(['name' => 'Chemises', 'slug' => 'chemises', 'description' => '', 'size_scale' => 'letters']);
        $keep = $this->upload('a.png');
        $drop = $this->upload('b.png');

        $id = $this->postJson('/api/admin/products', $this->productBody($c->id, [
            ['name' => 'Marine', 'hex' => '#1f2a44', 'images' => [
                ['src' => $keep, 'alt' => ''],
                ['src' => $drop, 'alt' => ''],
                ['src' => '/images/products/x/y.jpg', 'alt' => ''],
            ]],
        ]))->assertCreated()->json('id');

        $this->putJson("/api/admin/products/{$id}", $this->productBody($c->id, [
            ['name' => 'Marine', 'hex' => '#1f2a44', 'images' => [['src' => $keep, 'alt' => '']]],
        ]))->assertOk();

        $this->assertCount(1, Storage::disk('public')->allFiles('products'));
    }
}