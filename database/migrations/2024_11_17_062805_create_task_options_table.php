<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTaskOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('task_options', function (Blueprint $table) {
            $table->increments('task_option_id');
            $table->boolean('show_audio_button')->default(false)->nullable();
            $table->boolean('show_image')->default(false)->nullable();
            $table->boolean('show_transcription')->default(false)->nullable();
            $table->boolean('show_translate')->default(false)->nullable();
            $table->boolean('show_options')->default(false)->nullable();
            $table->integer('impression_limit')->nullable();
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
        Schema::dropIfExists('task_options');
    }
}
