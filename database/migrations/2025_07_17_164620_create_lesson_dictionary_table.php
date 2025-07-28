<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonDictionaryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_dictionary', function (Blueprint $table) {
            $table->increments('lesson_dictionary_id');
            $table->integer('category_id')->unsigned();
            $table->foreign('category_id')->references('category_id')->on('lesson_dictionary_category')->onDelete('cascade');
            $table->integer('word_id')->unsigned();
            $table->foreign('word_id')->references('word_id')->on('dictionary')->onDelete('cascade');
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
        Schema::dropIfExists('lesson_dictionary');
    }
}
