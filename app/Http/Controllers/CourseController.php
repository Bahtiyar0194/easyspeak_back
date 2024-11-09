<?php
namespace App\Http\Controllers;

use App\Models\Course;
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

    public function get_course_attributes(Request $request)
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

        $attributes = new \stdClass();

        $attributes->courses = $courses;

        return response()->json($attributes, 200);
    }
}
