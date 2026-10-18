<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug')->unique();
            // A path on this site (/images/brands/x.svg) or an uploaded file (/storage/products/x.webp)
            $table->string('logo', 2048)->nullable();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            // Deleting a brand keeps its products, they just lose the brand
            $table->foreignId('brand_id')->nullable()->after('name')
                ->constrained('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};