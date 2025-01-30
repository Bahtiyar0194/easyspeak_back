<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfMaterialsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_materials_lang', function (Blueprint $table) {
          $table->increments('id');
          $table->string('material_type_name');
          $table->integer('material_type_id')->unsigned();
          $table->foreign('material_type_id')->references('material_type_id')->on('types_of_materials');
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
        Schema::dropIfExists('types_of_media_files_lang');
    }
}
