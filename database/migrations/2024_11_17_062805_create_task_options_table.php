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
            $table->boolean('play_audio_at_the_begin')->default(false)->nullable();
            $table->boolean('play_audio_with_the_correct_answer')->default(false)->nullable();
            $table->boolean('play_error_sound_with_the_incorrect_answer')->default(false)->nullable();
            $table->boolean('show_image')->default(false)->nullable();
            $table->boolean('show_word')->nullable();
            $table->boolean('show_transcription')->default(false)->nullable();
            $table->boolean('show_translate')->default(false)->nullable();
            $table->boolean('show_options')->default(false)->nullable();
            $table->integer('impression_limit')->nullable();
            $table->integer('seconds_per_word')->nullable();
            $table->integer('seconds_per_sentence')->nullable();
            $table->boolean('in_the_main_lang')->nullable();
            $table->string('find_word_option')->nullable();
            $table->integer('options_num')->nullable();
            $table->boolean('random_order')->default(true);
            $table->boolean('match_by_typing')->nullable();
            $table->boolean('match_by_clicking')->nullable();
            $table->boolean('match_by_drag_and_drop')->nullable();
            $table->integer('max_attempts')->default(0);
            $table->string('show_materials_option')->nullable();
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
