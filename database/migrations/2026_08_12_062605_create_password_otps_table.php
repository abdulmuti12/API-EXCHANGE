<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_otps', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->string('otp', 6);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
            $table->index('otp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_otps');
    }
};
