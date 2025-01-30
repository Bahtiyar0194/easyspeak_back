<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_materials', function (Blueprint $table) {
            $table->increments('task_material_id');
            $table->integer('task_id')->unsigned();
            $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
            $table->integer('file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('file_id')->references('file_id')->on('files');
            $table->integer('block_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('block_id')->references('block_id')->on('blocks');
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
        Schema::dropIfExists('task_materials');
    }
}
