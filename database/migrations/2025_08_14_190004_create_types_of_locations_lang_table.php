<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfLocationsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_locations_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('location_type_name');
            $table->integer('location_type_id')->unsigned();
            $table->foreign('location_type_id')->references('location_type_id')->on('types_of_locations');
            $table->integer('lang_id')->unsigned();
            $table->foreign('lang_id')->references('lang_id')->on('languages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('types_of_locations_lang');
    }
}
