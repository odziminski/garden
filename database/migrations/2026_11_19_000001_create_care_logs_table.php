<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history', function (Blueprint $table) {
            $table->date('watered_on')->nullable();
            $table->date('fertilized_on')->nullable();
        });

        Schema::create('care_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->dateTime('performed_at');
            $table->date('performed_on');
            $table->text('note')->nullable();
            $table->boolean('is_legacy')->default(false);
            $table->timestamp('created_at');
            $table->index(['plant_id', 'performed_on', 'performed_at', 'id'], 'care_logs_timeline_index');
        });

        $importedAt = now();

        DB::table('history')->select(['id', 'plant_id', 'watered_at', 'fertilized_at'])
            ->chunkById(75, function ($histories) use ($importedAt): void {
                $entries = [];
                foreach ($histories as $history) {
                    foreach (['watering' => 'watered_at', 'fertilizing' => 'fertilized_at'] as $type => $column) {
                        if ($history->$column !== null) {
                            $entries[] = [
                                'plant_id' => $history->plant_id,
                                'type' => $type,
                                'performed_at' => $history->$column,
                                'performed_on' => substr($history->$column, 0, 10),
                                'is_legacy' => true,
                                'created_at' => $importedAt,
                            ];
                        }
                    }
                }

                DB::table('care_logs')->insert($entries);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_logs');
        Schema::table('history', function (Blueprint $table) {
            $table->dropColumn(['watered_on', 'fertilized_on']);
        });
    }
};
