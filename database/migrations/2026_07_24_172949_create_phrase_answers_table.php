<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePhraseAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('phrase_answers', function (Blueprint $table) {
            $table->increments('answer_id');
            $table->integer('question_id')->unsigned();
            $table->foreign('question_id')->references('question_id')->on('phrase_questions')->onDelete('cascade');
            $table->text('answer');
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
        Schema::dropIfExists('phrase_answers');
    }
}
