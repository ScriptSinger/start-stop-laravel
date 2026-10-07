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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // SEO из ocStore (oc_information_description): заголовок H1 на
            // странице, <title> и description.
            $table->string('heading')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('slug')->unique();
            $table->longText('description')->nullable();
            // Соответствует `bottom` в oc_information: такие страницы показывались
            // и в верхней тонкой полоске, и в футере старого сайта.
            $table->boolean('show_in_top')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
