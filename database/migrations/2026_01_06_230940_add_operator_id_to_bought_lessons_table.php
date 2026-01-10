<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOperatorIdToBoughtLessonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bought_lessons', function (Blueprint $table) {
            $table->integer('iniciator_id')->after('lesson_id')->unsigned()->nullable();
            $table->foreign('iniciator_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bought_lessons', function (Blueprint $table) {
            //
        });
    }
}
