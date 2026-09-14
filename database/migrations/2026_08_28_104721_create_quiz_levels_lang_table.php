<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuizLevelsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quiz_levels_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('quiz_level_name');
            $table->integer('quiz_level_id')->unsigned();
            $table->foreign('quiz_level_id')->references('quiz_level_id')->on('quiz_levels');
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
        Schema::dropIfExists('quiz_levels_lang');
    }
}
