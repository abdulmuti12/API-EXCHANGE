<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image1')->nullable()->after('description');
            $table->string('image2')->nullable()->after('image1');
            $table->string('image3')->nullable()->after('image2');
            $table->string('image4')->nullable()->after('image3');
            $table->string('image5')->nullable()->after('image4');
            $table->string('image6')->nullable()->after('image5');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image1', 'image2', 'image3', 'image4', 'image5', 'image6']);
        });
    }
};
