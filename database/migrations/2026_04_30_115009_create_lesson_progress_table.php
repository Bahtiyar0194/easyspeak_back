<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->increments('lesson_progress_id');
            $table->integer('learner_id')->unsigned();
            $table->foreign('learner_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('lesson_id')->unsigned();
            $table->foreign('lesson_id')->references('lesson_id')->on('lessons')->onDelete('cascade');
            $table->float('progress')->default(0);
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
        Schema::dropIfExists('lesson_progress');
    }
}
