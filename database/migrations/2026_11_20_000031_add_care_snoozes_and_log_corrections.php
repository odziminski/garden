<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('needs', function (Blueprint $table) {
            $table->date('watering_snoozed_until')->nullable();
            $table->date('fertilizing_snoozed_until')->nullable();
        });
        Schema::table('care_logs', function (Blueprint $table) {
            $table->dateTime('voided_at')->nullable();
            $table->dateTime('previous_performed_at')->nullable();
            $table->date('previous_performed_on')->nullable();
            $table->unsignedBigInteger('corrects_entry_id')->nullable();
            $table->index(['plant_id', 'type', 'voided_at'], 'care_logs_active_type_index');
        });
        Schema::table('plant_tasks', fn (Blueprint $table) => $table->date('snoozed_until')->nullable());
        Schema::table('plant_task_completions', function (Blueprint $table) {
            $table->date('resulting_due_on')->nullable();
            $table->date('previous_snoozed_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plant_task_completions', fn (Blueprint $table) => $table->dropColumn([
            'resulting_due_on', 'previous_snoozed_until',
        ]));
        Schema::table('plant_tasks', fn (Blueprint $table) => $table->dropColumn('snoozed_until'));
        Schema::table('care_logs', function (Blueprint $table) {
            $table->dropIndex('care_logs_active_type_index');
            $table->dropColumn(['voided_at', 'previous_performed_at', 'previous_performed_on', 'corrects_entry_id']);
        });
        Schema::table('needs', fn (Blueprint $table) => $table->dropColumn([
            'watering_snoozed_until', 'fertilizing_snoozed_until',
        ]));
    }
};
