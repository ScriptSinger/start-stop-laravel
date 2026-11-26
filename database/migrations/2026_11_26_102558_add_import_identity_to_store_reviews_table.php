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
            $table->string('source_external_id')->nullable();
            $table->string('import_fingerprint', 64)->nullable()->index();
            $table->unique(['source', 'source_external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('store_reviews', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_external_id']);
            $table->dropIndex(['import_fingerprint']);
            $table->dropColumn(['source_external_id', 'import_fingerprint']);
        });
    }
};
