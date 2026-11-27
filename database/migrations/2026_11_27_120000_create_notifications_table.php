<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Уведомления в личном кабинете (колокольчик в шапке).
 * Стандартная таблица database-уведомлений Laravel; в data хранится
 * { type, params, link } — текст собирает фронтенд по type на языке пользователя.
 */
class CreateNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Список и счётчик непрочитанных пользователя
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
