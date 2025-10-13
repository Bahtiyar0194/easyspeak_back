<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeMentorIdIsNullableOnCompletedTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('completed_tasks', function (Blueprint $table) {
            // Сначала нужно удалить внешнее ограничение
            $table->dropForeign(['mentor_id']);

            // Затем изменить столбец, чтобы он стал nullable
            $table->integer('mentor_id')->unsigned()->nullable()->change();

            // И восстановить внешний ключ
            $table->foreign('mentor_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('completed_tasks', function (Blueprint $table) {
            $table->dropForeign(['mentor_id']);
            $table->integer('mentor_id')->unsigned()->nullable(false)->change();
            $table->foreign('mentor_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
}
