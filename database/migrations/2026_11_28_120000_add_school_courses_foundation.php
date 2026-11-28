<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Фундамент «свои курсы школ»:
 *  - courses.school_id: NULL — курс платформы (EasySpeak), число — курс школы;
 *  - files.school_id: файл школы (библиотека не показывает чужие файлы);
 *  - роль «Методист» (methodist): наполняет курсы своей школы;
 *  - права на наполнение (операции 5–24) — владельцу школы и методисту.
 *    Какой курс им можно менять, проверяет middleware course_access.
 */
class AddSchoolCoursesFoundation extends Migration
{
    const METHODIST_ROLE_ID = 7;
    const CONTENT_OPERATIONS = [5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24];

    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->integer('school_id')->unsigned()->nullable()->after('course_id');
            $table->foreign('school_id')->references('school_id')->on('schools')->onDelete('cascade');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->integer('school_id')->unsigned()->nullable()->after('file_id');
            $table->foreign('school_id')->references('school_id')->on('schools')->onDelete('cascade');
        });

        $now = now();

        DB::table('types_of_user_roles')->insertOrIgnore([
            'role_type_id' => self::METHODIST_ROLE_ID,
            'role_type_slug' => 'methodist',
            'show_status_id' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $names = ['kk' => 'Әдіскер', 'ru' => 'Методист'];
        foreach (DB::table('languages')->get(['lang_id', 'lang_tag']) as $language) {
            DB::table('types_of_user_roles_lang')->insert([
                'user_role_type_name' => $names[$language->lang_tag] ?? 'Методист',
                'role_type_id' => self::METHODIST_ROLE_ID,
                'lang_id' => $language->lang_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Владелец школы (2) и методист — наполнение курсов
        foreach ([2, self::METHODIST_ROLE_ID] as $roleId) {
            foreach (self::CONTENT_OPERATIONS as $operationId) {
                $exists = DB::table('operations_roles')
                    ->where('operation_type_id', $operationId)
                    ->where('role_type_id', $roleId)
                    ->exists();
                if (!$exists) {
                    DB::table('operations_roles')->insert([
                        'operation_type_id' => $operationId,
                        'role_type_id' => $roleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        DB::table('operations_roles')
            ->whereIn('role_type_id', [2, self::METHODIST_ROLE_ID])
            ->whereIn('operation_type_id', self::CONTENT_OPERATIONS)
            ->delete();
        DB::table('users_roles')->where('role_type_id', self::METHODIST_ROLE_ID)->delete();
        DB::table('types_of_user_roles_lang')->where('role_type_id', self::METHODIST_ROLE_ID)->delete();
        DB::table('types_of_user_roles')->where('role_type_id', self::METHODIST_ROLE_ID)->delete();

        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
}
