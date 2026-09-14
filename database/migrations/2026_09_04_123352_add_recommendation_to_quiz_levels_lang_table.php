<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRecommendationToQuizLevelsLangTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('quiz_levels_lang', function (Blueprint $table) {
            $table->text('current_level_recommendation')->after('quiz_level_name')->nullable();
            $table->text('next_level_recommendation')->after('current_level_recommendation')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('quiz_levels_lang', function (Blueprint $table) {
            //
        });
    }
}
