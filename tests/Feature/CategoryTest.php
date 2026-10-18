<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): Admin
    {
        $admin = Admin::create([
            'name' => 'Mehdi',
            'phone' => '+212652403812',
            'password' => 'secret123',
            'active' => true,
        ]);

        Sanctum::actingAs($admin);

        return $admin;
    }

    private function category(string $name = 'Hoodies', string $scale = 'letters'): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => '',
            'size_scale' => $scale,
        ]);
    }

    public function test_public_list_only_exposes_store_fields(): void
    {
        $this->category('Hoodies');

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('0.id', 'hoodies')
            ->assertJsonPath('0.label', 'Hoodies')
            ->assertJsonPath('0.sizeScale', 'letters')
            ->assertJsonMissingPath('0.slug')
            ->assertJsonMissingPath('0.description');
    }

    public function test_admin_routes_need_a_token(): void
    {
        $this->getJson('/api/admin/categories')->assertUnauthorized();
        $this->postJson('/api/admin/categories', [])->assertUnauthorized();
    }

    public function test_admin_can_create_a_category(): void
    {
        $this->signIn();

        $this->postJson('/api/admin/categories', [
            'name' => 'Chemises Lin',
            'description' => 'Lin léger',
            'sizeScale' => 'letters',
        ])
            ->assertCreated()
            ->assertJsonPath('slug', 'chemises-lin')
            ->assertJsonPath('sizeScale', 'letters');

        $this->assertDatabaseHas('categories', ['slug' => 'chemises-lin']);

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('0.name', 'Chemises Lin');
    }

    public function test_duplicate_name_is_refused(): void
    {
        $this->signIn();
        $this->category('Hoodies');

        // The uniqueness rule lives on the slug (built from the name), so the error comes back under "slug".
        // The dashboard shows the first field error, whatever its key.
        $this->postJson('/api/admin/categories', ['name' => 'hoodies', 'sizeScale' => 'letters'])
            ->assertStatus(422)
            ->assertJsonPath('errors.slug.0', 'Une catégorie porte déjà ce nom.');
    }

    public function test_unknown_size_scale_is_refused(): void
    {
        $this->signIn();

        $this->postJson('/api/admin/categories', ['name' => 'Casquettes', 'sizeScale' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sizeScale');
    }

    public function test_update_can_keep_its_own_name_and_can_rename(): void
    {
        $this->signIn();
        $c = $this->category('Hoodies');

        $this->putJson("/api/admin/categories/{$c->id}", [
            'name' => 'Hoodies',
            'description' => 'Nouvelle description',
            'sizeScale' => 'letters',
        ])->assertOk()->assertJsonPath('description', 'Nouvelle description');

        $this->putJson("/api/admin/categories/{$c->id}", [
            'name' => 'Sweats',
            'sizeScale' => 'letters',
        ])->assertOk()->assertJsonPath('slug', 'sweats');
    }

    public function test_admin_can_delete_a_category(): void
    {
        $this->signIn();
        $c = $this->category('Hoodies');

        $this->deleteJson("/api/admin/categories/{$c->id}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $c->id]);
    }
}