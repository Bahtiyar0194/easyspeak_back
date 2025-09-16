<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_answers', function (Blueprint $table) {
            // Убираем старый внешний ключ
            $table->dropForeign(['word_id']);
            $table->dropForeign(['sentence_id']);
            $table->dropForeign(['question_id']);

            // Создаём новый с нужным поведением
            $table->foreign('question_id')
                  ->references('question_id')
                  ->on('task_questions')
                  ->onDelete('cascade'); // 👈 можно заменить на ->onDelete('set null')

            $table->foreign('word_id')
                  ->references('word_id')
                  ->on('dictionary')
                  ->onDelete('cascade'); // 👈 можно заменить на ->onDelete('set null')

            $table->foreign('sentence_id')
                  ->references('sentence_id')
                  ->on('sentences')
                  ->onDelete('cascade'); // 👈 можно заменить на ->onDelete('set null')
        });
    }

    public function down(): void
    {
        Schema::table('task_answers', function (Blueprint $table) {
            // Откатываем назад
            $table->dropForeign(['word_id']);
            $table->dropForeign(['sentence_id']);
            $table->dropForeign(['question_id']);

            $table->foreign('question_id')
                  ->references('question_id')
                  ->on('task_questions');

            $table->foreign('word_id')
                   ->references('word_id')
                   ->on('dictionary');

            $table->foreign('sentence_id')
                  ->references('sentence_id')
                  ->on('sentences');
        });
    }
};

