<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 8);
            $table->string('route', 150);
            $table->unsignedSmallInteger('status')->index();
            $table->unsignedInteger('duration_ms');
            $table->string('ip', 45)->nullable()->index();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('class', 190);
            $table->string('message', 500);
            $table->string('location', 250);
            $table->string('method', 8)->nullable();
            $table->string('path', 150)->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('trace')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('request_logs');
    }
};