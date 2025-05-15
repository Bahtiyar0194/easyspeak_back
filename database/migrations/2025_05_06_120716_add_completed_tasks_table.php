<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompletedTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('completed_tasks', function (Blueprint $table) {
            $table->increments('completed_task_id');
            $table->integer('task_id')->unsigned();
            $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
            $table->integer('learner_id')->unsigned();
            $table->foreign('learner_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('mentor_id')->unsigned();
            $table->foreign('mentor_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->boolean('is_completed')->default(true);
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
        //
    }
}
