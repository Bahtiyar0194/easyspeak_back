<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoursesLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('courses_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('course_name');
            $table->text('course_description');
            $table->text('course_content');
            $table->integer('course_id')->unsigned();
            $table->foreign('course_id')->references('course_id')->on('courses')->onDelete('cascade');
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
        Schema::dropIfExists('courses_lang');
    }
}
