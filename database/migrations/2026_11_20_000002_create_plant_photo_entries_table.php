<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_photo_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->string('public_id')->unique();
            $table->string('format', 4);
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['plant_id', 'taken_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_photo_entries');
    }
};
