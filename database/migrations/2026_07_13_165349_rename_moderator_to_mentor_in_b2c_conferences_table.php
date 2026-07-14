<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2c_conferences', function (Blueprint $table) {
            // 1. Удаляем старый внешний ключ. 
            // По умолчанию Laravel называет его: имя_таблицы_имя_поля_foreign
            $table->dropForeign(['moderator_id']);

            // 2. Переименовываем сам столбец
            $table->renameColumn('moderator_id', 'mentor_id');
        });

        Schema::table('b2c_conferences', function (Blueprint $table) {
            // 3. Создаем новый внешний ключ на переименованный столбец.
            // Делаем это в отдельной функции Schema::table, чтобы база данных 
            // успела «увидеть» переименование столбца.
            $table->foreign('mentor_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Откат миграции в точном обратном порядке
        Schema::table('b2c_conferences', function (Blueprint $table) {
            $table->dropForeign(['mentor_id']);
            $table->renameColumn('mentor_id', 'moderator_id');
        });

        Schema::table('b2c_conferences', function (Blueprint $table) {
            $table->foreign('moderator_id')
                  ->references('user_id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }
};