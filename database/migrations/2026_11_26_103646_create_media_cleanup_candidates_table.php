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
        Schema::create('media_cleanup_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('path', 1024);
            $table->string('path_hash', 64)->unique();
            $table->unsignedBigInteger('last_modified');
            $table->timestamp('unreferenced_since');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_cleanup_candidates');
    }
};
