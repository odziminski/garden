<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plant_tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['assignee_id', 'archived_at', 'completed_at', 'due_on'], 'plant_tasks_assigned_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('plant_tasks', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->dropIndex('plant_tasks_assigned_due_index');
            $table->dropColumn('assignee_id');
        });
    }
};
