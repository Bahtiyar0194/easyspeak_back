<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLessonMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->increments('lesson_material_id');
            $table->integer('lesson_id')->unsigned();
            $table->foreign('lesson_id')->references('lesson_id')->on('lessons')->onDelete('cascade');
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
        Schema::dropIfExists('lesson_materials');
    }
}
