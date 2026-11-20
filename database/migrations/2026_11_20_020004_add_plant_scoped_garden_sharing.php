<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['garden_invitations', 'garden_memberships'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('plant_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['garden_invitations', 'garden_memberships'] as $tableName) {
            // A restricted invitation or membership must never become a whole-garden grant.
            \Illuminate\Support\Facades\DB::table($tableName)->whereNotNull('plant_id')->delete();
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('plant_id');
            });
        }
    }
};
