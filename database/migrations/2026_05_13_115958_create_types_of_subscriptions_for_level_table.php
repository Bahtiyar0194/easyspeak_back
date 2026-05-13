<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfSubscriptionsForLevelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_subscriptions_for_level', function (Blueprint $table) {
            $table->increments('subscription_type_id');
            $table->integer('price')->default(0);
            $table->integer('level_id')->unsigned();
            $table->foreign('level_id')->references('level_id')->on('course_levels')->onDelete('cascade');
            $table->integer('subscription_period_in_months')->default(1);
            $table->integer('status_type_id')->default(1)->unsigned();
            $table->foreign('status_type_id')->references('status_type_id')->on('types_of_status');
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
        Schema::dropIfExists('types_of_subscriptions_for_level');
    }
}
