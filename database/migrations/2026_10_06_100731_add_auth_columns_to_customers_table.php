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
        Schema::table('customers', function (Blueprint $table) {
            $table->rememberToken()->after('password');
            // Пароль со старого сайта (OpenCart: sha1 с солью). При первом входе
            // проверяется, пересохраняется в password обычным хешем и стирается.
            $table->string('legacy_password_hash', 40)->nullable()->after('remember_token');
            $table->string('legacy_password_salt', 9)->nullable()->after('legacy_password_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['remember_token', 'legacy_password_hash', 'legacy_password_salt']);
        });
    }
};
