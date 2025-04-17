<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_answers', function (Blueprint $table) {
            $table->increments('task_answer_id');
            $table->integer('task_id')->unsigned();
            $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
            $table->integer('learner_id')->unsigned();
            $table->foreign('learner_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('mentor_id')->unsigned()->nullable();
            $table->foreign('mentor_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->text('user_answer')->nullable();
            $table->text('right_answer')->nullable();
            $table->integer('learner_answer_file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('learner_answer_file_id')->references('task_answer_file_id')->on('task_answer_files');
            $table->boolean('is_correct')->nullable();
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
        Schema::dropIfExists('task_answers');
    }
}
