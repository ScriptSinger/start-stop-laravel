<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_import_lock', function (Blueprint $table): void {
            $table->id();
        });
        DB::table('onec_import_lock')->insert(['id' => 1]);
        Schema::create('onec_link_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('import_id', 40);
            $table->unsignedBigInteger('admin_id');
            $table->string('code');
            $table->unsignedBigInteger('product_id');
            $table->timestamps();
            $table->unique(['import_id', 'admin_id', 'code']);
        });
        Schema::create('onec_applications', function (Blueprint $table): void {
            $table->string('import_id', 40)->primary();
            $table->json('metadata');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onec_applications');
        Schema::dropIfExists('onec_link_drafts');
        Schema::dropIfExists('onec_import_lock');
    }
};
