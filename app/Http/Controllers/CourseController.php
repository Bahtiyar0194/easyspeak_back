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

use Illuminate\Http\Request;
use Validator;
use DB;
use File;
use Image;
use Storage;

class CourseController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_courses(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $courses = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses.course_name_slug',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();
        
        return response()->json($courses, 200);
    }

    public function get_levels(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $course = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.course_name_slug', '=', $request->course_slug)
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->first();

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $levels = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->where('course_levels.course_id', '=', $course->course_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels_lang.level_name',
            'courses.course_name_slug'
        )
        ->distinct()
        ->orderBy('course_levels.level_id', 'asc')
        ->get();

        if (count($levels) == 0) {
            return response()->json(['error' => 'Levels not found'], 404);
        }

        $data = new \stdClass();
        $data->course = $course;
        $data->levels = $levels;
        
        return response()->json($data, 200);
    }

    public function get_level(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $course = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.course_name_slug', '=', $request->course_slug)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->first();

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->where('course_levels.course_id', '=', $course->course_id)
        ->where('course_levels.level_slug', '=', $request->level_slug)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels_lang.level_name',
            'courses.course_name_slug'
        )
        ->first();

        if (!isset($level)) {
            return response()->json(['error' => 'Level not found'], 404);
        }

        $data = new \stdClass();
        $data->course = $course;
        $data->level = $level;
        
        return response()->json($data, 200);
    }

    public function get_lessons(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $data = new \stdClass();

        $course = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.course_name_slug', '=', $request->course_slug)
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->first();

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $data->course = $course;

        $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->where('course_levels.level_slug', '=', $request->level_slug)
        ->where('course_levels.course_id', '=', $course->course_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels_lang.level_name'
        )
        ->first();

        if (!$level) {
            return response()->json(['error' => 'Level not found'], 404);
        }

        $data->level = $level;

        $sections = CourseSection::where('level_id', '=', $level->level_id)
        ->select(
            'section_id',
            'section_name'
        )
        ->orderBy('section_id', 'asc')
        ->get();

        if (count($sections) == 0) {
            return response()->json(['error' => 'Sections not found'], 404);
        }

        foreach ($sections as $s => $section) {
            $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
            ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
            ->where('lessons.section_id', '=', $section->section_id)
            ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
            ->select(
                'lessons.lesson_id',
                'lessons.sort_num',
                'lessons.lesson_name',
                'types_of_lessons.lesson_type_id',
                'types_of_lessons_lang.lesson_type_name'
            )
            ->distinct()
            ->orderBy('lessons.sort_num', 'asc')
            ->get();

            foreach ($lessons as $l => $lesson) {
                $lesson_tasks = Task::where('lesson_id', '=', $lesson->lesson_id)
                ->where('status_type_id', '=', 1)
                ->get();

                $lesson->tasks = $lesson_tasks;
            }

            $section->lessons = $lessons;
        }

        $data->sections = $sections;
        
        return response()->json($data, 200);
    }

    public function get_lesson(Request $request){
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $data = new \stdClass();

        $course = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.course_name_slug', '=', $request->course_slug)
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->first();

        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }

        $data->course = $course;

        $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->where('course_levels.level_slug', '=', $request->level_slug)
        ->where('course_levels.course_id', '=', $course->course_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->select(
            'course_levels.level_id',
            'course_levels.level_slug',
            'course_levels_lang.level_name'
        )
        ->first();

        if (!$level) {
            return response()->json(['error' => 'Level not found'], 404);
        }

        $data->level = $level;

        $lesson = Lesson::leftJoin('course_sections', 'lessons.section_id', '=', 'course_sections.section_id')
        ->leftJoin('course_levels', 'course_sections.level_id', '=', 'course_levels.level_id')
        ->where('course_levels.level_id', '=', $level->level_id)
        ->where('lessons.lesson_id', '=', $request->lesson_id)
        ->select(
            'lessons.lesson_id',
            'lessons.lesson_name',
            'lessons.lesson_description'
        )
        ->first();

        $lesson_materials = LessonMaterial::leftJoin('files', 'lesson_materials.file_id', '=', 'files.file_id')
        ->leftJoin('types_of_materials as file_types', 'files.material_type_id', '=', 'file_types.material_type_id')
        ->leftJoin('blocks', 'lesson_materials.block_id', '=', 'blocks.block_id')
        ->leftJoin('types_of_materials as block_types', 'blocks.material_type_id', '=', 'block_types.material_type_id')
        ->select(
            'lesson_materials.lesson_material_id',
            'files.target',
            'blocks.content',
            'file_types.material_type_slug as file_material_type_slug',
            'block_types.material_type_slug as block_material_type_slug'
        )
        ->where('lesson_materials.lesson_id', '=', $lesson->lesson_id)
        ->get();

        $lesson->materials = $lesson_materials;

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
        ->orderBy('types_of_materials.material_type_id', 'asc')
        ->get();

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
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'section_name' => 'required|string|between:2,100'
        ]);
    
        $course = Course::where('course_name_slug', '=', $request->course_slug)
            ->select('course_id')
            ->first();
    
        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }
    
        $level = CourseLevel::where('level_slug', '=', $request->level_slug)
            ->where('course_id', $course->course_id)
            ->select('level_id')
            ->first();
    
        if (!$level) {
            return response()->json(['error' => 'Level not found'], 404);
        }
    
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
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'lesson_name' => 'required|string|between:2,100',
            'lesson_description' => 'required|string|between:2,100',
            'lesson_type_id'=> 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
    
        $course = Course::where('course_name_slug', '=', $request->course_slug)
            ->select('course_id')
            ->first();
    
        if (!$course) {
            return response()->json(['error' => 'Course not found'], 404);
        }
    
        $level = CourseLevel::where('level_slug', '=', $request->level_slug)
            ->where('course_id', $course->course_id)
            ->select('level_id')
            ->first();
    
        if (!$level) {
            return response()->json(['error' => 'Level not found'], 404);
        }

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
        $new_lesson->sort_num = $last_lesson ? $last_lesson->sort_num + 1 : 1;
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
        $rules = [];

        if($material_type->material_type_category == 'file'){
            if($request['upload_lesson_file'] == 'true'){
                $rules['lesson_file_name'] = 'required';
                
                $upload_config = UploadConfiguration::leftJoin('types_of_materials', 'upload_configuration.material_type_id', '=', 'types_of_materials.material_type_id')
                ->where('types_of_materials.material_type_slug', '=', $material_type->material_type_slug)
                ->select(
                    'upload_configuration.max_file_size_mb',
                    'upload_configuration.mimes'
                )
                ->first();
                
                $rules['lesson_file'] = 'required|file|mimes:'.$upload_config->mimes.'|max_mb:'.$upload_config->max_file_size_mb;
            }
            else{
                $rules['lesson_file_from_library'] = 'required|numeric';
            }
        }
        elseif($material_type->material_type_category == 'block'){
            if($material_type->material_type_slug == 'text'){
                $rules['lesson_text'] = 'required|string|min:8';
            }
            elseif($material_type->material_type_slug == 'table'){
                $rules['lesson_table'] = 'required|string|min:3';
            }
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $new_lesson_material = new LessonMaterial();
        $new_lesson_material->lesson_id = $request->lesson_id;

        if($material_type->material_type_category == 'file'){
            if($request['upload_lesson_file'] == 'true'){

                $file = $request->file('lesson_file');

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
                    $new_file->file_name = $request['lesson_file_name'];
                    $new_file->target = $file_name;
                    $new_file->size = $file->getSize() / 1048576;
                    $new_file->material_type_id = $material_type->material_type_id;
                    $new_file->save();
                }
            }
            else{
                $findFile = MediaFile::findOrFail($request['lesson_file_from_library']);
            }

            $new_lesson_material->file_id = $request['upload_lesson_file'] == 'true' ? $new_file->file_id : $findFile->file_id;
        }
        elseif($material_type->material_type_category == 'block'){
            $new_block = new Block();

            if($material_type->material_type_slug == 'text'){
                $new_block->content = $request->lesson_text;
            }
            elseif($material_type->material_type_slug == 'table'){
                $new_block->content = $request->lesson_table;
            }

            $new_block->material_type_id = $material_type->material_type_id;
            $new_block->save();

            $new_lesson_material->block_id = $new_block->block_id;
        }

        $new_lesson_material->save();

        return response()->json($new_lesson_material, 200);
    }

    public function get_courses_structure(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $courses = Course::leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('courses.show_status_id', '=', 1)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'courses.course_id',
            'courses_lang.course_name'
        )
        ->distinct()
        ->orderBy('courses.course_id', 'asc')
        ->get();


        foreach ($courses as $c => $course) {
            $levels = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.course_id', '=', $course->course_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->orderBy('course_levels.level_id', 'asc')
            ->get();

            $course->levels = $levels;

            foreach ($levels as $l => $level) {
                $sections = CourseSection::where('level_id', '=', $level->level_id)
                ->select(
                    'section_id',
                    'section_name'
                )
                ->orderBy('section_id', 'asc')
                ->get();

                $level->sections = $sections;

                foreach ($sections as $s => $section) {
                    $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                    ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
                    ->where('lessons.section_id', '=', $section->section_id)
                    ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
                    ->select(
                        'lessons.lesson_id',
                        'lessons.sort_num',
                        'lessons.lesson_name',
                        'types_of_lessons_lang.lesson_type_name'
                    )
                    ->distinct()
                    ->orderBy('lessons.sort_num', 'asc')
                    ->get();

                    $section->lessons = $lessons;
                }
            }
        }

        $attributes = new \stdClass();

        $attributes->courses = $courses;

        return response()->json($attributes, 200);
    }
}