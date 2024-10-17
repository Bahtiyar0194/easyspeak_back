<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfSubscriptionPlans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_subscription_plans', function (Blueprint $table) {
            $table->increments('subscription_plan_id');
            $table->string('subscription_plan_name');
            $table->integer('disk_space');
            $table->integer('max_users_count');
            $table->integer('max_courses_count');
            $table->integer('price');
            $table->boolean('white_label')->default(false);
            $table->string('color_scheme');
            $table->integer('show_status_id')->default(1)->unsigned();
            $table->foreign('show_status_id')->references('show_status_id')->on('show_status');
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
        Schema::dropIfExists('types_of_subscription_plans');
    }
}
