<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->date('due_on');
            $table->unsignedInteger('repeat_days')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['plant_id', 'archived_at', 'completed_at', 'due_on'], 'plant_tasks_due_index');
        });

        Schema::create('plant_task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_task_id')->constrained()->cascadeOnDelete();
            $table->date('scheduled_for');
            $table->date('completed_on');
            $table->timestamp('completed_at');
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['plant_task_id', 'scheduled_for'], 'plant_task_occurrence_unique');
            $table->index(['plant_task_id', 'completed_on', 'completed_at'], 'plant_task_completion_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_task_completions');
        Schema::dropIfExists('plant_tasks');
    }
};
