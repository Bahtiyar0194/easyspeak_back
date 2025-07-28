<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonDictionaryCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_dictionary_category', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('category_slug');
            $table->integer('lesson_id')->unsigned();
            $table->foreign('lesson_id')->references('lesson_id')->on('lessons')->onDelete('cascade');
            $table->integer('operator_id')->unsigned();
            $table->foreign('operator_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('sort_num')->default(0);
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
        Schema::dropIfExists('lesson_dictionary_category');
    }
}
