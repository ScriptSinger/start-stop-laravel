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
        Schema::create('supplier_import_applications', function (Blueprint $table) {
            $table->string('import_id', 40)->primary();
            $table->timestamp('applied_at');
            $table->unsignedBigInteger('applied_by_id');
            $table->string('applied_by_name');
            $table->json('summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_import_applications');
    }
};
