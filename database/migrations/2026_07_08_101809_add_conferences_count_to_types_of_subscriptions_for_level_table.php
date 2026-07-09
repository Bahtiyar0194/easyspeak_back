<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddConferencesCountToTypesOfSubscriptionsForLevelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('types_of_subscriptions_for_level', function (Blueprint $table) {
            $table->integer('conferences_count')->default(0)->after('subscription_period_in_months');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('types_of_subscriptions_for_level', function (Blueprint $table) {
            //
        });
    }
}
