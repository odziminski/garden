<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_identifications', function (Blueprint $table): void {
            $table->foreignId('plant_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('cover_public_id');
            $table->string('status', 16);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('available_at');
            $table->uuid('claim_token')->nullable();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_identifications');
    }
};
