<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateFileIdForeignOnTaskMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_materials', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')
                ->references('file_id')
                ->on('files')
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
        Schema::table('task_materials', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->foreign('file_id')
                ->references('file_id')
                ->on('files');
        });
    }
}
