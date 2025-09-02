<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonthsToTypesOfSubscriptionPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('types_of_subscription_plans', function (Blueprint $table) {
            $table->integer('months_count')->after('subscription_plan_name');
            $table->dropColumn(['disk_space', 'max_users_count', 'max_courses_count', 'color_scheme']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('types_of_subscription_plans', function (Blueprint $table) {
            //
        });
    }
}
