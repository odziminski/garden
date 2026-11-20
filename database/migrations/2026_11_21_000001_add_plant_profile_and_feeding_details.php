<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->string('room', 80)->nullable();
            $table->string('species_name', 120)->nullable();
            $table->text('care_instructions')->nullable();
        });

        Schema::table('care_logs', function (Blueprint $table) {
            $table->string('fertilizer', 120)->nullable();
            $table->string('dose', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('care_logs', function (Blueprint $table) {
            $table->dropColumn(['fertilizer', 'dose']);
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn(['room', 'species_name', 'care_instructions']);
        });
    }
};
