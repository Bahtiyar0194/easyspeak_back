<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskLang;
use App\Models\TaskType;
use App\Models\TaskWord;
use App\Models\TaskSentence;
use App\Models\TaskMaterial;
use App\Models\TaskOption;
use App\Models\MediaFile;
use App\Models\Block;
use App\Models\UploadConfiguration;
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

        $new_task = new Task();
        $new_task->task_slug = $request->task_slug;
        $new_task->task_example = $request->task_example ? $request->task_example : null;
        $new_task->task_type_id = $task_type_id;
        $new_task->lesson_id = $request->lesson_id;
        $new_task->operator_id = auth()->user()->user_id;
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
        $new_task_option->in_the_main_lang = isset($request->in_the_main_lang) ? $request->in_the_main_lang : null;
        $new_task_option->find_word_option = isset($request->find_word_option) ? $request->find_word_option : null;
        $new_task_option->options_num = isset($request->options_num) ? $request->options_num : null;
        $new_task_option->random_order = isset($request->random_order) ? true : false;
        $new_task_option->match_by_typing = isset($request->match_by_typing) ? 1 : 0;
        $new_task_option->match_by_clicking = isset($request->match_by_clicking) ? 1 : 0;
        $new_task_option->match_by_drag_and_drop = isset($request->match_by_drag_and_drop) ? 1 : 0;
        $new_task_option->max_attempts = isset($request->max_attempts) ? $request->max_attempts : null;
        $new_task_option->show_materials_option = isset($request->show_materials_option) ? $request->show_materials_option : null;
        $new_task_option->save();
    }


    // Получить слова задания
    public function getTaskWords($task_id, $language, $task_options){

        $task_words = TaskWord::leftJoin('dictionary', 'task_words.word_id', '=', 'dictionary.word_id')
        ->leftJoin('dictionary_translate', 'dictionary.word_id', '=', 'dictionary_translate.word_id')
        ->select(
            'task_words.task_word_id',
            'dictionary.word',
            'dictionary.transcription',
            'dictionary.image_file',
            'dictionary.audio_file',
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
        ->select(
            'task_sentences.task_sentence_id',
            'task_sentences.answer',
            'sentences.sentence',
            'sentences.audio_file',
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


    // Проверить материалы к заданию
    public function validateTaskMaterials($request)
    {
        $rules = [];
            if(isset($request->task_materials)){
                $task_materials = json_decode($request->task_materials);

                if(count($task_materials) > 0){
                    foreach ($task_materials as $key => $material) {
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
        // Проверяем, существует ли задача
        $task = Task::findOrFail($task_id);

        if(isset($request->task_materials)){
            $task_materials = json_decode($request->task_materials);

            if(count($task_materials) > 0){
                foreach ($task_materials as $key => $material) {
    
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
                        }
            
                        $new_block->material_type_id = $material->material_type_id;
                        $new_block->save();
            
                        $new_task_material->block_id = $new_block->block_id;
                    }
    
                    $new_task_material->save();
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
            'files.target',
            'blocks.content',
            'file_types.material_type_slug as file_material_type_slug',
            'block_types.material_type_slug as block_material_type_slug'
        )
        ->where('task_materials.task_id', '=', $task_id)
        ->get();

        return $task_materials;
    }
}