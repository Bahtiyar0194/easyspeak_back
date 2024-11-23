<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfTasksLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_tasks_lang', function (Blueprint $table) {
          $table->increments('id');
          $table->string('task_type_name');
          $table->integer('task_type_id')->unsigned();
          $table->foreign('task_type_id')->references('task_type_id')->on('types_of_tasks');
          $table->integer('lang_id')->unsigned();
          $table->foreign('lang_id')->references('lang_id')->on('languages');
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
        Schema::dropIfExists('types_of_tasks_lang');
    }
}
