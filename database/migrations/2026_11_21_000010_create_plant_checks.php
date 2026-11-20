<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plant_check_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->date('next_check_on')->nullable();
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->unsignedInteger('legacy_watering_frequency')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->date('skip_previous_on')->nullable();
            $table->foreignId('skipped_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['enabled', 'next_check_on'], 'plant_checks_schedule_index');
        });

        Schema::create('plant_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('care_log_id')->nullable()->unique()->constrained('care_logs')->nullOnDelete();
            $table->string('outcome', 16);
            $table->date('scheduled_for')->nullable();
            $table->unsignedBigInteger('schedule_version');
            $table->date('checked_on');
            $table->dateTime('checked_at');
            $table->date('next_check_on')->nullable();
            $table->text('note')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unique(['plant_id', 'schedule_version']);
            $table->index(['plant_id', 'voided_at', 'checked_at', 'id'], 'plant_checks_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plant_checks');
        Schema::dropIfExists('plant_check_schedules');
    }
};
