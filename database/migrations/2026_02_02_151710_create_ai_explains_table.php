<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiExplainsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_explains', function (Blueprint $table) {
            $table->increments('explain_id');
            $table->text('content');
            $table->string('text_driver')->nullable();
            $table->integer('audio_file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('audio_file_id')->references('file_id')->on('files');
            $table->string('audio_driver')->nullable();
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
        Schema::dropIfExists('ai_explains');
    }
}
