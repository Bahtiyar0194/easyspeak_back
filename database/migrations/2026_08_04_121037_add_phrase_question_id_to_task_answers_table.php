<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhraseQuestionIdToTaskAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_answers', function (Blueprint $table) {
            $table->integer('phrase_question_id')->unsigned()->nullable()->after('question_id');
            $table->foreign('phrase_question_id')->references('question_id')->on('phrase_questions');
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
