<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(true);
            $table->string('reminder_timezone', 64)->default('UTC');
            $table->string('reminder_time', 5)->default('10:10');
            $table->date('reminder_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reminders_enabled', 'reminder_timezone', 'reminder_time', 'reminder_sent_on']);
        });
    }
};
