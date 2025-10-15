<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonType;
use App\Models\MaterialType;
use App\Models\LessonMaterial;
use App\Models\Task;
use App\Models\Language;
use App\Models\MediaFile;
use App\Models\Block;
use App\Models\UploadConfiguration;
use App\Models\School;

use Mail;
use App\Mail\CourseRequestMail;

use Illuminate\Http\Request;
use Validator;
use DB;
use File;
use Image;
use Storage;
use Auth;
use Log;

use App\Services\CourseService;
use App\Services\TaskService;

class CourseController extends Controller
{

    protected $courseService;
    protected $taskService;

    public function __construct(Request $request, CourseService $courseService, TaskService $taskService)
    {
        $this->courseService = $courseService;
        $this->taskService = $taskService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_courses(Request $request)
    {
        $courses = $this->courseService->getCourses($request);

        return response()->json($courses, 200);
    }

    public function get_levels(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);

        $levels = $this->courseService->getCourseLevels($course->course_id, $language->lang_id);

        if(Auth::check()){
            foreach ($levels as $key => $level) {
                if($level->is_available_always === 1){
                    $level->is_available = true;
                }
                else{
                    $level->is_available = $this->courseService->levelIsAvailable($level->level_id, auth()->user()->user_id);
                }

                $sections = $this->courseService->getLevelSections($level->level_id);

                $levelCompletedPercent = 0;

                foreach ($sections as $s => $section) {
                    $sectionCompletedPercent = 0;

                    $lessons = $this->courseService->getLessons($section->section_id, $language->lang_id);

                    foreach ($lessons as $l => $lesson) {
                        $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, true);

                        $completedTasksCount = 0;
                        $completedTasksPercent = 0;

                        foreach ($lesson->tasks as $key => $task) {
                            if ($task->task_result && $task->task_result->completed === true) {
                                $completedTasksCount++;
                                $completedTasksPercent += $task->task_result->percentage;
                            }
                        }

                        $lesson->completed_tasks_count = $completedTasksCount;
                        $lesson->completed_tasks_percent = count($lesson->tasks) > 0
                            ? $completedTasksPercent / count($lesson->tasks)
                            : 0;

                        $sectionCompletedPercent += $lesson->completed_tasks_percent;
                    }

                    $section->lessons = $lessons;
                    $section->completed_percent = count($lessons) > 0
                        ? $sectionCompletedPercent / count($lessons)
                        : 0;

                    // 👇 добавляем в общий процент уровня
                    $levelCompletedPercent += $section->completed_percent;
                }

                // 👇 итоговый процент по уровню
                $level->completed_percent = count($sections) > 0
                ? $levelCompletedPercent / count($sections)
                : 0;
            }
        }

        $levels = $levels
        ->sortByDesc('completed_percent') // сначала сортируем по проценту
        ->sortByDesc('is_available')      // потом по доступности (она будет приоритетнее)
        ->values();

        $data = new \stdClass();
        $data->course = $course;
        $data->levels = $levels;
        
