<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWordSyllablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('word_syllables', function (Blueprint $table) {
            $table->increments('word_syllable_id');
            $table->string('syllable');
            $table->boolean('target')->default(false);
            $table->integer('task_word_id')->unsigned();
            $table->foreign('task_word_id')->references('task_word_id')->on('task_words')->onDelete('cascade');
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
        Schema::dropIfExists('word_syllables');
    }
}
