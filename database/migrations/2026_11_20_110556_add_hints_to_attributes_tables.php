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
        // Подсказка к характеристике целиком («Полярность») и к отдельному
        // значению («EFB», «AGM»): показывается в окне по значку «i» в карточке
        // товара. guide_id/guide_anchor — раздел гайда для ссылки «Больше советов».
        Schema::table('attributes', function (Blueprint $table) {
            $table->text('hint')->nullable()->after('name');
            $table->foreignId('guide_id')->nullable()->after('hint')->constrained()->nullOnDelete();
            $table->string('guide_anchor')->nullable()->after('guide_id');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->text('hint')->nullable()->after('value');
            $table->string('guide_anchor')->nullable()->after('hint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attribute_values', function (Blueprint $table) {
            $table->dropColumn(['hint', 'guide_anchor']);
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guide_id');
            $table->dropColumn(['hint', 'guide_anchor']);
        });
    }
};
