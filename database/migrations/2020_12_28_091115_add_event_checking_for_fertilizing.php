<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventCheckingForFertilizing extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plants', function (Blueprint $table) {

            DB::connection()->getPdo()->exec('
                CREATE EVENT IF NOT EXISTS checkforfertilizing
                ON SCHEDULE EVERY 1 MINUTE
                DO
                    UPDATE
                        plants    
                    SET
                        need_fertilizing = 1      
                    WHERE
                     DATE_ADD(plants.fertilized_at, INTERVAL plants.fertilizing_frequency DAY) <= CURRENT_TIMESTAMP()    
            ');
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
            DB::connection()->getPdo()->exec('DROP EVENT IF EXISTS checkforfertilizing');
        });
    }
}
