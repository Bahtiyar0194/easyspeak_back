<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCourseLevelsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('course_levels_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('level_name');
            $table->text('level_description');
            $table->integer('level_id')->unsigned();
            $table->foreign('level_id')->references('level_id')->on('course_levels')->onDelete('cascade');
            $table->integer('lang_id')->unsigned();
            $table->foreign('lang_id')->references('lang_id')->on('languages');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('course_levels_lang');
    }
}
