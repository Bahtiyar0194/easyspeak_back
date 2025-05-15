<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Language;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;

use Illuminate\Http\Request;
use Validator;
use Str;

class ConferenceController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_attributes(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $groups = Group::leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->select(
            'groups.group_id',
            'groups.level_id',
            'groups.group_name',
            'course_levels_lang.level_name',
            'courses_lang.course_name',
            'courses.course_id'
        )
        ->where('groups.mentor_id', '=', $auth_user->user_id)
        ->where('groups.status_type_id', '=', 1)
        ->get();

        foreach ($groups as $g => $group) {

            $members = GroupMember::where('group_id', '=', $group->group_id)
            ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
            ->select(
                'users.user_id',
                'users.last_name',
                'users.first_name',
                'users.avatar'
            )
            ->get();

            $group->members = $members;

            $sections = CourseSection::where('course_sections.level_id', '=', $group->level_id)
            ->select(
                'course_sections.section_id',
                'course_sections.section_name'
            )
            ->distinct()
            ->orderBy('course_sections.section_id', 'asc')
            ->get();

            $group->sections = $sections;

            foreach ($sections as $s => $section) {
                $lessons = Lesson::leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
                ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
                ->where('lessons.section_id', '=', $section->section_id)
                ->where('lessons.lesson_type_id', '=', 1)
                ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
                ->select(
                    'lessons.lesson_id',
                    'lessons.lesson_name',
                    'lessons.sort_num',
                    'types_of_lessons_lang.lesson_type_name'
                )
                ->distinct()
                ->orderBy('lessons.sort_num', 'asc')
                ->get();

                $section->lessons = $lessons;
            }
        }

        $attributes = new \stdClass();

        $attributes->groups = $groups;
        return response()->json($attributes, 200);
    }

    public function get_current_conferences(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();
        $current_conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
        ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
        ->select(
            'conferences.uuid',
            'conferences.created_at',
            'conferences.start_time',
            'conferences.end_time',
            'lessons.lesson_name',
            'courses_lang.course_name',
            'course_levels_lang.level_name',
            'groups.group_name',
        )
        ->where('groups.mentor_id', '=', $auth_user->user_id)
        ->where('conferences.start_time', '<=', now())
        ->where('conferences.end_time', '>=', now())
        ->get();
    
        $isOwner = $auth_user->hasRole(['school_owner']);
        $isAdmin = $auth_user->hasRole(['school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);

        // Если пользователь - куратор, то показываем только свои группы
        if ($isMentor && !$isAdmin && !$isOwner) {
            $groups->where('groups.mentor_id', '=', $auth_user->user_id);
        }
        else{
            $groups->where('users.school_id', '=', $auth_user->school_id);
        }

        $current_conferences = $current_conferences->get()

    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer',
            'lesson_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $conference = new Conference();
        $conference->uuid = str_replace('-', '', (string) Str::uuid());
        $conference->group_id = $request->group_id;
        $conference->lesson_id = $request->lesson_id;
        $conference->start_time = date('Y-m-d H:i:s');
        $conference->end_time = date('Y-m-d H:i:s', strtotime('+2 hour'));
        $conference->save();

        return response()->json($conference, 200);
    }
}
