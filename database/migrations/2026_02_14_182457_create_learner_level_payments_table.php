<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLearnerLevelPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('learner_level_payments', function (Blueprint $table) {
            $table->increments('level_payment_id');

            $table->float('sum')->default(0);
            $table->string('description')->nullable();

            $table->boolean('is_paid')->default(false);

            $table->integer('level_id')->unsigned();
            $table->foreign('level_id')->references('level_id')->on('course_levels')->onDelete('cascade');

            $table->integer('payment_method_id')->unsigned();
            $table->foreign('payment_method_id')->references('payment_method_id')->on('payment_methods');

            $table->integer('promo_id')->unsigned()->nullable();
            $table->foreign('promo_id')->references('promo_id')->on('promo_codes');

            $table->integer('iniciator_id')->unsigned()->nullable();
            $table->foreign('iniciator_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->integer('operator_id')->unsigned()->nullable();
            $table->foreign('operator_id')->references('user_id')->on('users')->onDelete('cascade');

            $table->timestamp('subscription_expiration_at');
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
        Schema::dropIfExists('learner_level_payments');
    }
}
