<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        //
    }
}
