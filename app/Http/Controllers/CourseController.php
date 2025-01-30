<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonType;
use App\Models\Task;
use App\Models\Language;

use Illuminate\Http\Request;
use Validator;
use DB;

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

        $data->lesson = $lesson;
        
        return response()->json($data, 200);
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