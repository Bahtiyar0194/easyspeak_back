<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCheckingByToTaskQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_questions', function (Blueprint $table) {;
            $table->string('checking_by')->after('question_id')->default('by_ai');
            $table->dropColumn('predefined_answer');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_questions', function (Blueprint $table) {
            //
        });
    }
}
