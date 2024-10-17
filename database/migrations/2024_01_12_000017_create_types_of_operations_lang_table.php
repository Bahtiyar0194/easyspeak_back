<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfOperationsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_operations_lang', function (Blueprint $table) {
          $table->increments('id');
          $table->string('operation_type_name');
          $table->integer('operation_type_id')->unsigned();
          $table->foreign('operation_type_id')->references('operation_type_id')->on('types_of_operations');
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
        Schema::dropIfExists('types_of_operations_lang');
    }
}
