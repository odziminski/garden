<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', fn (Blueprint $table) => $table->string('seasonal_hemisphere', 5)->default('north'));
    }

    public function down(): void
    {
        Schema::table('needs', fn (Blueprint $table) => $table->dropColumn('seasonal_hemisphere'));
    }
};
