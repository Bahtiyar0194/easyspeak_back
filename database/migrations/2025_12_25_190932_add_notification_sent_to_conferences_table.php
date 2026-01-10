<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotificationSentToConferencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conferences', function (Blueprint $table) {
            $table->boolean('notification_sent_day_before')->after('moved')->default(false);
            $table->boolean('notification_sent_hour_before')->after('notification_sent_day_before')->default(false);
            $table->boolean('notification_sent')->after('notification_sent_hour_before')->default(false);
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
