<?php
namespace App\Http\Controllers;
use App\Models\Language;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Conference;
use App\Models\Course;
use App\Models\CourseLevel;

use DB;
use Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_schedule_attributes(Request $request)
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
        }

        $all_mentors = DB::table('users')
        ->where('users.school_id', '=', auth()->user()->school_id)
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('users_roles')
                ->whereColumn('users.user_id', 'users_roles.user_id')
                ->whereIn('users_roles.role_type_id', [3, 4]);
        })
        ->select(
            'users.user_id',
            'users.first_name',
            'users.last_name',
            'users.avatar'
        )
        ->distinct()
        ->orderBy('users.last_name', 'asc')
        ->get();

        $mentors = Group::leftJoin('users', 'users.user_id', '=', 'groups.mentor_id')
        ->where('users.school_id', '=', auth()->user()->school_id)
        ->where('users.status_type_id', '!=', 2)
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

        $attributes = new \stdClass();

        $attributes->courses = $courses;
        $attributes->all_mentors = $all_mentors;
        $attributes->group_mentors = $mentors;

        return response()->json($attributes, 200);
    }

    public function get_schedule(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $conferences = Conference::leftJoin('groups', 'conferences.group_id', '=', 'groups.group_id')
        ->leftJoin('group_members', 'groups.group_id', '=', 'group_members.group_id')
        ->leftJoin('users as mentor', 'groups.mentor_id', '=', 'mentor.user_id')
        ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
        ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
        ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
        ->leftJoin('lessons', 'conferences.lesson_id', '=', 'lessons.lesson_id')
        ->leftJoin('types_of_lessons', 'lessons.lesson_type_id', '=', 'types_of_lessons.lesson_type_id')
        ->leftJoin('types_of_lessons_lang', 'types_of_lessons.lesson_type_id', '=', 'types_of_lessons_lang.lesson_type_id')
        ->select(
            'conferences.uuid',
            // 'conferences.operator_id',
            // 'conferences.created_at',
            'conferences.start_time',
            'conferences.end_time',
            'lessons.lesson_name',
            'types_of_lessons_lang.lesson_type_name',
            'mentor.avatar as mentor_avatar',
            'mentor.first_name as mentor_first_name',
            'mentor.last_name as mentor_last_name',
            'courses_lang.course_name',
            'course_levels_lang.level_name',
            'groups.mentor_id',
            'groups.group_name',
            'groups.group_id'
        )
        ->where('courses_lang.lang_id', '=', $language->lang_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->where('types_of_lessons_lang.lang_id', '=', $language->lang_id)
        ->orderBy('conferences.start_time', 'asc')
        ->distinct();

        $course_id = $request->course_id;
        $levels_id = $request->levels;
        $mentors_id = $request->mentors;
        $group_name = $request->group_name;
        $lesson_name = $request->lesson_name;
        $started_at_from = $request->started_at_from;
        $started_at_to = $request->started_at_to;

        if (!empty($course_id)) {
            $conferences->where('courses.course_id', $course_id);
        }

        if (!empty($levels_id)) {
            $conferences->whereIn('course_levels.level_id', $levels_id);
        }

        if(!empty($mentors_id)){
            $conferences->whereIn('groups.mentor_id', $mentors_id);
        }

        if (!empty($group_name)) {
            $conferences->where('groups.group_name', 'LIKE', '%' . $group_name . '%');
        }

        if (!empty($lesson_name)) {
            $conferences->where('lessons.lesson_name', 'LIKE', '%' . $lesson_name . '%');
        }

        if ($started_at_from && $started_at_to) {
            $conferences->whereBetween('groups.started_at', [$started_at_from . ' 00:00:00', $started_at_to . ' 23:59:00']);
        }

        if ($started_at_from) {
            $conferences->where('groups.started_at', '>=', $started_at_from . ' 00:00:00');
        }

        if ($started_at_to) {
            $conferences->where('groups.started_at', '<=', $started_at_to . ' 23:59:00');
        }

        $isOwner = $auth_user->hasRole(['super_admin', 'school_owner', 'school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);
        $isLearner = $auth_user->hasRole(['learner']);

        if ($isOwner || $isMentor || $isLearner) {
            $conferences->where(function ($query) use ($isOwner, $isMentor, $isLearner, $auth_user) {
                if ($isOwner) {
                    $query->orWhere('mentor.school_id', '=', $auth_user->school_id);
                }
                if ($isMentor || $isLearner) {
                    $query->orWhere('groups.mentor_id', '=', $auth_user->user_id)
                    ->orWhere('group_members.member_id', '=', $auth_user->user_id);
                }
            });
        }

        $conferences = $conferences->get()->map(function ($conference) {

            $conference->start_time_formatted = Carbon::parse($conference->start_time)
                ->translatedFormat('d F Y, H:i');
        
            $conference->end_time_formatted = Carbon::parse($conference->end_time)
                ->translatedFormat('d F Y, H:i');
        
            $conference->date = Carbon::parse($conference->start_time)
                ->translatedFormat('Y-m-d');

            $conference->time = Carbon::parse($conference->start_time)
                ->translatedFormat('H:i');
    
            $members = GroupMember::where('group_id', '=', $conference->group_id)
            ->leftJoin('users', 'group_members.member_id', '=', 'users.user_id')
            ->select(
                'users.user_id',
                'users.last_name',
                'users.first_name',
                'users.avatar'
            )
            ->get();

            $conference->members = $members;
        
            return $conference;
        });

        return response()->json($conferences, 200);
    }

    public function update(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $rules = [
            'mentor_id' => 'required|numeric',
            'start_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $conference = Conference::where('uuid', '=', $request->uuid)
        ->first();

        if(isset($conference)){
            $referenceDate = Carbon::parse($conference->start_time)->toDateString();
            $requestDate = Carbon::parse($request->start_date)->toDateString();

            // Создаем допустимый диапазон: от -2 до +2 дней
            $minDate = Carbon::parse($referenceDate)->subDays(2);
            $maxDate = Carbon::parse($referenceDate)->addDays(2);

            if ($requestDate < $minDate->toDateString() || $requestDate > $maxDate->toDateString()) {
                return response()->json(['start_date' => trans('auth.date_should_be_no_earlier_or_no_later_than_two_days')], 422);
            }

            $conference->start_time = $request->start_date.' '.$request->start_time;
            $conference->save();

            $group = Group::findOrFail($conference->group_id);

            $group->mentor_id = $request->mentor_id;
            $group->save();

            return response()->json('Conference saved sucessfully', 200);
        }
        else{
            return response()->json('Conference not found', 404);
        }
    }
}
