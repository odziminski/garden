<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garden_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['owner_id', 'member_id']);
            $table->index('member_id');
        });

        Schema::create('garden_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garden_invitations');
        Schema::dropIfExists('garden_memberships');
    }
};
