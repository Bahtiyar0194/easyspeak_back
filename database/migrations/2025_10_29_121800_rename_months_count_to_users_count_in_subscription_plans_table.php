<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameMonthsCountToUsersCountInSubscriptionPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('types_of_subscription_plans', function (Blueprint $table) {
            $table->renameColumn('months_count', 'users_count');
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
            $table->renameColumn('users_count', 'months_count');
        });
    }
}
