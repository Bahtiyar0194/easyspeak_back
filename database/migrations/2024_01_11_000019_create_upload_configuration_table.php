<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUploadConfigurationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('upload_configuration', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('file_type_id')->unsigned();
            $table->foreign('file_type_id')->references('file_type_id')->on('types_of_media_files')->onDelete('cascade');
            $table->float('max_file_size_mb')->nullable();
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
        Schema::dropIfExists('upload_configuration');
    }
}
