<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_furniture_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_furniture_id');
            $table->string('image_url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('user_furniture_id')->references('id')->on('user_furnitures')->onDelete('cascade');
            $table->index('user_furniture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_furniture_images');
    }
};