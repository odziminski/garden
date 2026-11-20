<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['needs', 'history', 'plant_data'] as $tableName) {
            $duplicates = DB::table($tableName)->select('plant_id')->groupBy('plant_id')->havingRaw('COUNT(*) > 1');
            if ($duplicates->exists()) {
                throw new RuntimeException("Resolve duplicate {$tableName} records by plant_id before adding its unique index.");
            }
        }
        if (DB::table('plant_data')->whereNotIn('plant_id', DB::table('plants')->select('id'))->exists()) {
            throw new RuntimeException('Reconcile plant_data records without a plant before adding its foreign key.');
        }

        foreach (['needs', 'history', 'plant_data'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique('plant_id');
            });
        }
        Schema::table('plant_data', function (Blueprint $table) {
            $table->foreign('plant_id')->references('id')->on('plants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plant_data', function (Blueprint $table) {
            $table->dropForeign(['plant_id']);
        });
        foreach (['needs', 'history', 'plant_data'] as $tableName) {
            if ($tableName !== 'plant_data' && DB::getDriverName() === 'mysql') {
                $indexName = $tableName.'_plant_id_index';
                if (! Schema::hasIndex($tableName, $indexName)) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->index('plant_id');
                    });
                }
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['plant_id']);
            });
        }
    }
};
