<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOperationsRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('operations_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('operation_type_id')->unsigned();
            $table->foreign('operation_type_id')->references('operation_type_id')->on('types_of_operations');
            $table->integer('role_type_id')->unsigned();
            $table->foreign('role_type_id')->references('role_type_id')->on('types_of_user_roles');
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
        Schema::dropIfExists('operations_roles');
    }
}