        return response()->json($data, 200);
    }

    public function get_level(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);

        $level = $this->courseService->getCourseLevel($course->course_id, $request->level_slug, $language->lang_id);

        $data = new \stdClass();
        $data->course = $course;
        $data->level = $level;
        
        return response()->json($data, 200);
    }

    public function get_lessons(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $auth_user = auth()->user();

        $data = new \stdClass();

        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);

        $levelCompletedPercent = 0;

        $level = $this->courseService->getCourseLevel($course->course_id, $request->level_slug, $language->lang_id);

        if($level->is_available_always === 1){
            $level->is_available = true;
        }
        else{
            $level->is_available = $this->courseService->levelIsAvailable($level->level_id, $auth_user->user_id);
        }

        $sections = $this->courseService->getLevelSections($level->level_id);

        foreach ($sections as $s => $section) {

            $sectionCompletedPercent = 0;

            $lessons = $this->courseService->getLessons($section->section_id, $language->lang_id);

            foreach ($lessons as $l => $lesson) {
                $lesson->is_available = $this->courseService->lessonIsAvailable($lesson, $level->is_available_always);
    
                $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, true);

                $completedTasksCount = 0;
                $completedTasksPercent = 0;

                foreach ($lesson->tasks as $key => $task) {
                    if ($task->task_result && $task->task_result->completed === true) {
                        $completedTasksCount++;
                        $completedTasksPercent += $task->task_result->percentage;
                    }
                }

                $lesson->completed_tasks_count = $completedTasksCount;
                $lesson->completed_tasks_percent = count($lesson->tasks) > 0
                    ? $completedTasksPercent / count($lesson->tasks)
                    : 0;

                $sectionCompletedPercent += $lesson->completed_tasks_percent;

                $lesson_materials = LessonMaterial::where('lesson_id', '=', $lesson->lesson_id)
                ->get();

                $lesson->materials = $lesson_materials;
            }

            $section->lessons = $lessons;
            $section->completed_percent = count($lessons) > 0
                ? $sectionCompletedPercent / count($lessons)
                : 0;

            // 👇 добавляем в общий процент уровня
            $levelCompletedPercent += $section->completed_percent;
        }

        // 👇 итоговый процент по уровню
        $level->completed_percent = count($sections) > 0
        ? $levelCompletedPercent / count($sections)
        : 0;

        $data->course = $course;
        $data->level = $level;
        $data->sections = $sections;
        
        return response()->json($data, 200);
    }

    public function get_lesson(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $data = new \stdClass();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $isOnlyLearner = $auth_user->hasOnlyRoles(['learner']);

        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);

        $data->course = $course;

        $level = $this->courseService->getCourseLevel($course->course_id, $request->level_slug, $language->lang_id);

        if($level->is_available_always === 1){
            $level->is_available = true;
        }
        else{
            $level->is_available = $this->courseService->levelIsAvailable($level->level_id, $auth_user->user_id);
        }

        $data->level = $level;

        $lesson = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
        ->leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
        ->leftJoin('course_levels', 'course_sections.level_id', '=', 'course_levels.level_id')
        ->where('course_levels.level_id', '=', $level->level_id)
        ->where('lessons.lesson_id', '=', $request->lesson_id)
        ->select(
            'lessons.lesson_id',
            'lessons.section_id',
            'course_sections.section_name',
            'lessons.sort_num',
            'lessons.lesson_name',
            'lessons.lesson_description',
            'types_of_lessons.lesson_type_slug'
        )
        ->first();

        $lesson->is_available = $this->courseService->lessonIsAvailable($lesson, $level->is_available_always);
        $lesson->is_only_learner = $isOnlyLearner;

        $lesson->materials = $this->courseService->getLessonMaterials($lesson->lesson_id, $language);

        $data->lesson = $lesson;
        
        return response()->json($data, 200);
    }

    public function get_material_types(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $all_material_types = MaterialType::leftJoin('types_of_materials_lang', 'types_of_materials.material_type_id', '=', 'types_of_materials_lang.material_type_id')
        ->select(
            'types_of_materials.material_type_id',
            'types_of_materials.material_type_slug',
            'types_of_materials.material_type_category',
            'types_of_materials.icon',
            'types_of_materials_lang.material_type_name'
        )
        ->where('types_of_materials.show_status_id', '=', 1)
        ->where('types_of_materials_lang.lang_id', '=', $language->lang_id)
        ->distinct()
        ->orderBy('types_of_materials.material_type_id', 'asc');

        if($request->material_type_category == 'file'){
            $all_material_types->where('types_of_materials.material_type_category', '=', 'file');
        }

        $all_material_types = $all_material_types->get();

        return response()->json($all_material_types, 200);
    }

    public function get_lesson_types(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $lesson_types = LessonType::leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
        ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
        ->select(
            'types_of_lessons.lesson_type_id',
            'types_of_lessons_lang.lesson_type_name'
        )
        ->distinct()
        ->get();

        return response()->json($lesson_types, 200);
    }

    public function add_section(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'section_name' => 'required|string|between:2,100'
        ]);
    
        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);
    
        $level = $this->courseService->getCourseLevel($course->course_id, $request->level_slug, $language->lang_id);
    
        $validator->after(function ($validator) use ($request, $level) {
            $existingSection = CourseSection::where('section_name', '=', $request->section_name)
                ->where('level_id', $level->level_id)
                ->first();
    
            if ($existingSection) {
                $validator->errors()->add('section_name', trans('auth.section_already_exists'));
            }
        });
    
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        // Создание нового раздела
        $new_section = new CourseSection();
        $new_section->section_name = $request->section_name;
        $new_section->level_id = $level->level_id;
        $new_section->save();
    
        return response()->json($new_section, 200);
    }

    public function add_lesson(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'lesson_name' => 'required|string|between:2,100',
            'lesson_description' => 'required|string|between:2,100',
            'lesson_type_id'=> 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        $course = $this->courseService->getCourse($request->course_slug, $language->lang_id);
    
        $level = $this->courseService->getCourseLevel($course->course_id, $request->level_slug, $language->lang_id);

        $section = CourseSection::where('section_id', '=', $request->section_id)
        ->where('level_id', '=', $level->level_id)
        ->first();

        if (!$section) {
            return response()->json(['error' => 'Section not found'], 404);
        }

        $last_lesson = Lesson::where('section_id', '=', $section->section_id)
        ->orderByDesc('sort_num')
        ->first();

        // Создание нового урока
        $new_lesson = new Lesson();
        $new_lesson->lesson_name = $request->lesson_name;
        $new_lesson->lesson_description = $request->lesson_description;
        $new_lesson->section_id = $section->section_id;
        $new_lesson->lesson_type_id = $request->lesson_type_id;
        $new_lesson->sort_num = $last_lesson ? ($last_lesson->sort_num + 1) : 1;
        $new_lesson->save();

        return response()->json($new_lesson, 200);
    }
    
    public function add_material(Request $request)
    {
        $material_type = MaterialType::where('material_type_slug', '=', $request->material_type_slug)
        ->first();

        if (!$material_type) {
            return response()->json(['error' => 'Material type is not found'], 404);
        }

        // Инициализируем массив правил
        $rules = [
            'annotation' => 'required|string|between:2,100',
        ];

        if($material_type->material_type_category == 'file'){
            if($request['upload_lesson_file_create'] == 'true'){
                $rules['lesson_file_name_create'] = 'required';
                
                $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
                ->where('types_of_materials.material_type_slug', '=', $material_type->material_type_slug)
                ->select(
                    'upload_configuration.max_file_size_mb',
                    'upload_configuration.mimes'
                )
                ->first();
                
                $rules['lesson_file_create'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
            }
            else{
                $rules['lesson_file_from_library_create'] = 'required|numeric';
            }
        }
        elseif($material_type->material_type_category == 'block'){
            if($material_type->material_type_slug == 'text'){
                $rules['lesson_text_create'] = 'required|string|min:8';
            }
            elseif($material_type->material_type_slug == 'table'){
                $rules['lesson_table_create'] = 'required|string|min:3';
                $rules['lesson_table_create_options'] = 'required';
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $materials_count = LessonMaterial::where("lesson_id", $request->lesson_id)->count();

        $new_lesson_material = new LessonMaterial();
        $new_lesson_material->lesson_id = $request->lesson_id;
        $new_lesson_material->annotation = $request->annotation;
        $new_lesson_material->sort_num = $materials_count + 1;

        if($material_type->material_type_category == 'file'){
            if($request['upload_lesson_file_create'] == 'true'){

                $file = $request->file('lesson_file_create');

                if($file){
                    $file_name = $file->hashName();

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
                    $new_file->file_name = $request['lesson_file_name_create'];
                    $new_file->target = $file_name;
                    $new_file->size = $file->getSize() / 1048576;
                    $new_file->material_type_id = $material_type->material_type_id;
                    $new_file->save();
                }
            }
            else{
                $findFile = MediaFile::findOrFail($request['lesson_file_from_library_create']);
            }

            $new_lesson_material->file_id = $request['upload_lesson_file_create'] == 'true' ? $new_file->file_id : $findFile->file_id;
        }
        elseif($material_type->material_type_category == 'block'){
            $new_block = new Block();

            if($material_type->material_type_slug == 'text'){
                $new_block->content = $request->lesson_text_create;
            }
            elseif($material_type->material_type_slug == 'table'){
                $new_block->content = $request->lesson_table_create;
                $new_block->options = $request->lesson_table_create_options;
            }

            $new_block->material_type_id = $material_type->material_type_id;
            $new_block->save();

            $new_lesson_material->block_id = $new_block->block_id;
        }

        $new_lesson_material->save();

        return response()->json($new_lesson_material, 200);
    }

    public function edit_material(Request $request)
    {
        $material_type = MaterialType::where('material_type_slug', '=', $request->material_type_slug)
        ->first();

        if (!$material_type) {
            return response()->json(['error' => 'Material type is not found'], 404);
        }

        // Инициализируем массив правил
        $rules = [
            'annotation' => 'required|string|between:2,100',
        ];

        if($material_type->material_type_category == 'file' && $request->select_other_file == 'true'){
            if($request['upload_lesson_file_edit'] == 'true'){
                $rules['lesson_file_name_edit'] = 'required';
                
                $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
                ->where('types_of_materials.material_type_slug', '=', $material_type->material_type_slug)
                ->select(
                    'upload_configuration.max_file_size_mb',
                    'upload_configuration.mimes'
                )
                ->first();
                
                $rules['lesson_file_edit'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
            }
            else{
                $rules['lesson_file_from_library_edit'] = 'required|numeric';
            }
        }
        elseif($material_type->material_type_category == 'block'){
            if($material_type->material_type_slug == 'text'){
                $rules['lesson_text_edit'] = 'required|string|min:8';
            }
            elseif($material_type->material_type_slug == 'table'){
                $rules['lesson_table_edit'] = 'required|string|min:3';
                $rules['lesson_table_edit_options'] = 'required';
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $lesson_material = LessonMaterial::where('lesson_id', '=', $request->lesson_id)
        ->where('lesson_material_id', '=', $request->lesson_material_id)
        ->first();

        if (!$lesson_material) {
            return response()->json(['error' => 'Lesson material is not found'], 404);
        }

        $lesson_material->annotation = $request->annotation;

        if($material_type->material_type_category == 'file' && $request->select_other_file == 'true'){
            if($request['upload_lesson_file_edit'] == 'true'){

                $file = $request->file('lesson_file_edit');

                if($file){
                    $file_name = $file->hashName();

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
                    $new_file->file_name = $request['lesson_file_name_edit'];
                    $new_file->target = $file_name;
                    $new_file->size = $file->getSize() / 1048576;
                    $new_file->material_type_id = $material_type->material_type_id;
                    $new_file->save();
                }
            }
            else{
                $findFile = MediaFile::findOrFail($request['lesson_file_from_library_edit']);
            }

            $lesson_material->file_id = $request['upload_lesson_file_edit'] == 'true' ? $new_file->file_id : $findFile->file_id;
        }
        elseif($material_type->material_type_category == 'block'){
            $new_block = new Block();

            if($material_type->material_type_slug == 'text'){
                $new_block->content = $request->lesson_text_edit;
            }
            elseif($material_type->material_type_slug == 'table'){
                $new_block->content = $request->lesson_table_edit;
                $new_block->options = $request->lesson_table_edit_options;
            }

            $new_block->material_type_id = $material_type->material_type_id;
            $new_block->save();

            $lesson_material->block_id = $new_block->block_id;
        }

        $lesson_material->save();

        return response()->json($lesson_material, 200);
    }

    public function order_materials(Request $request){
        $lesson_materials = json_decode($request->lesson_materials);

        foreach ($lesson_materials as $key => $lesson_material_item) {
            $lesson_material = LessonMaterial::where('lesson_material_id', $lesson_material_item->lesson_material_id)
            ->where('lesson_id', $request->lesson_id)
            ->first();

            $lesson_material->sort_num = $key + 1;
            $lesson_material->save();
        }

        return response()->json('order materials is success', 200);
    }

    public function delete_material(Request $request){
        $lesson_material = LessonMaterial::where('lesson_material_id', $request->lesson_material_id)
        ->where('lesson_id', $request->lesson_id)
        ->first();

        if(isset($lesson_material)){
            $lesson_material->delete();
            return response()->json('delete material is success', 200);
        }
    }

    public function get_courses_structure(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $courses = $this->courseService->getCourses($request);

        foreach ($courses as $c => $course) {
            $levels = $this->courseService->getCourseLevels($course->course_id, $language->lang_id);

            $course->levels = $levels;

            foreach ($levels as $l => $level) {
                $sections = $this->courseService->getLevelSections($level->level_id);

                $level->sections = $sections;

                foreach ($sections as $s => $section) {
                    $lessons = $this->courseService->getLessons($section->section_id, $language->lang_id);

                    $section->lessons = $lessons;
                }
            }
        }

        $attributes = new \stdClass();

        $attributes->courses = $courses;

        return response()->json($attributes, 200);
    }

    public function get_grade(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // $courses = Course::with([
        //     'levels.translation' => function ($q) use ($language) {
        //         $q->where('lang_id', $language->lang_id);
        //     }, 
        //     'levels.sections.lessons.tasks.completedTask' => function ($q) use ($request) {
        //         $q->where('learner_id', $request->user_id)
        //             ->with('taskAnswer');
        //     }
        // ])
        // ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        // ->where('courses.show_status_id', 1)
        // ->where('courses_lang.lang_id', $language->lang_id)
        // ->select('courses.course_id', 'courses.course_name_slug', 'courses_lang.course_name')
        // ->get();

        // foreach ($courses as $courseKey => $course) {
        //     if(count($course[$courseKey]->levels) > 0){
        //         foreach ($course[$courseKey]->levels as $levelKey => $level) {
        //             $levelCompletedPercent = 0;

        //             if($level->is_available_always === 1){
        //                 $level->is_available = true;
        //             }
        //             else{
        //                 $level->is_available = $this->courseService->levelIsAvailable($level->level_id, $request->user_id);
        //             }

        //             if($level->is_available === true){
        //                 if(count($levels[$levelKey]->sections) > 0){
        //                     foreach ($levels[$levelKey]->sections as $sectionKey => $section) {
        //                         $sectionCompletedPercent = 0;

        //                         if(count($sections[$sectionKey]->lessons) > 0){
        //                             foreach ($sections[$sectionKey]->lessons as $lessonKey => $lesson) {
        //                                 $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, false);

        //                                 $completedTasksCount = 0;
        //                                 $completedTasksPercent = 0;

        //                                 foreach ($lesson->tasks as $key => $task) {
        //                                     $task->task_result = $this->taskService->getTaskResult($task->task_id, $request->user_id);

        //                                     if ($task->task_result && $task->task_result->completed === true) {
        //                                         $completedTasksCount++;
        //                                         $completedTasksPercent += $task->task_result->percentage;
        //                                     }
        //                                 }

        //                                 $lesson->completed_tasks_count = $completedTasksCount;
        //                                 $lesson->completed_tasks_percent = count($lesson->tasks) > 0
        //                                     ? $completedTasksPercent / count($lesson->tasks)
        //                                     : 0;

        //                                 $sectionCompletedPercent += $lesson->completed_tasks_percent;
        //                             }
        //                         }
        //                     }
        //                 }
        //             }
        //         }
        //     }
        // }

        $courses = $this->courseService->getCourses($request);

        foreach ($courses as $courseKey => $course) {
            $levels = $this->courseService->getCourseLevels($course->course_id, $language->lang_id);

            foreach ($levels as $levelKey => $level) {
                $levelCompletedPercent = 0;
                $sections = $this->courseService->getLevelSections($level->level_id);

                if($level->is_available_always === 1){
                    $level->is_available = true;
                }
                else{
                    $level->is_available = $this->courseService->levelIsAvailable($level->level_id, $request->user_id);
                }

                if($level->is_available === true){
                    foreach ($sections as $sectionKey => $section) {
                        $sectionCompletedPercent = 0;

                        $lessons = $this->courseService->getLessons($section->section_id, $language->lang_id);

                        foreach ($lessons as $lessonKey => $lesson) {
                            $lesson->tasks = $this->taskService->getLessonTasks($lesson->lesson_id, $language, false);

                            $completedTasksCount = 0;
                            $completedTasksPercent = 0;

                            foreach ($lesson->tasks as $key => $task) {
                                $task->task_result = $this->taskService->getTaskResult($task->task_id, $request->user_id);

                                if ($task->task_result && $task->task_result->completed === true) {
                                    $completedTasksCount++;
                                    $completedTasksPercent += $task->task_result->percentage;
                                }
                            }

                            $lesson->completed_tasks_count = $completedTasksCount;
                            $lesson->completed_tasks_percent = count($lesson->tasks) > 0
                                ? $completedTasksPercent / count($lesson->tasks)
                                : 0;

                            $sectionCompletedPercent += $lesson->completed_tasks_percent;
                        }

                        $section->lessons = $lessons;
                        $section->completed_percent = count($lessons) > 0
                        ? $sectionCompletedPercent / count($lessons)
                        : 0;

                        
                        // 👇 добавляем в общий процент уровня
                        $levelCompletedPercent += $section->completed_percent;
                    }
                }
                                
                // 👇 итоговый процент по уровню
                $level->completed_percent = count($sections) > 0
                ? $levelCompletedPercent / count($sections)
                : 0;

                $level->sections = $sections;
            }

            $levels = $levels
            ->sortByDesc('completed_percent') // сначала сортируем по проценту
            ->sortByDesc('is_available')      // потом по доступности (она будет приоритетнее)
            ->values();

            $course->levels = $levels;
        }

        return response()->json(['courses' => $courses], 200);
    }

    public function send_request(Request $request)
    {
        $rules = [
            'location_id' => 'required|numeric',
            'school_id' => 'required|numeric',
            'lang' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);

        app()->setLocale($request->lang);

        $language = Language::where('lang_tag', '=', $request->lang)->first();

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $school = School::findOrFail($request->school_id);

        return response()->json($school->school_domain, 200);

        // $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        // ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        // ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        // ->select(
        //     'course_levels_lang.level_name',
        //     'courses_lang.course_name'
        // )
        // ->where('course_levels.level_id', '=', $request->level_id)
        // ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        // ->where('courses_lang.lang_id', '=', $language->lang_id)
        // ->first();

        // if(isset($level)){
        //     $mail_body = new \stdClass();
        //     $mail_body->subject = 'Запрос на курс: '.$level->course_name.' ('.$level->level_name.')';
        //     $mail_body->course_name = $level->course_name;
        //     $mail_body->level_name = $level->level_name;
        //     $mail_body->name = $request->first_name;
        //     $mail_body->phone = $request->phone;
        //     $mail_body->lang = $language->lang_name;

        //     $school = School::findOrFail($request->school_id);

        //     try {
        //         Mail::to($school->email)->send(new CourseRequestMail($mail_body));
        //         Log::info("Письмо успешно отправлено на {$school->email}");
        //     } catch (\Exception $e) {
        //         Log::error("Ошибка при отправке письма: " . $e->getMessage());
        //         return response()->json(['error' => $e->getMessage()], 500);
        //     }

        //     return response()->json('success', 200);
        // }
        // else{
        //     return response()->json(['error' => 'Level not found'], 404);
        // }
    }
}