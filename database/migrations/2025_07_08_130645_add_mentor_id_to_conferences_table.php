<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMentorIdToConferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conferences', function (Blueprint $table) {
            $table->integer('mentor_id')->unsigned()->after('operator_id')->default(1);
            $table->foreign('mentor_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conferences', function (Blueprint $table) {
            //
        });
    }
}
