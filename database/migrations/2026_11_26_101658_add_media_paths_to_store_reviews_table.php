<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('store_reviews', function (Blueprint $table) {
            $table->string('avatar_path')->nullable();
            $table->json('photo_paths')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_reviews', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'photo_paths']);
        });
    }
};
