<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemovePlantsColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('plants')->chunkById(100, function ($plants) {
            $ids = $plants->pluck('id');
            $currentNeeds = DB::table('needs')->whereIn('plant_id', $ids)->pluck('plant_id')->flip();
            $currentHistory = DB::table('history')->whereIn('plant_id', $ids)->pluck('plant_id')->flip();
            $needs = [];
            $history = [];

            foreach ($plants as $plant) {
                if (! $currentNeeds->has($plant->id)) {
                    $needs[] = [
                        'plant_id' => $plant->id,
                        'watering_frequency' => $plant->watering_frequency,
                        'fertilizing_frequency' => $plant->fertilizing_frequency,
                        'need_watering' => $plant->need_watering,
                        'need_fertilizing' => $plant->need_fertilizing,
                    ];
                }
                if (! $currentHistory->has($plant->id)) {
                    $history[] = [
                        'plant_id' => $plant->id,
                        'watered_at' => $plant->watered_at,
                        'fertilized_at' => $plant->fertilized_at,
                    ];
                }
            }

            DB::table('needs')->insert($needs);
            DB::table('history')->insert($history);
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn('watered_at');
            $table->dropColumn('fertilized_at');
            $table->dropColumn('watering_frequency');
            $table->dropColumn('need_watering');
            $table->dropColumn('fertilizing_frequency');
            $table->dropColumn('need_fertilizing');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->timestamp('watered_at')->nullable();
            $table->timestamp('fertilized_at')->nullable();
            $table->integer('watering_frequency')->default(7);
            $table->integer('fertilizing_frequency')->default(30);
            $table->boolean('need_watering')->default(false);
            $table->boolean('need_fertilizing')->default(false);
        });

        DB::table('needs')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('plants')->where('id', $row->plant_id)->update([
                    'watering_frequency' => $row->watering_frequency,
                    'fertilizing_frequency' => $row->fertilizing_frequency,
                    'need_watering' => $row->need_watering,
                    'need_fertilizing' => $row->need_fertilizing,
                ]);
            }
        });
        DB::table('history')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('plants')->where('id', $row->plant_id)->update([
                    'watered_at' => $row->watered_at,
                    'fertilized_at' => $row->fertilized_at,
                ]);
            }
        });
    }
}
