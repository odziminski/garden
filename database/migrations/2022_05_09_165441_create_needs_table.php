<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNeedsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('needs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('plant_id')->unsigned();
            $table->foreign('plant_id')->references('id')->on('plants')->onDelete('cascade');
            $table->integer('watering_frequency');
            $table->boolean('need_watering')->default(false);
            $table->integer('fertilizing_frequency');
            $table->boolean('need_fertilizing')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('needs');
    }
}
