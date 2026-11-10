<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('vk_id')->nullable()->unique();
            $table->string('vk_email')->nullable();
        });

        if (! Schema::hasColumn('customers', 'last_seen_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('customers')->whereNotNull('vk_id')->exists()) {
            throw new RuntimeException('Нельзя откатить миграцию, пока к покупателям привязан VK ID.');
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['vk_id']);
            $table->dropColumn(['vk_id', 'vk_email', 'last_seen_at']);
        });
    }
};
