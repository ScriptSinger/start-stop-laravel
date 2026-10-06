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
        Schema::table('pages', function (Blueprint $table) {
            // SEO из ocStore (oc_information_description): заголовок H1 на
            // странице, <title> и description.
            $table->string('heading')->nullable()->after('title');
            $table->string('meta_title')->nullable()->after('heading');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['heading', 'meta_title', 'meta_description']);
        });
    }
};
