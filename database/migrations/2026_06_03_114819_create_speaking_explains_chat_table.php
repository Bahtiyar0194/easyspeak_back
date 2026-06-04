<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpeakingExplainsChatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('speaking_explains_chat', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('uuid')->unique();
            $table->text('user_prompt');
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('speaking_explains_chat');
    }
}
