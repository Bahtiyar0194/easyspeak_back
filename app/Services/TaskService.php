<?php

namespace App\Services;
use App\Models\Dictionary;
use App\Models\Sentence;
use App\Models\Task;
use App\Models\TaskLang;
use App\Models\TaskType;
use App\Models\TaskWord;
use App\Models\TaskSentence;
use App\Models\TaskQuestion;
use App\Models\TaskMaterial;
use App\Models\TaskAnswer;
use App\Models\TaskOption;
use App\Models\TaskSentenceMaterial;
use App\Models\MediaFile;
use App\Models\Block;
use App\Models\UploadConfiguration;
use App\Models\MaterialType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TaskService
{
    //Добавить задание
    public function newTask($request, $task_type_id){
        // Проверяем, существует ли тип задания
        $task_type = TaskType::findOrFail($task_type_id);

        $tasks_count = Task::where("lesson_id", $request->lesson_id)->count();

        $new_task = new Task();
        $new_task->task_slug = $request->task_slug;
        $new_task->task_example = $request->task_example ? $request->task_example : null;
        $new_task->task_type_id = $task_type_id;
        $new_task->lesson_id = $request->lesson_id;
        $new_task->operator_id = auth()->user()->user_id;
        $new_task->sort_num = $tasks_count + 1;
        $new_task->save();

        $new_task_lang = new TaskLang();
        $new_task_lang->task_name = $request->task_name_kk;
        $new_task_lang->task_id = $new_task->task_id;
        $new_task_lang->lang_id = 1;
        $new_task_lang->save();

        $new_task_lang = new TaskLang();
        $new_task_lang->task_name = $request->task_name_ru;
        $new_task_lang->task_id = $new_task->task_id;
        $new_task_lang->lang_id = 2;
        $new_task_lang->save();

        return $new_task;
    }

    //Редактировать задание
    public function editTask($request){
        $edit_task = Task::findOrFail($request->task_id);
    
        $edit_task->task_slug = $request->task_slug;
        $edit_task->task_example = $request->task_example ? $request->task_example : null;
        $edit_task->operator_id = auth()->user()->user_id;
        $edit_task->save();

        TaskLang::where('task_id', $edit_task->task_id)
        ->delete();

        $new_task_lang = new TaskLang();
        $new_task_lang->task_name = $request->task_name_kk;
        $new_task_lang->task_id = $edit_task->task_id;
        $new_task_lang->lang_id = 1;
        $new_task_lang->save();

        $new_task_lang = new TaskLang();
        $new_task_lang->task_name = $request->task_name_ru;
        $new_task_lang->task_id = $edit_task->task_id;
        $new_task_lang->lang_id = 2;
        $new_task_lang->save();

        return $edit_task;
    }

    public function getTaskResult($task_id, $learner_id){
        $task_result = new \stdClass();

        $task_answers = TaskAnswer::where('task_id', '=', $task_id)
        ->where('learner_id', '=', $learner_id)
        ->get();

        if(count($task_answers) > 0){
            $correct_anwers = [];
            $incorrect_answers = [];
            $correct_answers_count = 0;
            $incorrect_answers_count = 0;

            foreach ($task_answers as $key => $answer) {
                if($answer->is_correct == 1){
                    array_push($correct_anwers, $answer);
                    $correct_answers_count++;
                }
                else{
                    array_push($incorrect_answers, $answer);
                    $incorrect_answers_count++;
                }

                if(isset($answer->word_id)){
                    $word = Dictionary::where('word_id', '=', $answer->word_id)
                    ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
                    ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
                    ->select(
                        'dictionary.word',
                        'image_file.target as image_file',
                        'audio_file.target as audio_file'
                    )
                    ->first();

                    $answer->word = $word;
                }

                if(isset($answer->sentence_id)){
                    $sentence = Sentence::where('sentence_id', '=', $answer->sentence_id)
                    ->leftJoin('files as image_file', 'sentences.image_file_id', '=', 'image_file.file_id')
                    ->leftJoin('files as audio_file', 'sentences.audio_file_id', '=', 'audio_file.file_id')
                    ->select(
                        'sentences.sentence',
                        'image_file.target as image_file',
                        'audio_file.target as audio_file'
                    )
                    ->first();
;
                    $answer->sentence = $sentence;
                }
            }

            $task_result->correct_answers_count = $correct_answers_count;
            $task_result->incorrect_answers_count = $incorrect_answers_count;
            $task_result->answers = ['correct_answers' => $correct_anwers, 'incorrect_answers' => $incorrect_answers];
            $task_result->percentage = round(($correct_answers_count / count($task_answers)) * 100, 2);
            return $task_result;
        }
        else{
            $task_result->percentage = 0;
            return $task_result;
        }
    }

    public function findTask($task_id){
        $find_task = Task::findOrFail($task_id);

        $task_langs = TaskLang::where('task_id', '=', $find_task->task_id)
        ->get();

        $find_task->langs = $task_langs;
        $find_task->task_result = $this->getTaskResult($find_task->task_id, auth()->user()->user_id); 

        return $find_task;
    }

    //Добавить опции для задания
    public function addTaskOptions($task_id, $request){
        $new_task_option = new TaskOption();
        $new_task_option->task_id = $task_id;
        $new_task_option->show_audio_button = isset($request->show_audio_button) ? $request->show_audio_button : null;
        $new_task_option->play_audio_at_the_begin = isset($request->play_audio_at_the_begin) ? $request->play_audio_at_the_begin : null;
        $new_task_option->play_audio_with_the_correct_answer = isset($request->play_audio_with_the_correct_answer) ? $request->play_audio_with_the_correct_answer : null;
        $new_task_option->play_error_sound_with_the_incorrect_answer = isset($request->play_error_sound_with_the_incorrect_answer) ? $request->play_error_sound_with_the_incorrect_answer : null;
        $new_task_option->show_image = isset($request->show_image) ? $request->show_image : null;
        $new_task_option->show_word = isset($request->show_word) ? $request->show_word : null;
        $new_task_option->show_transcription = isset($request->show_transcription) ? $request->show_transcription : null;
        $new_task_option->show_translate = isset($request->show_translate) ? $request->show_translate : null;
        $new_task_option->show_options = isset($request->show_options) ? $request->show_options : null;
        $new_task_option->impression_limit = isset($request->impression_limit) ? $request->impression_limit : null;
        $new_task_option->seconds_per_word = isset($request->seconds_per_word) ? $request->seconds_per_word : null;
        $new_task_option->seconds_per_sentence = isset($request->seconds_per_sentence) ? $request->seconds_per_sentence : null;
        $new_task_option->seconds_per_section = isset($request->seconds_per_section) ? $request->seconds_per_section : null;
        $new_task_option->seconds_per_question = isset($request->seconds_per_question) ? $request->seconds_per_question : null;
        $new_task_option->in_the_main_lang = isset($request->in_the_main_lang) ? $request->in_the_main_lang : null;
        $new_task_option->find_word_option = isset($request->find_word_option) ? $request->find_word_option : null;
        $new_task_option->match_words_by_pictures_option = isset($request->match_words_by_pictures_option) ? $request->match_words_by_pictures_option : null;
        $new_task_option->answer_the_questions_option = isset($request->answer_the_questions_option) ? $request->answer_the_questions_option : null;
        $new_task_option->options_num = isset($request->options_num) ? $request->options_num : null;
        $new_task_option->random_order = isset($request->random_order) ? true : false;
        $new_task_option->random_order_pictures = isset($request->random_order_pictures) ? true : null;
        $new_task_option->match_by_typing = isset($request->match_by_typing) ? 1 : 0;
        $new_task_option->match_by_clicking = isset($request->match_by_clicking) ? 1 : 0;
        $new_task_option->match_by_drag_and_drop = isset($request->match_by_drag_and_drop) ? 1 : 0;
        $new_task_option->max_attempts = isset($request->max_attempts) ? $request->max_attempts : 0;
        $new_task_option->max_answer_attempts = isset($request->max_answer_attempts) ? $request->max_answer_attempts : 0;
        $new_task_option->show_materials_option = isset($request->show_materials_option) ? $request->show_materials_option : null;
        $new_task_option->sentence_material_type_slug = isset($request->sentence_material_type_slug) ? $request->sentence_material_type_slug : null;
        $new_task_option->save();
    }


    // Получить слова задания
    public function getTaskWords($task_id, $language, $task_options){

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->leftJoin('files as image_file', 'dictionary.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'dictionary.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'task_words.task_word_id',
            'task_words.word_id',
            'dictionary.word',
            'dictionary.transcription',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'dictionary_translate.word_translate'
        )
        ->where('task_words.task_id', '=', $task_id)
        ->where('dictionary_translate.lang_id', '=', $language->lang_id);
    
        // Случайный порядок, если указан
        if ($task_options->random_order == 1) {
            $task_words->inRandomOrder();
        }
    
        $task_words = $task_words->get();

        return $task_words;
    }

    // Получить фразы для задания
    public function getTaskSentences($task_id, $language, $task_options){
        $task_sentences = TaskSentence::leftJoin('sentences', 'task_sentences.sentence_id', '=', 'sentences.sentence_id')
        ->leftJoin('sentences_translate', 'sentences.sentence_id', '=', 'sentences_translate.sentence_id')
        ->leftJoin('files as image_file', 'sentences.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'sentences.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'task_sentences.task_sentence_id',
            'task_sentences.sentence_id',
            'task_sentences.answer',
            'sentences.sentence',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'sentences_translate.sentence_translate'
        )
        ->where('task_sentences.task_id', '=', $task_id)
        ->where('sentences_translate.lang_id', '=', $language->lang_id)  
        ->distinct();

        if($task_options->random_order == 1){
            $task_sentences->inRandomOrder();
        }

        $task_sentences = $task_sentences->get();

        return $task_sentences;
    }

    // Получить вопросы для задания
    public function getTaskQuestions($task_id, $language, $task_options){
        $task_questions = TaskQuestion::leftJoin('sentences', 'task_questions.question_id', '=', 'sentences.sentence_id')
        ->leftJoin('sentences_translate', 'sentences.sentence_id', '=', 'sentences_translate.sentence_id')
        ->leftJoin('files as image_file', 'sentences.image_file_id', '=', 'image_file.file_id')
        ->leftJoin('files as audio_file', 'sentences.audio_file_id', '=', 'audio_file.file_id')
        ->select(
            'sentences.sentence_id',
            'task_questions.task_question_id',
            'task_questions.predefined_answer',
            'sentences.sentence',
            'image_file.target as image_file',
            'audio_file.target as audio_file',
            'sentences_translate.sentence_translate'
        )
        ->where('task_questions.task_id', '=', $task_id)
        ->where('sentences_translate.lang_id', '=', $language->lang_id)  
        ->distinct();

        if($task_options->random_order == 1){
            $task_questions->inRandomOrder();
        }

        $task_questions = $task_questions->get();

        return $task_questions;
    }


    // Проверить материалы к заданию
    public function validateTaskMaterials($request)
    {
        $rules = [];
        if(isset($request->task_materials)){
            $task_materials = json_decode($request->task_materials);

            if(count($task_materials) > 0){
                foreach ($task_materials as $key => $material) {
                    if(!isset($material->task_material_id)){
                        if($material->material_type_category == 'file'){
                            if($request['upload_task_file_'.$key] == 'true'){
                                $rules['file_name_'.$key] = 'required';
                                
                                $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
                                ->where('types_of_materials.material_type_slug', '=', $material->material_type_slug)
                                ->select(
                                    'upload_configuration.max_file_size_mb',
                                    'upload_configuration.mimes'
                                )
                                ->first();
                                
                                $rules['file_'.$key] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
                            }
                            else{
                                $rules['file_from_library_'.$key] = 'required|numeric';
                            }
                        }
                        elseif($material->material_type_category == 'block'){
                            if($material->material_type_slug == 'text'){
                                $rules['text_'.$key] = 'required|string|min:8';
                            }
                            elseif($material->material_type_slug == 'table'){
                                $rules['table_'.$key] = 'required|string|min:3';
                                $rules['table_'.$key.'_options'] = 'required';
                            }
                        }
                    }
                }
    
                $validator = Validator::make($request->all(), $rules);
    
                if ($validator->fails()) {
                    return $validator->errors();
                }
            }
        }
    }

    //Добавить материалы к заданию
    public function addMaterialsToTask($task_id, $request)
    {
        if(isset($request->task_materials)){
            $task_materials = json_decode($request->task_materials);
            $leave_task_ids = [];

            if(count($task_materials) > 0){
                foreach ($task_materials as $key => $material) {
                    if(isset($material->task_material_id)){
                        array_push($leave_task_ids, $material->task_material_id);
                    }
                }

                if(count($leave_task_ids) > 0){
                    TaskMaterial::where('task_id', $task_id)
                    ->whereNotIn('task_material_id', $leave_task_ids)
                    ->delete();
                }
                else{
                    TaskMaterial::where('task_id', $task_id)
                    ->delete();
                }

                foreach ($task_materials as $key => $material) {
                    if(!isset($material->task_material_id)){
                        $new_task_material = new TaskMaterial();
                        $new_task_material->task_id = $task_id;
        
                        if($material->material_type_category == 'file'){
                            if($request['upload_task_file_'.$key] == 'true'){
        
                                $file = $request->file('file_'.$key);
        
                                if($file){
                                    $file_name = $file->hashName();
        
                                    if($material->material_type_slug == 'image'){
                                        $resized_image = Image::make($file)->resize(500, null, function ($constraint) {
                                            $constraint->aspectRatio();
                                        })->stream('png', 80);
                                        Storage::disk('local')->put('/public/'.$file_name, $resized_image);
                                    }
                                    else{
                                        $file->storeAs('/public/', $file_name);
                                    }
        
                                    $new_file = new MediaFile();
                                    $new_file->file_name = $request['file_name_'.$key];
                                    $new_file->target = $file_name;
                                    $new_file->size = $file->getSize() / 1048576;
                                    $new_file->material_type_id = $material->material_type_id;
                                    $new_file->save();
    
                                    $new_task_material->file_id = $new_file->file_id;
                                }
                            }
                            else{
                                $findFile = MediaFile::findOrFail($request['file_from_library_'.$key]);
                                $new_task_material->file_id = $findFile->file_id;
                            }
                        }
                        elseif($material->material_type_category == 'block'){
                            $new_block = new Block();
        
                            if($material->material_type_slug == 'text'){
                                $new_block->content = $request['text_'.$key];
                            }
                            elseif($material->material_type_slug == 'table'){
                                $new_block->content = $request['table_'.$key];
                                $new_block->options = $request['table_'.$key.'_options'];
                            }
                
                            $new_block->material_type_id = $material->material_type_id;
                            $new_block->save();
                
                            $new_task_material->block_id = $new_block->block_id;
                        }

                        $new_task_material->sort_num = $key + 1;
        
                        $new_task_material->save();
                    }
                    else{
                        $sort_task_material = TaskMaterial::findOrFail($material->task_material_id);
                        $sort_task_material->sort_num = $key + 1;
                        $sort_task_material->save();
                    }
                }
            }
        }
    }

    // Получить материалы для задания
    public function getTaskMaterials($task_id){
        $task_materials = TaskMaterial::leftJoin('files', 'task_materials.file_id', '=', 'files.file_id')
        ->leftJoin('types_of_materials as file_types', 'files.material_type_id', '=', 'file_types.material_type_id')
        ->leftJoin('blocks', 'task_materials.block_id', '=', 'blocks.block_id')
        ->leftJoin('types_of_materials as block_types', 'blocks.material_type_id', '=', 'block_types.material_type_id')
        ->select(
            'task_materials.task_material_id',
            'task_materials.sort_num',
            'files.target',
            'blocks.content',
            'blocks.options',
            'file_types.material_type_slug as file_material_type_slug',
            'block_types.material_type_slug as block_material_type_slug'
        )
        ->where('task_materials.task_id', '=', $task_id)
        ->orderBy('task_materials.sort_num', 'asc')
        ->get();

        return $task_materials;
    }

    // Проверить материалы к фразе
    public function validateSentenceMaterials($sentences, $request)
    {
        $rules = [];

        if (count($sentences) > 0) {
            foreach ($sentences as $sentence_key => $sentence) { 
                if(!isset($sentence->material->task_sentence_material_id)){
                    if($request['upload_sentence_file_'.$sentence_key] == 'true'){
                        $rules['file_name_'.$sentence_key] = 'required';
                        
                        $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
                        ->where('types_of_materials.material_type_slug', '=', $request->sentence_material_type_slug)
                        ->select(
                            'upload_configuration.max_file_size_mb',
                            'upload_configuration.mimes'
                        )
                        ->first();
                        
                        $rules['file_'.$sentence_key] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
                    }
                    else{
                        $rules['file_from_library_'.$sentence_key] = 'required|numeric';
                    }
                }
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return $validator->errors();
            }
        }
    }

    //Добавить материалы к фразе
    public function addMaterialsToTaskSentence($sentenceIndex, $task_sentence_id, $request)
    {
        $new_task_sentence_material = new TaskSentenceMaterial();
        $new_task_sentence_material->task_sentence_id = $task_sentence_id;

        if($request['upload_sentence_file_'.$sentenceIndex] == 'true'){

            $file = $request->file('file_'.$sentenceIndex);

            if($file){
                $file_name = $file->hashName();

                $material_type = MaterialType::where('material_type_slug', '=', $request->sentence_material_type_slug)
                ->first();

                if (!$material_type) {
                    return response()->json(['error' => 'Material type is not found'], 404);
                }

                if($material_type->material_type_slug == 'image'){
                    $resized_image = Image::make($file)->resize(500, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->stream('png', 80);
                    Storage::disk('local')->put('/public/'.$file_name, $resized_image);
                }
                else{
                    $file->storeAs('/public/', $file_name);
                }

                $new_file = new MediaFile();
                $new_file->file_name = $request['file_name_'.$sentenceIndex];
                $new_file->target = $file_name;
                $new_file->size = $file->getSize() / 1048576;
                $new_file->material_type_id = $material_type->material_type_id;
                $new_file->save();

                $new_task_sentence_material->file_id = $new_file->file_id;
            }
        }
        else{
            $findFile = MediaFile::findOrFail($request['file_from_library_'.$sentenceIndex]);
            $new_task_sentence_material->file_id = $findFile->file_id;
        }

        $new_task_sentence_material->save();
    }

    // Получить материалы для фразы
    public function getTaskSentenceMaterial($task_sentence_id){
        $task_sentence_material = TaskSentenceMaterial::leftJoin('files', 'task_sentence_materials.file_id', '=', 'files.file_id')
        ->leftJoin('types_of_materials as file_types', 'files.material_type_id', '=', 'file_types.material_type_id')
        ->leftJoin('blocks', 'task_sentence_materials.block_id', '=', 'blocks.block_id')
        ->leftJoin('types_of_materials as block_types', 'blocks.material_type_id', '=', 'block_types.material_type_id')
        ->select(
            'task_sentence_materials.task_sentence_material_id',
            'files.target',
            'blocks.content',
            'blocks.options',
            'file_types.material_type_slug as file_material_type_slug',
            'block_types.material_type_slug as block_material_type_slug'
        )
        ->where('task_sentence_materials.task_sentence_id', '=', $task_sentence_id)
        ->first();

        return $task_sentence_material;
    }
}