<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_furnitures', function (Blueprint $table) {
            $table->decimal('admin_price', 12, 2)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('user_furnitures', function (Blueprint $table) {
            $table->dropColumn('admin_price');
        });
    }
};
