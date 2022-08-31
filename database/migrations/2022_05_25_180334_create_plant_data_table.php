<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlantDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plant_data', function (Blueprint $table) {
            $plantidFields = [
                'plant_name',
                'common_name',
                'wikipedia_url',
                'wikipedia_description',
                'taxonomy_class',
                'taxonomy_family',
                'taxonomy_genus',
                'taxonomy_kingdom',
                'taxonomy_order',
                'taxonomy_phylum',

            ];
            $table->id();
            $table->bigInteger('plant_id')->unsigned();

            foreach ($plantidFields as $field) {
                $table->string($field);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plant_data');
    }
}
