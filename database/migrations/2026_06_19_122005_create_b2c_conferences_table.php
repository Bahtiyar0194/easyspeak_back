<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2cConferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('b2c_conferences', function (Blueprint $table) {
            $table->increments('conference_id');
            $table->uuid('uuid')->unique();
            $table->string('topic');
            $table->text('topic_description')->nullable();
            $table->integer('poster_file_id')->unsigned()->onDelete('cascade')->nullable();
            $table->foreign('poster_file_id')->references('file_id')->on('files');
            $table->integer('moderator_id')->unsigned();
            $table->foreign('moderator_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('operator_id')->unsigned();
            $table->foreign('operator_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->integer('max_members_count')->default(20);
            $table->integer('participated')->default(0);
            $table->boolean('notification_sent_day_before')->default(false);
            $table->boolean('notification_sent_hour_before')->default(false);
            $table->boolean('notification_sent')->default(false);
            $table->string('access_code')->nullable();
            $table->timestamp('start_time');
            $table->timestamp('end_time');
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
        Schema::dropIfExists('b2c_conferences');
    }
}
