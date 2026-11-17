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
        Schema::create('customer_carts', function (Blueprint $table) {
            $table->foreignId('customer_id')->primary()->constrained('customers')->cascadeOnDelete();
            $table->json('items');
            $table->timestamps();
        });

        Schema::create('customer_cart_merges', function (Blueprint $table) {
            $table->string('token', 64)->primary();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_cart_merges');
        Schema::dropIfExists('customer_carts');
    }
};
