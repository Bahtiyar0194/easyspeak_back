<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfRequestsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_requests_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_type_name');
            $table->integer('request_type_id')->unsigned();
            $table->foreign('request_type_id')->references('request_type_id')->on('types_of_requests');
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
        Schema::dropIfExists('types_of_requests_lang');
    }
}
