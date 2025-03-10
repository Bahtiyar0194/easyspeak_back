<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskSentenceMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_sentence_materials', function (Blueprint $table) {
            $table->increments('task_sentence_material_id');
            $table->integer('task_sentence_id')->unsigned();
            $table->foreign('task_sentence_id')->references('task_sentence_id')->on('task_sentences')->onDelete('cascade');
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
        Schema::dropIfExists('task_sentence_materials');
    }
}
