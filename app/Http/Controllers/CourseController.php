<?php
namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
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
                    ->leftJoin('lessons_lang', 'lessons.lesson_id', '=', 'lessons_lang.lesson_id')
                    ->where('lessons.section_id', '=', $section->section_id)
                    ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
                    ->where('lessons_lang.lang_id', '=', $language->lang_id)
                    ->select(
                        'lessons.lesson_id',
                        'lessons.sort_num',
                        'lessons_lang.lesson_name',
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
