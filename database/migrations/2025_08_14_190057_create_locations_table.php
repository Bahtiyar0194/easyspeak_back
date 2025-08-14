<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLocationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('location_id');
            $table->string('location_slug');

            $table->integer('parent_id')->unsigned()->nullable();
            $table->foreign('parent_id')->references('location_id')->on('locations')->onDelete('set null');

            $table->integer('location_type_id')->unsigned();
            $table->foreign('location_type_id')->references('location_type_id')->on('types_of_locations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locations');
    }
}
