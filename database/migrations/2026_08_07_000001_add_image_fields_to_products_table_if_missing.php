<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image1')) {
                $table->string('image1')->nullable()->after('description');
            }
            if (! Schema::hasColumn('products', 'image2')) {
                $table->string('image2')->nullable()->after('image1');
            }
            if (! Schema::hasColumn('products', 'image3')) {
                $table->string('image3')->nullable()->after('image2');
            }
            if (! Schema::hasColumn('products', 'image4')) {
                $table->string('image4')->nullable()->after('image3');
            }
            if (! Schema::hasColumn('products', 'image5')) {
                $table->string('image5')->nullable()->after('image4');
            }
            if (! Schema::hasColumn('products', 'image6')) {
                $table->string('image6')->nullable()->after('image5');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image6')) {
                $table->dropColumn(['image6']);
            }
            if (Schema::hasColumn('products', 'image5')) {
                $table->dropColumn(['image5']);
            }
            if (Schema::hasColumn('products', 'image4')) {
                $table->dropColumn(['image4']);
            }
            if (Schema::hasColumn('products', 'image3')) {
                $table->dropColumn(['image3']);
            }
            if (Schema::hasColumn('products', 'image2')) {
                $table->dropColumn(['image2']);
            }
            if (Schema::hasColumn('products', 'image1')) {
                $table->dropColumn(['image1']);
            }
        });
    }
};
