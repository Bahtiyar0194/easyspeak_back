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
            $table->integer('task_id')->unsigned();
            $table->foreign('task_id')->references('task_id')->on('tasks')->onDelete('cascade');
            $table->boolean('show_audio_button')->default(false)->nullable();
            $table->boolean('show_image')->default(false)->nullable();
            $table->boolean('show_word')->nullable();
            $table->boolean('show_transcription')->default(false)->nullable();
            $table->boolean('show_translate')->default(false)->nullable();
            $table->boolean('show_options')->default(false)->nullable();
            $table->integer('impression_limit')->nullable();
            $table->integer('seconds_per_word')->nullable();
            $table->integer('seconds_per_sentence')->nullable();
            $table->boolean('in_the_main_lang')->nullable();
            $table->boolean('find_word_with_options')->nullable();
            $table->integer('options_num')->nullable();
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
