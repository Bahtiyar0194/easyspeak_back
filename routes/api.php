<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\ConferenceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\SentenceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TranslateController;
use App\Http\Controllers\TextToSpeechController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([
    'middleware' => 'api',
    'prefix' => 'v1'
], function ($router) {
    Route::group([
        'prefix' => 'demo'
    ], function ($router) {
        Route::post('/request', [DemoController::class, 'request']);
    });

    Route::group([
        'prefix' => 'auth'
    ], function ($router) {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/password_recovery', [AuthController::class, 'password_recovery']);
        Route::get('/check_email_hash/{hash}', [AuthController::class, 'check_email_hash']);
        Route::post('/new_password/{hash}', [AuthController::class, 'new_password']);

        Route::group(['prefix' => 'google'], function () {
            Route::get('/login', [AuthController::class, 'google_login']);
            Route::get('/callback', [AuthController::class, 'google_callback']);
        });

        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/change_mode/{role_type_id}', [AuthController::class, 'change_mode']);
            Route::post('/change_language/{lang_tag}', [AuthController::class, 'change_language']);
            //Route::post('/change_theme/{theme_slug}', [AuthController::class, 'change_theme']);
            //Route::post('/change_location/{location_id}', [AuthController::class, 'change_location']);
            // Route::post('/update', [AuthController::class, 'update']);
            // Route::post('/upload_avatar', [AuthController::class, 'upload_avatar']);
            // Route::post('/delete_avatar', [AuthController::class, 'delete_avatar']);
            // Route::post('/change_password', [AuthController::class, 'change_password']);
            Route::post('/logout', [AuthController::class, 'logout']);
        });
    });

    Route::group([
        'prefix' => 'locations'
    ], function ($router) {
        Route::get('/get', [LocationController::class, 'get']);
    });

    Route::group([
        'prefix' => 'media'
    ], function ($router) {

        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_attributes', [MediaController::class, 'get_attributes']);
            Route::post('/get', [MediaController::class, 'get_files']);
            Route::post('/add', [MediaController::class, 'add_file'])->middleware('check_roles');
            Route::post('/replace/{file_id}', [MediaController::class, 'replace_file'])->middleware('check_roles');
        });

        Route::get('get/{file_name}', [MediaController::class, 'get_file']);
    });

    Route::group([
        'prefix' => 'school'
    ], function ($router) {
        Route::post('/get', [SchoolController::class, 'get_school'])->middleware('check_subdomain');
        Route::get('/get_schools_from_city/{location_id}', [SchoolController::class, 'get_schools_from_city']);
        Route::get('/get_logo/{logo_file}/{logo_variable}', [SchoolController::class, 'get_logo']);
        Route::get('/get_favicon/{school_id}/{file_name}', [SchoolController::class, 'get_favicon']);

        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_attributes', [SchoolController::class, 'get_school_attributes']);
            Route::post('/set_attributes', [SchoolController::class, 'set_school_attributes']);
            Route::post('/update', [SchoolController::class, 'update']);
            Route::post('/upload_logo', [SchoolController::class, 'upload_logo']);
            Route::post('/delete_logo/{logo_variable}', [SchoolController::class, 'delete_logo']);
            Route::post('/upload_favicon', [SchoolController::class, 'upload_favicon']);
            Route::post('/delete_favicon', [SchoolController::class, 'delete_favicon']);
        });
    });

    Route::group([
        'prefix' => 'conferences'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::post('/create', [ConferenceController::class, 'create'])->middleware('check_roles');
            Route::post('/delete/{uuid}', [ConferenceController::class, 'delete'])->middleware('check_roles');
            Route::get('/get_attributes', [ConferenceController::class, 'get_attributes']);
            Route::get('/get_current_conferences', [ConferenceController::class, 'get_current_conferences']);
            Route::get('/get_conference/{conference_id}', [ConferenceController::class, 'get_conference']);
            Route::get('/get_conference_tasks/{conference_id}', [ConferenceController::class, 'get_conference_tasks']);
            Route::post('/run_task/{conference_id}/{task_id}', [ConferenceController::class, 'run_task']);
        });
    });

    Route::group([
        'prefix' => 'schedule'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_attributes', [ScheduleController::class, 'get_schedule_attributes']);
            Route::post('/get', [ScheduleController::class, 'get_schedule']);
            Route::post('/update/{uuid}', [ScheduleController::class, 'update']);
        });
    });

    Route::group([
        'prefix' => 'users'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::post('/get', [UserController::class, 'get_users']);
            Route::get('/get/{user_id}', [UserController::class, 'get_user']);
            Route::get('/get_roles', [UserController::class, 'get_roles']);
            Route::get('/get_user_attributes', [UserController::class, 'get_user_attributes']);
            Route::post('/invite', [UserController::class, 'invite_user'])->middleware('check_roles'); 
            Route::post('/update', [UserController::class, 'update_user'])->middleware('check_roles');
        });
    });

    Route::group([
        'prefix' => 'groups'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_group_attributes', [GroupController::class, 'get_group_attributes']);
            Route::post('/get', [GroupController::class, 'get_groups']);
            Route::post('/create', [GroupController::class, 'create'])->middleware('check_roles');
            Route::get('/get/{group_id}', [GroupController::class, 'get_group']);
            Route::post('/update/{group_id}', [GroupController::class, 'update'])->middleware('check_roles');
        });
    });

    Route::group([
        'prefix' => 'operations'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_operation_attributes', [OperationController::class, 'get_operation_attributes']);
            Route::post('/get', [OperationController::class, 'get_operations']);
            Route::get('/get/{user_operation_id}', [OperationController::class, 'get_operation']);
        });
    });

    Route::group([
        'prefix' => 'courses'
    ], function ($router) {
        Route::get('/get', [CourseController::class, 'get_courses']);
        Route::get('/get_levels_index/{course_slug}', [CourseController::class, 'get_levels']);
        Route::post('/send_request', [CourseController::class, 'send_request']);

        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_levels/{course_slug}', [CourseController::class, 'get_levels']);
            Route::get('/get_level/{course_slug}/{level_slug}', [CourseController::class, 'get_level']);
            Route::get('/{course_slug}/{level_slug}/get_lessons', [CourseController::class, 'get_lessons']);
            Route::get('/{course_slug}/{level_slug}/get_lesson/{lesson_id}', [CourseController::class, 'get_lesson']);
            Route::get('/get_material_types', [CourseController::class, 'get_material_types']);
            Route::get('/get_lesson_types', [CourseController::class, 'get_lesson_types']);
            Route::get('/get_structure', [CourseController::class, 'get_courses_structure']);

            Route::get('/get_grade/{user_id}', [CourseController::class, 'get_grade']);

            Route::post('/{course_slug}/{level_slug}/add_section', [CourseController::class, 'add_section'])->middleware('check_roles');
            Route::post('/{course_slug}/{level_slug}/{section_id}/add_lesson', [CourseController::class, 'add_lesson'])->middleware('check_roles');
            Route::post('/add_material/{lesson_id}', [CourseController::class, 'add_material'])->middleware('check_roles');
            Route::post('/edit_material/{lesson_id}/{lesson_material_id}', [CourseController::class, 'edit_material'])->middleware('check_roles');
            Route::post('/order_materials/{lesson_id}', [CourseController::class, 'order_materials'])->middleware('check_roles');
            Route::delete('/delete_material/{lesson_id}/{lesson_material_id}', [CourseController::class, 'delete_material'])->middleware('check_roles');
        });
    });

    Route::group([
        'prefix' => 'dictionary'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_dictionary_attributes', [DictionaryController::class, 'get_dictionary_attributes']);
            Route::post('/get', [DictionaryController::class, 'get_words']);
            Route::get('/get/{word_id}', [DictionaryController::class, 'get_word']);
            Route::post('/add', [DictionaryController::class, 'add'])->middleware('check_roles');
            Route::post('/update/{word_id}', [DictionaryController::class, 'update'])->middleware('check_roles');

            Route::post('/add_lesson_dictionary/{lesson_id}', [DictionaryController::class, 'add_lesson_dictionary'])->middleware('check_roles');
            Route::get('/get_lesson_dictionary/{lesson_id}', [DictionaryController::class, 'get_lesson_dictionary']);
        });
    });

    Route::group([
        'prefix' => 'sentences'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_sentence_attributes', [SentenceController::class, 'get_sentence_attributes']);
            Route::post('/get', [SentenceController::class, 'get_sentences']);
            Route::get('/get/{sentence_id}', [SentenceController::class, 'get_sentence']);
            Route::post('/add', [SentenceController::class, 'add'])->middleware('check_roles');
            Route::post('/update/{sentence_id}', [SentenceController::class, 'update'])->middleware('check_roles');
        });
    });

    Route::group([
        'prefix' => 'tasks'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_task_attributes', [TaskController::class, 'get_task_attributes']);
            Route::post('/get', [TaskController::class, 'get_tasks']);
            Route::get('/get_lesson_tasks/{lesson_id}', [TaskController::class, 'get_lesson_tasks']);
            Route::post('/order/{lesson_id}', [TaskController::class, 'order'])->middleware('check_roles');
            Route::delete('/delete_task/{lesson_id}/{task_id}', [TaskController::class, 'delete_task'])->middleware('check_roles');
            Route::post('/save_result/{task_id}', [TaskController::class, 'save_task_result'])->middleware('check_roles');
            Route::post('/change_result/{completed_task_id}', [TaskController::class, 'change_task_result'])->middleware('check_roles');
            Route::post('/check_answers/{task_id}', [TaskController::class, 'check_answers'])->middleware('check_roles');
            Route::post('/get_task_results', [TaskController::class, 'get_task_results']);

            Route::post('/create/missing_letters/{lesson_id}', [TaskController::class, 'create_missing_letters_task'])->middleware('check_roles');
            Route::post('/edit/missing_letters/{task_id}', [TaskController::class, 'edit_missing_letters_task'])->middleware('check_roles');
            Route::get('/get/missing_letters/{task_id}', [TaskController::class, 'get_missing_letters_task']);

            Route::post('/create/match_words_by_pictures/{lesson_id}', [TaskController::class, 'create_match_words_by_pictures_task'])->middleware('check_roles');
            Route::post('/edit/match_words_by_pictures/{task_id}', [TaskController::class, 'edit_match_words_by_pictures_task'])->middleware('check_roles');
            Route::get('/get/match_words_by_pictures/{task_id}', [TaskController::class, 'get_match_words_by_pictures_task']);

            Route::post('/create/form_a_sentence_out_of_the_words/{lesson_id}', [TaskController::class, 'create_form_a_sentence_out_of_the_words_task'])->middleware('check_roles');
            Route::post('/edit/form_a_sentence_out_of_the_words/{task_id}', [TaskController::class, 'edit_form_a_sentence_out_of_the_words_task'])->middleware('check_roles');
            Route::get('/get/form_a_sentence_out_of_the_words/{task_id}', [TaskController::class, 'get_form_a_sentence_out_of_the_words_task']);

            Route::post('/create/learning_words/{lesson_id}', [TaskController::class, 'create_learning_words_task'])->middleware('check_roles');
            Route::post('/edit/learning_words/{task_id}', [TaskController::class, 'edit_learning_words_task'])->middleware('check_roles');
            Route::get('/get/learning_words/{task_id}', [TaskController::class, 'get_learning_words_task']);

            Route::post('/create/form_a_word_out_of_the_letters/{lesson_id}', [TaskController::class, 'create_form_a_word_out_of_the_letters_task'])->middleware('check_roles');
            Route::post('/edit/form_a_word_out_of_the_letters/{task_id}', [TaskController::class, 'edit_form_a_word_out_of_the_letters_task'])->middleware('check_roles');
            Route::get('/get/form_a_word_out_of_the_letters/{task_id}', [TaskController::class, 'get_form_a_word_out_of_the_letters_task']);

            Route::post('/create/fill_in_the_blanks_in_the_sentence/{lesson_id}', [TaskController::class, 'create_fill_in_the_blanks_in_the_sentence_task'])->middleware('check_roles');
            Route::post('/edit/fill_in_the_blanks_in_the_sentence/{task_id}', [TaskController::class, 'edit_fill_in_the_blanks_in_the_sentence_task'])->middleware('check_roles');
            Route::get('/get/fill_in_the_blanks_in_the_sentence/{task_id}', [TaskController::class, 'get_fill_in_the_blanks_in_the_sentence_task']);

            Route::post('create/match_same_words/{lesson_id}', [TaskController::class, 'create_match_same_words_task'])->middleware('check_roles');
            Route::post('edit/match_same_words/{task_id}', [TaskController::class, 'edit_match_same_words_task'])->middleware('check_roles');
            Route::get('get/match_same_words/{task_id}', [TaskController::class, 'get_match_same_words_task']);

            Route::post('/create/find_an_extra_word/{lesson_id}', [TaskController::class, 'create_find_an_extra_word_task'])->middleware('check_roles');
            Route::post('/edit/find_an_extra_word/{task_id}', [TaskController::class, 'edit_find_an_extra_word_task'])->middleware('check_roles');
            Route::get('/get/find_an_extra_word/{task_id}', [TaskController::class, 'get_find_an_extra_word_task']);

            Route::post('/create/true_or_false/{lesson_id}', [TaskController::class, 'create_true_or_false_task'])->middleware('check_roles');
            Route::post('/edit/true_or_false/{task_id}', [TaskController::class, 'edit_true_or_false_task'])->middleware('check_roles');
            Route::get('/get/true_or_false/{task_id}', [TaskController::class, 'get_true_or_false_task']);

            Route::post('/create/match_sentences_with_materials/{lesson_id}', [TaskController::class, 'create_match_sentences_with_materials_task'])->middleware('check_roles');
            Route::post('/edit/match_sentences_with_materials/{task_id}', [TaskController::class, 'edit_match_sentences_with_materials_task'])->middleware('check_roles');
            Route::get('/get/match_sentences_with_materials/{task_id}', [TaskController::class, 'get_match_sentences_with_materials_task']);

            Route::post('/create/find_the_stressed_syllable/{lesson_id}', [TaskController::class, 'create_find_the_stressed_syllable_task'])->middleware('check_roles');
            Route::post('/edit/find_the_stressed_syllable/{task_id}', [TaskController::class, 'edit_find_the_stressed_syllable_task'])->middleware('check_roles');
            Route::get('/get/find_the_stressed_syllable/{task_id}', [TaskController::class, 'get_find_the_stressed_syllable_task']);

            Route::post('/create/answer_the_questions/{lesson_id}', [TaskController::class, 'create_answer_the_questions_task'])->middleware('check_roles');
            Route::post('/edit/answer_the_questions/{task_id}', [TaskController::class, 'edit_answer_the_questions_task'])->middleware('check_roles');
            Route::get('/get/answer_the_questions/{task_id}', [TaskController::class, 'get_answer_the_questions_task']);

            Route::post('/create/pronunciation_check/{lesson_id}', [TaskController::class, 'create_pronunciation_check_task'])->middleware('check_roles');
            Route::post('/edit/pronunciation_check/{task_id}', [TaskController::class, 'edit_pronunciation_check_task'])->middleware('check_roles');
            Route::get('/get/pronunciation_check/{task_id}', [TaskController::class, 'get_pronunciation_check_task']);

            // Route::get('/get/{sentence_id}', [SentenceController::class, 'get_sentence']);
            // Route::post('/add', [SentenceController::class, 'add'])->middleware('check_roles');
            // Route::post('/update/{sentence_id}', [SentenceController::class, 'update'])->middleware('check_roles');
        });
    });

    Route::group([
        'prefix' => 'payment'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get_attributes', [PaymentController::class, 'get_attributes']);
            Route::post('/get_payments', [PaymentController::class, 'get_payments']);
            Route::post('/handle', [PaymentController::class, 'handle']);
            Route::post('/accept_payment/{payment_id}', [PaymentController::class, 'accept_payment'])->middleware('check_roles');
        });

        Route::post('/tiptop/handle3ds', [PaymentController::class, 'tiptop_handle3ds']);
        Route::post('/tiptop/check', [PaymentController::class, 'tiptop_check']);
    });

    Route::group([
        'prefix' => 'openai'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/translate', [TranslateController::class, 'translate']);
        });
    });

    Route::group([
        'prefix' => 'elevenlabs'
    ], function ($router) {
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/list_voices', [TextToSpeechController::class, 'list_voices']);
            Route::get('/tts', [TextToSpeechController::class, 'tts']);
        });
    });
});