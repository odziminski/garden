<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table): void {
            $table->string('cover_public_id')->nullable();
            $table->string('cover_format', 5)->nullable();
        });

        Schema::create('plant_photo_cleanup', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id');
            $table->string('delivery_type', 16);
            $table->string('legacy_url')->nullable();
            $table->dateTime('available_at')->index();
            $table->uuid('claim_token')->nullable();
            $table->unique(['public_id', 'delivery_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_photo_cleanup');
        Schema::table('plants', function (Blueprint $table): void {
            $table->dropColumn(['cover_public_id', 'cover_format']);
        });
    }
};
