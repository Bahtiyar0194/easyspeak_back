<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWordSectionItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('word_section_items', function (Blueprint $table) {
            $table->increments('word_section_item_id');
            $table->integer('word_section_id')->unsigned();
            $table->foreign('word_section_id')->references('word_section_id')->on('word_sections')->onDelete('cascade');
            $table->integer('word_id')->unsigned();
            $table->foreign('word_id')->references('word_id')->on('dictionary')->onDelete('cascade');
            $table->boolean('target')->default(false);
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
        Schema::dropIfExists('word_section_items');
    }
}
