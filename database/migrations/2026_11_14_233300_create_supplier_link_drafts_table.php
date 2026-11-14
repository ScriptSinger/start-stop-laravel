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
        Schema::create('supplier_link_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('import_id', 40);
            $table->foreignId('admin_id')->constrained('moonshine_users')->cascadeOnDelete();
            $table->unsignedBigInteger('offer_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['import_id', 'admin_id', 'offer_id'], 'supplier_link_drafts_owner_offer_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_link_drafts');
    }
};
