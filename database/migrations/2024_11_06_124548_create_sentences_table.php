<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSentencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sentences', function (Blueprint $table) {
            $table->increments('sentence_id');
            $table->string('sentence');
            $table->string('transcription')->nullable();
            $table->integer('image_file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('image_file_id')->references('file_id')->on('files');
            $table->integer('audio_file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('audio_file_id')->references('file_id')->on('files');
            $table->integer('course_id')->unsigned();
            $table->foreign('course_id')->references('course_id')->on('courses')->onDelete('cascade');
            $table->integer('operator_id')->unsigned();
            $table->foreign('operator_id')->references('user_id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('sentences');
    }
}
