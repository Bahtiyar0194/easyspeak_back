<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSchoolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->increments('school_id');
            $table->string('school_domain');
            $table->string('school_name');
            $table->text('about')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('instagram')->nullable();
            $table->string('facebook')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('telegram')->nullable();
            $table->string('youtube')->nullable();
            $table->string('light_theme_logo')->nullable();
            $table->string('dark_theme_logo')->nullable();
            $table->string('favicon')->nullable();
            $table->integer('theme_id')->unsigned()->default(1);
            $table->foreign('theme_id')->references('theme_id')->on('themes');
            $table->integer('color_id')->unsigned()->default(1);
            $table->foreign('color_id')->references('color_id')->on('colors');
            $table->integer('title_font_id')->unsigned()->default(1);
            $table->foreign('title_font_id')->references('font_id')->on('fonts');
            $table->integer('body_font_id')->unsigned()->default(1);
            $table->foreign('body_font_id')->references('font_id')->on('fonts');
            $table->integer('school_type_id')->unsigned();
            $table->foreign('school_type_id')->references('school_type_id')->on('types_of_schools');
            $table->integer('subscription_plan_id')->default(1)->unsigned();
            $table->foreign('subscription_plan_id')->references('subscription_plan_id')->on('types_of_subscription_plans');
            $table->timestamp('subscription_expiration_at')->nullable();
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
        Schema::dropIfExists('schools');
    }
}