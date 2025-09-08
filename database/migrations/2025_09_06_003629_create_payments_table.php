<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->increments('payment_id');
            $table->float('sum')->default(0);

            $table->boolean('is_paid')->default(false);

            $table->integer('subscription_plan_id')->unsigned()->nullable();
            $table->foreign('subscription_plan_id')->references('subscription_plan_id')->on('types_of_subscription_plans');

            $table->integer('school_id')->unsigned()->nullable();
            $table->foreign('school_id')->references('school_id')->on('schools')->onDelete('cascade');

            $table->integer('payment_method_id')->unsigned();
            $table->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods');

            $table->integer('iniciator_id')->unsigned()->nullable();
            $table->foreign('iniciator_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->integer('operator_id')->unsigned()->nullable();
            $table->foreign('operator_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->timestamps();

            $table->timestamp('accepted_at');
            $table->timestamp('expiration_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
