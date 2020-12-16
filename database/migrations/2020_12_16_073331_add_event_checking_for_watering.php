<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventCheckingForWatering extends Migration
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
                CREATE EVENT IF NOT EXISTS checkforwatering
                ON SCHEDULE EVERY 1 MINUTE
                DO
                    UPDATE
                        plants    
                    SET
                        need_watering = 1      
                    WHERE
                     DATE_ADD(plants.watered_at, INTERVAL plants.watering_frequency DAY) <= CURRENT_TIMESTAMP()    
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
            DB::connection()->getPdo()->exec('DROP EVENT IF EXISTS checkforwatering');
        });
    }
}
