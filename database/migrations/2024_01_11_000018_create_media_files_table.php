<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMediaFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->increments('file_id');
            $table->string('file_name');
            $table->string('file_target');
            $table->integer('file_type_id')->unsigned();
            $table->foreign('file_type_id')->references('file_type_id')->on('types_of_media_files');
            $table->integer('school_id')->unsigned();
            $table->foreign('school_id')->references('school_id')->on('schools')->onDelete('cascade');
            $table->float('size')->nullable();
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
        Schema::dropIfExists('media_files');
    }
}
