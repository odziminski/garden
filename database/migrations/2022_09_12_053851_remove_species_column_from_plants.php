<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveSpeciesColumnFromPlants extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plants', function($table) {
            $table->dropColumn('species');
        });
    }

    public function down()
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->string('species')->after('name');
        });
    }
}
