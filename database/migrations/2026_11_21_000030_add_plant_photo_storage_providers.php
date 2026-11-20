<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table): void {
            $table->string('cover_provider', 16)->default('cloudinary');
        });
        Schema::table('plant_photo_entries', function (Blueprint $table): void {
            $table->string('provider', 16)->default('cloudinary');
        });
        Schema::table('plant_photo_cleanup', function (Blueprint $table): void {
            $table->string('provider', 16)->default('cloudinary');
        });
    }

    public function down(): void
    {
        Schema::table('plant_photo_cleanup', fn (Blueprint $table) => $table->dropColumn('provider'));
        Schema::table('plant_photo_entries', fn (Blueprint $table) => $table->dropColumn('provider'));
        Schema::table('plants', fn (Blueprint $table) => $table->dropColumn('cover_provider'));
    }
};
