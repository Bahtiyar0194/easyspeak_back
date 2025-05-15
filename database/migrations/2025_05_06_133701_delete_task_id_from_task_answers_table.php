<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DeleteTaskIdFromTaskAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_answers', function (Blueprint $table) {
            $table->integer('completed_task_id')->unsigned()->after('task_answer_id');
            $table->foreign('completed_task_id')->references('completed_task_id')->on('completed_tasks')->onDelete('cascade');
            $table->dropForeign(['task_id']);
            $table->dropForeign(['learner_id']);
            $table->dropForeign(['mentor_id']);
            $table->dropColumn(['task_id', 'learner_id', 'mentor_id']);
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
