<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('user_furniture_id');
            $table->uuid('product_id');
            $table->enum('status', ['pending', 'checking', 'rejected', 'approved'])->default('pending');
            $table->uuid('admin_id')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('user_furniture_id')->references('id')->on('user_furnitures')->onDelete('restrict');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('admin_id')->references('id')->on('admins')->onDelete('set null');
            $table->index('user_id');
            $table->index('status');
            $table->index('product_id');
            $table->index('user_furniture_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};