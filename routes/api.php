<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\SentenceController;
use App\Http\Controllers\TaskController;

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
        'prefix' => 'auth'
    ], function ($router) {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);

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
        'prefix' => 'media'
    ], function ($router) {
        Route::get('/{file_name}', [MediaController::class, 'get_file']);
    });

    Route::group([
        'prefix' => 'school'
    ], function ($router) {
        Route::get('/get', [SchoolController::class, 'get_school'])->middleware('check_subdomain');
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
        Route::group(['middleware' => ['auth:sanctum']], function () {
            Route::get('/get', [CourseController::class, 'get_courses']);
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

            Route::post('/missing_letters', [TaskController::class, 'create_missing_letters_task'])->middleware('check_roles');
            Route::get('/missing_letters/{task_id}', [TaskController::class, 'get_missing_letters_task']);

            Route::post('/match_words_by_pictures', [TaskController::class, 'create_match_words_by_pictures_task'])->middleware('check_roles');
            Route::get('/match_words_by_pictures/{task_id}', [TaskController::class, 'get_match_words_by_pictures_task']);

            Route::post('/form_a_sentence_out_of_the_words', [TaskController::class, 'create_form_a_sentence_out_of_the_words_task'])->middleware('check_roles');
            Route::get('/form_a_sentence_out_of_the_words/{task_id}', [TaskController::class, 'get_form_a_sentence_out_of_the_words_task']);

            // Route::get('/get/{sentence_id}', [SentenceController::class, 'get_sentence']);
            // Route::post('/add', [SentenceController::class, 'add'])->middleware('check_roles');
            // Route::post('/update/{sentence_id}', [SentenceController::class, 'update'])->middleware('check_roles');
        });
    });
});