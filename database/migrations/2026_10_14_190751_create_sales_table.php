<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_id, category_id and sold_by_id are plain columns on purpose (no foreign key):
     * sales history is kept as it was even if the product, category or admin is deleted.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('product_name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->unsignedBigInteger('sold_by_id')->nullable()->index();
            $table->string('sold_by_name');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};