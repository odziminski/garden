<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL creates an index for the plants.user_id foreign key. SQLite does not.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('plants', fn (Blueprint $table) => $table->index('user_id'));
        }

        Schema::create('care_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 8);
            $table->date('local_date');
            $table->string('status', 12)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('finished_at')->nullable();
            $table->unique(['user_id', 'local_date', 'channel']);
            $table->index(['status', 'available_at']);
            $table->index(['status', 'finished_at']);
        });

        Schema::create('care_scheduler_heartbeats', function (Blueprint $table) {
            $table->string('name', 20)->primary();
            $table->dateTime('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_scheduler_heartbeats');
        Schema::dropIfExists('care_reminder_deliveries');

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('plants', fn (Blueprint $table) => $table->dropIndex(['user_id']));
        }
    }
};
