<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->increments('task_id');
            $table->string('task_slug');
            $table->text('task_example')->nullable();
            $table->integer('task_type_id')->unsigned();
            $table->foreign('task_type_id')->references('task_type_id')->on('types_of_tasks');
            $table->integer('lesson_id')->unsigned()->nullable();
            $table->foreign('lesson_id')->references('lesson_id')->on('lessons');
            $table->integer('operator_id')->unsigned();
            $table->foreign('operator_id')->references('user_id')->on('users');
            $table->integer('sort_num')->default(0);
            $table->integer('status_type_id')->default(1)->unsigned();
            $table->foreign('status_type_id')->references('status_type_id')->on('types_of_status');
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
        Schema::dropIfExists('lesson_tasks');
    }
}
