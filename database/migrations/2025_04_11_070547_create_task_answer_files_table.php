<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskAnswerFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_answer_files', function (Blueprint $table) {
            $table->increments('task_answer_file_id');
            $table->string('target');
            $table->float('size');
            $table->integer('material_type_id')->unsigned();
            $table->foreign('material_type_id')->references('material_type_id')->on('types_of_materials');
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
        Schema::dropIfExists('task_answer_files');
    }
}
