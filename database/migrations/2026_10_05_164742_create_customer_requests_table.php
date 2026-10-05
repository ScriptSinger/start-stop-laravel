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
        // Заявки с сайта: «Заказать звонок», вопросы (oc_uni_request старого сайта).
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->text('admin_comment')->nullable();
            $table->boolean('is_processed')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_requests');
    }
};
