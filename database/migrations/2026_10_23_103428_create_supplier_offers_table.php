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
        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_code');
            $table->string('name');
            $table->decimal('cost', 12, 2);
            $table->unsignedInteger('quantity')->nullable();
            $table->boolean('in_price')->default(true);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_offers');
    }
};
