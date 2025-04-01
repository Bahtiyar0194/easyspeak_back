<?php
namespace App\Http\Controllers;

use App\Models\Dictionary;
use App\Models\DictionaryTranslate;
use App\Models\Language;
use App\Models\UploadConfiguration;
use App\Models\User;
use App\Models\Course;
use App\Models\MediaFile;

use Illuminate\Http\Request;
use Validator;
use DB;
use File;
use Image;
use Storage;

class DictionaryController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_dictionary_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $operators = Dictionary::leftJoin('users', 'users.user_id', '=', 'dictionary.operator_id')
        ->select(
            'users.user_id',
            'users.first_name',
            'users.last_name',
            DB::raw("CONCAT(users.last_name, ' ', users.first_name) AS full_name"),
            'users.avatar'
        )
        ->distinct()
        ->orderBy('users.last_name', 'asc')
        ->get();

        $courses = Dictionary::leftJoin('courses', 'dictionary.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();

        $all_courses = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();

        // Получаем статусы пользователя
        $statuses = DB::table('dictionary')
        ->leftJoin('types_of_status', 'dictionary.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->select(
            'dictionary.status_type_id',
            'types_of_status_lang.status_type_name'
        )
        ->groupBy('dictionary.status_type_id', 'types_of_status_lang.status_type_name')
        ->get();

        $attributes = new \stdClass();

        $attributes->all_courses = $all_courses;
        $attributes->courses = $courses;
        $attributes->operators = $operators;
        $attributes->statuses = $statuses;

        return response()->json($attributes, 200);
    }

    public function get_words(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $words = Dictionary::leftJoin('courses', 'dictionary.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('users as operator', 'dictionary.operator_id', '=', 'operator.user_id')
        ->leftJoin('types_of_status', 'dictionary.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'dictionary.word_id',
            'dictionary.word',
            'dictionary.transcription',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'dictionary.created_at',
            'courses_lang.course_name',
            'operator.first_name as operator_first_name',
            'operator.last_name as operator_last_name',
            'operator.avatar as operator_avatar',
            'types_of_status.color as status_color',
            'types_of_status_lang.status_type_name'
        )            
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy($sortKey, $sortDirection);

        // Применяем фильтрацию по параметрам из запроса
        $word = $request->word;
        $transcription = preg_replace('/^\[(.*)\]$/', '$1', $request->transcription);
        $courses_id = $request->courses;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;
        $operators_id = $request->operators;
        $statuses_id = $request->statuses;


        // Фильтрация по слову
        if (!empty($word)) {
            $words->where('dictionary.word', 'LIKE', '%' . $word . '%');
        }

        // Фильтрация по транскрипции
        if (!empty($transcription)) {
            $words->where('dictionary.transcription', 'LIKE', '%' . $transcription . '%');
        }

        // Фильтрация по курсу
        if (!empty($courses_id)) {
            $words->whereIn('courses.course_id', $courses_id);
        }

        // Фильтрация по операторам
        if(!empty($operators_id)){
            $words->whereIn('dictionary.operator_id', $operators_id);
        }

        // Фильтрация по статусу
        if (!empty($statuses_id)) {
            $words->whereIn('dictionary.status_type_id', $statuses_id);
        }

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $words->whereBetween('dictionary.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $words->where('dictionary.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $words->where('dictionary.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        //

        if ($request->image_file) {
            $words->whereNotNull('dictionary.image_file_id');
        }

        // Возвращаем пагинированный результат
        return response()->json($words->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_word(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $word = Dictionary::leftJoin('courses', 'dictionary.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('types_of_status', 'dictionary.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'dictionary.word_id',
            'dictionary.word',
            'dictionary.transcription',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'dictionary.created_at',
            'dictionary.operator_id',
            'dictionary.course_id',
            'courses_lang.course_name',
            'types_of_status.color as status_color',
            'types_of_status_lang.status_type_name'
        )            
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->where('dictionary.word_id', '=', $request->word_id)
        ->distinct()
        ->first();

        $operator = User::find($word->operator_id);

        $translates = DictionaryTranslate::leftJoin('languages', 'dictionary_translate.lang_id', '=', 'languages.lang_id')
        ->select(
            'dictionary_translate.word_translate',
            'languages.lang_tag'
        ) 
        ->where('dictionary_translate.word_id', '=', $request->word_id)
        ->get();

        $word->operator = $operator->only(['last_name', 'first_name', 'avatar']);
        $word->translates = $translates;

        return response()->json($word, 200);
    }

    public function add(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            'word' => 'required|string|between:1,100|unique:dictionary',
            'transcription' => 'required|string|between:2,100',
            'word_kk' => 'required|string|between:1,100',
            'word_ru' => 'required|string|between:1,100',
            'course_id' => 'required|numeric',
        ];

        if($request['upload_new_word_image_file'] == 'true'){
            
            $upload_config = UploadConfiguration::where('material_type_id', '=', 3)
            ->first();
            
            $rules['new_word_image_file'] = 'file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['new_word_image_file_id'] = 'numeric';
        }

        if($request['upload_new_word_audio_file'] == 'true'){
            
            $upload_config = UploadConfiguration::where('material_type_id', '=', 2)
            ->first();
            
            $rules['new_word_audio_file'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['new_word_audio_file_id'] = 'required|numeric';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();


        $new_word = new Dictionary();
        $new_word->word = trim(preg_replace('/\s+/', ' ', $request->word));
        $new_word->transcription = preg_replace('/^\[(.*)\]$/', '$1', $request->transcription);

        if($request['upload_new_word_image_file'] == 'true'){
    
            $word_image_file = $request->file('new_word_image_file');

            if($word_image_file){
                $file_name = $word_image_file->hashName();

                $resized_image = Image::make($word_image_file)->resize(500, null, function ($constraint) {
                    $constraint->aspectRatio();
                })->stream('png', 80);
                Storage::disk('local')->put('/public/'.$file_name, $resized_image);

                $new_file = new MediaFile();
                $new_file->file_name = $request['word'];
                $new_file->target = $file_name;
                $new_file->size = $word_image_file->getSize() / 1048576;
                $new_file->material_type_id = 3;
                $new_file->save();

                $new_word->image_file_id = $new_file->file_id;
            }
        }
        else{
            if($request['new_word_image_file_id']){
                $findFile = MediaFile::findOrFail($request['new_word_image_file_id']);
                $new_word->image_file_id = $findFile->file_id;
            }
        }

        if($request['upload_new_word_audio_file'] == 'true'){
    
            $word_audio_file = $request->file('new_word_audio_file');

            if($word_audio_file){
                $file_name = $word_audio_file->hashName();

                $word_audio_file->storeAs('/public/', $file_name);

                $new_file = new MediaFile();
                $new_file->file_name = $request['word'];
                $new_file->target = $file_name;
                $new_file->size = $word_audio_file->getSize() / 1048576;
                $new_file->material_type_id = 2;
                $new_file->save();

                $new_word->audio_file_id = $new_file->file_id;
            }
        }
        else{
            $findFile = MediaFile::findOrFail($request['new_word_audio_file_id']);
            $new_word->audio_file_id = $findFile->file_id;
        }

        $new_word->course_id = $request->course_id;
        $new_word->operator_id = $auth_user->user_id;
        $new_word->save();

        $new_word_translate = new DictionaryTranslate();
        $new_word_translate->word_translate = trim(preg_replace('/\s+/', ' ', $request->word_kk));
        $new_word_translate->word_id = $new_word->word_id;
        $new_word_translate->lang_id = 1;
        $new_word_translate->save();

        $new_word_translate = new DictionaryTranslate();
        $new_word_translate->word_translate = trim(preg_replace('/\s+/', ' ', $request->word_ru));
        $new_word_translate->word_id = $new_word->word_id;
        $new_word_translate->lang_id = 2;
        $new_word_translate->save();

        // $description = "Имя: {$new_user->last_name} {$new_user->first_name};\n E-Mail: {$request->email};\n Телефон: {$request->phone};\n Роли: " . implode(",", $role_names) . ".";

        // $user_operation = new UserOperation();
        // $user_operation->operator_id = $auth_user->user_id;
        // $user_operation->operation_type_id = 1;
        // $user_operation->description = $description;
        // $user_operation->save();

        return response()->json($new_word, 200);
    }

    public function update(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            'word' => 'required|string|between:1,100',
            'transcription' => 'required|string|between:2,100',
            'word_kk' => 'required|string|between:1,100',
            'word_ru' => 'required|string|between:1,100',
            'course_id' => 'required|numeric'
        ];

        if($request['upload_edit_word_image_file'] == 'true'){
            $upload_config = UploadConfiguration::where('material_type_id', '=', 3)
            ->first();
            
            $rules['edit_word_image_file'] = 'file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['edit_word_image_file_id'] = 'required|numeric';
        }

        if($request['upload_edit_word_audio_file'] == 'true'){
            
            $upload_config = UploadConfiguration::where('material_type_id', '=', 2)
            ->first();
            
            $rules['edit_word_audio_file'] = 'file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['edit_word_audio_file_id'] = 'numeric';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $edit_word = Dictionary::find($request->word_id);

        if(isset($edit_word)){
            $edit_word->word = trim(preg_replace('/\s+/', ' ', $request->word));
            $edit_word->transcription = preg_replace('/^\[(.*)\]$/', '$1', $request->transcription);

            if($request['upload_edit_word_image_file'] == 'true'){
    
                $word_image_file = $request->file('edit_word_image_file');
    
                if($word_image_file){
                    $file_name = $word_image_file->hashName();
    
                    $resized_image = Image::make($word_image_file)->resize(500, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->stream('png', 80);
                    Storage::disk('local')->put('/public/'.$file_name, $resized_image);
    
                    $new_file = new MediaFile();
                    $new_file->file_name = $request['word'];
                    $new_file->target = $file_name;
                    $new_file->size = $word_image_file->getSize() / 1048576;
                    $new_file->material_type_id = 3;
                    $new_file->save();
    
                    $edit_word->image_file_id = $new_file->file_id;
                }
            }
            else{
                if($request['edit_word_image_file_id']){
                    $findFile = MediaFile::findOrFail($request['edit_word_image_file_id']);
                    $edit_word->image_file_id = $findFile->file_id;
                }
            }
    
            if($request['upload_edit_word_audio_file'] == 'true'){
        
                $word_audio_file = $request->file('edit_word_audio_file');
    
                if($word_audio_file){
                    $file_name = $word_audio_file->hashName();
    
                    $word_audio_file->storeAs('/public/', $file_name);
    
                    $new_file = new MediaFile();
                    $new_file->file_name = $request['word'];
                    $new_file->target = $file_name;
                    $new_file->size = $word_audio_file->getSize() / 1048576;
                    $new_file->material_type_id = 2;
                    $new_file->save();
    
                    $edit_word->audio_file_id = $new_file->file_id;
                }
            }
            else{
                if($request['edit_word_audio_file_id']){
                    $findFile = MediaFile::findOrFail($request['edit_word_audio_file_id']);
                    $edit_word->audio_file_id = $findFile->file_id;
                }
            }

            $edit_word->course_id = $request->course_id;
            $edit_word->operator_id = $auth_user->user_id;
            $edit_word->save();

            DictionaryTranslate::where('word_id', $edit_word->word_id)
            ->delete();

            $new_word_translate = new DictionaryTranslate();
            $new_word_translate->word_translate = trim(preg_replace('/\s+/', ' ', $request->word_kk));
            $new_word_translate->word_id = $edit_word->word_id;
            $new_word_translate->lang_id = 1;
            $new_word_translate->save();
    
            $new_word_translate = new DictionaryTranslate();
            $new_word_translate->word_translate = trim(preg_replace('/\s+/', ' ', $request->word_ru));
            $new_word_translate->word_id = $edit_word->word_id;
            $new_word_translate->lang_id = 2;
            $new_word_translate->save();
    
            // $description = "Имя: {$new_user->last_name} {$new_user->first_name};\n E-Mail: {$request->email};\n Телефон: {$request->phone};\n Роли: " . implode(",", $role_names) . ".";
    
            // $user_operation = new UserOperation();
            // $user_operation->operator_id = $auth_user->user_id;
            // $user_operation->operation_type_id = 1;
            // $user_operation->description = $description;
            // $user_operation->save();
    
            return response()->json($edit_word, 200);
        }
    }
}
