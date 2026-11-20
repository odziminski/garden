<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->string('timezone', 64);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['member_id', 'revoked_at', 'ends_at']);
            $table->index(['owner_id', 'id']);
        });

        Schema::create('plant_handoff_plants', function (Blueprint $table) {
            $table->foreignId('plant_handoff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->text('instructions')->nullable();
            $table->primary(['plant_handoff_id', 'plant_id']);
            $table->index('plant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_handoff_plants');
        Schema::dropIfExists('plant_handoffs');
    }
};
