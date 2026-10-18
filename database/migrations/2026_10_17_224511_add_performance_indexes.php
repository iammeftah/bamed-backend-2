<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Postgres does not index foreign keys on its own: these are hit on every product query. */
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->index('product_color_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['product_color_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['created_at']);
        });
    }
};