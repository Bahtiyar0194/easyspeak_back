<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMissingWordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('missing_words', function (Blueprint $table) {
            $table->increments('missing_word_id');
            $table->string('word');
            $table->integer('task_sentence_id')->unsigned();
            $table->foreign('task_sentence_id')->references('task_sentence_id')->on('task_sentences')->onDelete('cascade');
            $table->boolean('is_correct');
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
        Schema::dropIfExists('missing_words');
    }
}
