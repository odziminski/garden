<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->date('observed_on');
            $table->text('notes');
            $table->string('container_size', 100)->nullable();
            $table->string('substrate', 500)->nullable();
            $table->foreignId('photo_entry_id')->nullable()->constrained('plant_photo_entries')->nullOnDelete();
            $table->foreignId('follow_up_task_id')->nullable()->constrained('plant_tasks')->nullOnDelete();
            $table->timestamps();
            $table->index(['plant_id', 'observed_on', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_observations');
    }
};
