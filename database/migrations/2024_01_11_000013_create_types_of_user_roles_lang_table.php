<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfUserRolesLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_user_roles_lang', function (Blueprint $table) {
          $table->increments('id');
          $table->string('user_role_type_name');
          $table->integer('role_type_id')->unsigned();
          $table->foreign('role_type_id')->references('role_type_id')->on('types_of_user_roles');
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
        Schema::dropIfExists('types_of_user_roles_lang');
    }
}
