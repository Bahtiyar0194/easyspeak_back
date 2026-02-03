<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMaterialExplainsChatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('material_explains_chat', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->text('user_prompt');
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('lesson_material_id')->unsigned()->nullable();
            $table->foreign('lesson_material_id')->references('lesson_material_id')->on('lesson_materials')->onDelete('cascade');
            $table->integer('explain_id')->unsigned()->nullable();
            $table->foreign('explain_id')->references('explain_id')->on('ai_explains')->onDelete('cascade');
            $table->boolean('like')->nullable();
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
        Schema::dropIfExists('material_explains_chat');
    }
}
