<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->unsignedSmallInteger('summer_watering_frequency')->nullable();
            $table->unsignedSmallInteger('winter_watering_frequency')->nullable();
            $table->unsignedSmallInteger('summer_fertilizing_frequency')->nullable();
            $table->unsignedSmallInteger('winter_fertilizing_frequency')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->dropColumn([
                'summer_watering_frequency',
                'winter_watering_frequency',
                'summer_fertilizing_frequency',
                'winter_fertilizing_frequency',
            ]);
        });
    }
};
