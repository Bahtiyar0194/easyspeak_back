<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTypesOfSubscriptionPlansLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('types_of_subscription_plans_lang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('subscription_plan_name');
            $table->integer('subscription_plan_id')->unsigned();

            // 👇 указываем короткое имя для foreign key
            $table->foreign('subscription_plan_id')
                ->references('subscription_plan_id')
                ->on('types_of_subscription_plans')
                ->onDelete('cascade');

            $table->integer('lang_id')->unsigned();
            $table->foreign('lang_id')->references('lang_id')->on('languages');
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
        Schema::dropIfExists('types_of_subscription_plans_lang');
    }
}
