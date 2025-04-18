<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSentenceIdToTaskAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_answers', function (Blueprint $table) {
            $table->integer('word_id')->unsigned()->nullable();
            $table->foreign('word_id')->references('word_id')->on('dictionary');
            $table->integer('sentence_id')->unsigned()->nullable();
            $table->foreign('sentence_id')->references('sentence_id')->on('sentences');
            $table->integer('question_id')->unsigned()->nullable();
            $table->foreign('question_id')->references('question_id')->on('task_questions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_answers', function (Blueprint $table) {
            //
        });
    }
}
