<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConferenceTasks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conference_tasks', function (Blueprint $table) {
            $table->increments('conference_task_id');
            $table->integer('conference_id')->unsigned();
            $table->foreign('conference_id')->references('conference_id')->on('conferences')->onDelete('cascade');
            $table->integer('task_id')->unsigned();
            $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
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
