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
            $table->integer('material_type_id')->unsigned();
            $table->foreign('material_type_id')->references('material_type_id')->on('types_of_materials')->onDelete('cascade');
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