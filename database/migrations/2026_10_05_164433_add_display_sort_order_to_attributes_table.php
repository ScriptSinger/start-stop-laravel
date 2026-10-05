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
        Schema::table('attributes', function (Blueprint $table) {
            // Порядок на карточке и странице товара (oc_attribute), отдельно от
            // порядка в фильтре (sort_order, из OCFilter): на старом сайте они разные.
            $table->unsignedInteger('display_sort_order')->default(0)->after('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('display_sort_order');
        });
    }
};
