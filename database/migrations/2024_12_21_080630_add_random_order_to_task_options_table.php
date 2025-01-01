<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRandomOrderToTaskOptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_options', function (Blueprint $table) {
            $table->boolean('random_order')->default(true);
            $table->boolean('match_by_typing')->nullable();
            $table->boolean('match_by_clicking')->nullable();
            $table->boolean('match_by_drag_and_drop')->nullable();
            $table->integer('max_attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_options', function (Blueprint $table) {
        });
    }
}
