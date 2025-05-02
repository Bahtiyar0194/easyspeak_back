<?php
namespace App\Http\Controllers;

use App\Models\Sentence;
use App\Models\SentenceTranslate;
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

class SentenceController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_sentence_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $operators = Sentence::leftJoin('users', 'users.user_id', '=', 'sentences.operator_id')
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

        $courses = Sentence::leftJoin('courses', 'sentences.course_id', '=', 'courses.course_id')
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
        $statuses = DB::table('sentences')
        ->leftJoin('types_of_status', 'sentences.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->select(
            'sentences.status_type_id',
            'types_of_status_lang.status_type_name'
        )
        ->groupBy('sentences.status_type_id', 'types_of_status_lang.status_type_name')
        ->get();

        $attributes = new \stdClass();

        $attributes->all_courses = $all_courses;
        $attributes->courses = $courses;
        $attributes->operators = $operators;
        $attributes->statuses = $statuses;

        return response()->json($attributes, 200);
    }

    public function get_sentences(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $sentences = Sentence::leftJoin('courses', 'sentences.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('users as operator', 'sentences.operator_id', '=', 'operator.user_id')
        ->leftJoin('types_of_status', 'sentences.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->leftJoin('files as image_file', 'sentences.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'sentences.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'sentences.sentence_id',
            'sentences.sentence',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'sentences.created_at',
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
        $sentence = $request->sentence;
        // $transcription = preg_replace('/^\[(.*)\]$/', '$1', $request->transcription);
        $courses_id = $request->courses;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;
        $operators_id = $request->operators;
        $statuses_id = $request->statuses;


        // Фильтрация по слову
        if (!empty($sentence)) {
            $sentences->where('sentences.sentence', 'LIKE', '%' . $sentence . '%');
        }

        // Фильтрация по транскрипции
        // if (!empty($transcription)) {
        //     $sentences->where('sentences.transcription', 'LIKE', '%' . $transcription . '%');
        // }

        // Фильтрация по курсу
        if (!empty($courses_id)) {
            $sentences->whereIn('courses.course_id', $courses_id);
        }

        // Фильтрация по операторам
        if(!empty($operators_id)){
            $sentences->whereIn('sentences.operator_id', $operators_id);
        }

        // Фильтрация по статусу
        if (!empty($statuses_id)) {
            $sentences->whereIn('sentences.status_type_id', $statuses_id);
        }

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $sentences->whereBetween('sentences.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $sentences->where('sentences.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $sentences->where('sentences.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        // Возвращаем пагинированный результат
        return response()->json($sentences->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_sentence(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $sentence = Sentence::leftJoin('courses', 'sentences.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('types_of_status', 'sentences.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->leftJoin('files as image_file', 'sentences.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'sentences.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'sentences.sentence_id',
            'sentences.sentence',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'sentences.created_at',
            'sentences.operator_id',
            'sentences.course_id',
            'courses_lang.course_name',
            'types_of_status.color as status_color',
            'types_of_status_lang.status_type_name'
        )            
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->where('sentences.sentence_id', '=', $request->sentence_id)
        ->distinct()
        ->first();

        $operator = User::find($sentence->operator_id);

        $translates = SentenceTranslate::leftJoin('languages', 'sentences_translate.lang_id', '=', 'languages.lang_id')
        ->select(
            'sentences_translate.sentence_translate',
            'languages.lang_tag'
        ) 
        ->where('sentences_translate.sentence_id', '=', $request->sentence_id)
        ->get();

        $sentence->operator = $operator->only(['last_name', 'first_name', 'avatar']);
        $sentence->translates = $translates;

        return response()->json($sentence, 200);
    }

    public function add(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            'sentence' => 'required|string|between:1,100|unique:sentences',
            'sentence_kk' => 'required|string|between:1,100',
            'sentence_ru' => 'required|string|between:1,100',
            'course_id' => 'required|numeric',
        ];
        if($request['upload_new_sentence_audio_file'] == 'generate'){
            $rules['generate_new_sentence_audio_file'] = 'required|string';
        }
        elseif($request['upload_new_sentence_audio_file'] == 'true'){
                    
            $upload_config = UploadConfiguration::where('material_type_id', '=', 2)
            ->first();
            
            $rules['new_sentence_audio_file'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['new_sentence_audio_file_id'] = 'required|numeric';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $new_sentence = new Sentence();
        $new_sentence->sentence = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence)));
        $new_sentence->transcription = preg_replace('/^\[(.*)\]$/', '$1', $request->transcription);

        if($request['upload_new_sentence_audio_file'] == 'generate'){;
            $binary = base64_decode($request->input('generate_new_sentence_audio_file'));
            $file_name = uniqid() . '.mp3';

            Storage::put("/public/{$file_name}", $binary);
            $file_size = Storage::size("/public/{$file_name}");

            $new_file = new MediaFile();
            $new_file->file_name = $request['sentence'];
            $new_file->target = $file_name;
            $new_file->size = $file_size / 1048576;
            $new_file->material_type_id = 2;
            $new_file->save();

            $new_sentence->audio_file_id = $new_file->file_id;
        }
        elseif($request['upload_new_sentence_audio_file'] == 'true'){
    
            $sentence_audio_file = $request->file('new_sentence_audio_file');

            if($sentence_audio_file){
                $file_name = $sentence_audio_file->hashName();

                $sentence_audio_file->storeAs('/public/', $file_name);

                $new_file = new MediaFile();
                $new_file->file_name = $request['sentence'];
                $new_file->target = $file_name;
                $new_file->size = $sentence_audio_file->getSize() / 1048576;
                $new_file->material_type_id = 2;
                $new_file->save();

                $new_sentence->audio_file_id = $new_file->file_id;
            }
        }
        else{
            $findFile = MediaFile::findOrFail($request['new_sentence_audio_file_id']);
            $new_sentence->audio_file_id = $findFile->file_id;
        }

        $new_sentence->course_id = $request->course_id;
        $new_sentence->operator_id = $auth_user->user_id;
        $new_sentence->save();

        $new_sentence_translate = new SentenceTranslate();
        $new_sentence_translate->sentence_translate = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence_kk)));
        $new_sentence_translate->sentence_id = $new_sentence->sentence_id;
        $new_sentence_translate->lang_id = 1;
        $new_sentence_translate->save();

        $new_sentence_translate = new SentenceTranslate();
        $new_sentence_translate->sentence_translate = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence_ru)));
        $new_sentence_translate->sentence_id = $new_sentence->sentence_id;
        $new_sentence_translate->lang_id = 2;
        $new_sentence_translate->save();

        // $description = "Имя: {$new_user->last_name} {$new_user->first_name};\n E-Mail: {$request->email};\n Телефон: {$request->phone};\n Роли: " . implode(",", $role_names) . ".";

        // $user_operation = new UserOperation();
        // $user_operation->operator_id = $auth_user->user_id;
        // $user_operation->operation_type_id = 1;
        // $user_operation->description = $description;
        // $user_operation->save();

        return response()->json($new_sentence, 200);
    }

    public function update(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            'sentence' => 'required|string|between:1,100',
            'sentence_kk' => 'required|string|between:1,100',
            'sentence_ru' => 'required|string|between:1,100',
            'course_id' => 'required|numeric',
        ];

        if($request['upload_edit_sentence_audio_file'] == 'true'){
            $upload_config = UploadConfiguration::where('material_type_id', '=', 2)
            ->first();
            
            $rules['edit_sentence_audio_file'] = 'file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
        }
        else{
            $rules['edit_sentence_audio_file_id'] = 'numeric';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $edit_sentence = Sentence::find($request->sentence_id);

        if(isset($edit_sentence)){
            $edit_sentence->sentence = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence)));

            if($request['upload_edit_sentence_audio_file'] == 'true'){
        
                $sentence_audio_file = $request->file('edit_sentence_audio_file');
    
                if($sentence_audio_file){
                    $file_name = $sentence_audio_file->hashName();
    
                    $sentence_audio_file->storeAs('/public/', $file_name);
    
                    $new_file = new MediaFile();
                    $new_file->file_name = $request['sentence'];
                    $new_file->target = $file_name;
                    $new_file->size = $sentence_audio_file->getSize() / 1048576;
                    $new_file->material_type_id = 2;
                    $new_file->save();
    
                    $edit_sentence->audio_file_id = $new_file->file_id;
                }
            }
            else{
                if($request['edit_sentence_audio_file_id']){
                    $findFile = MediaFile::findOrFail($request['edit_sentence_audio_file_id']);
                    $edit_sentence->audio_file_id = $findFile->file_id;
                }
            }

            $edit_sentence->course_id = $request->course_id;
            $edit_sentence->operator_id = $auth_user->user_id;
            $edit_sentence->save();

            SentenceTranslate::where('sentence_id', $edit_sentence->sentence_id)
            ->delete();

            $new_sentence_translate = new SentenceTranslate();
            $new_sentence_translate->sentence_translate = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence_kk)));
            $new_sentence_translate->sentence_id = $edit_sentence->sentence_id;
            $new_sentence_translate->lang_id = 1;
            $new_sentence_translate->save();
    
            $new_sentence_translate = new SentenceTranslate();
            $new_sentence_translate->sentence_translate = trim(preg_replace('/\s+/', ' ', normalizeQuotes($request->sentence_ru)));
            $new_sentence_translate->sentence_id = $edit_sentence->sentence_id;
            $new_sentence_translate->lang_id = 2;
            $new_sentence_translate->save();
    
            // $description = "Имя: {$new_user->last_name} {$new_user->first_name};\n E-Mail: {$request->email};\n Телефон: {$request->phone};\n Роли: " . implode(",", $role_names) . ".";
    
            // $user_operation = new UserOperation();
            // $user_operation->operator_id = $auth_user->user_id;
            // $user_operation->operation_type_id = 1;
            // $user_operation->description = $description;
            // $user_operation->save();
    
            return response()->json($edit_sentence, 200);
        }
    }
}
