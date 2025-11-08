<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfVideoResolutionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_video_resolutions', function (Blueprint $table) {
            $table->increments('resolution_id');
            $table->string('name');
            $table->integer('width');
            $table->integer('height');
            $table->integer('bitrate');
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('types_of_video_resolutions');
    }
}
