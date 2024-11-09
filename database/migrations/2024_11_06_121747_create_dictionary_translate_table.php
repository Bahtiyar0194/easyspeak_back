<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDictionaryTranslateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dictionary_translate', function (Blueprint $table) {
            $table->increments('id');
            $table->string('word_translate');
            $table->integer('word_id')->unsigned();
            $table->foreign('word_id')->references('word_id')->on('dictionary');
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
        Schema::dropIfExists('dictionary_translate');
    }
}
