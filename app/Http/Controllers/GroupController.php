<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Conference;
use App\Models\BoughtLesson;
use App\Models\Language;
use App\Models\UserOperation;
use App\Models\UserRequest;

use App\Services\ConferenceService;
use App\Services\ScheduleService;

use Illuminate\Http\Request;
use Validator;
use DB;
use Carbon\Carbon;

class GroupController extends Controller
{
    protected $conferenceService;
    protected $scheduleService;

    public function __construct(Request $request, ConferenceService $conferenceService, ScheduleService $scheduleService)
    {
        $this->conferenceService = $conferenceService;
        $this->scheduleService = $scheduleService;
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_group_attributes(Request $request)
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
            ->where('course_levels.is_available_always', '=', 0)
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

        $operators = Group::leftJoin('users', 'users.user_id', '=', 'groups.operator_id')
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

        // Получаем статусы пользователя
        $statuses = DB::table('groups')
        ->leftJoin('types_of_status', 'groups.status_type_id', '=', 'types_of_status.status_type_id')
        ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
        ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
        ->select(
            'groups.status_type_id',
            'types_of_status_lang.status_type_name'
        )
        ->groupBy('groups.status_type_id', 'types_of_status_lang.status_type_name')
        ->get();

        $attributes = new \stdClass();

        $attributes->courses = $courses;
        $attributes->group_operators = $operators;
        $attributes->group_mentors = $mentors;
        $attributes->all_mentors = $all_mentors;
        $attributes->group_statuses = $statuses;

        return response()->json($attributes, 200);
    }

    public function get_groups(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        $groups = Group::leftJoin('users as mentor', 'groups.mentor_id', '=', 'mentor.user_id')
            ->leftJoin('users as operator', 'groups.operator_id', '=', 'operator.user_id')
            ->leftJoin('schools', 'schools.school_id', '=', 'mentor.school_id')
            ->leftJoin('course_levels', 'groups.level_id', '=', 'course_levels.level_id')
            ->leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->leftJoin('courses', 'course_levels.course_id', '=', 'courses.course_id')
            ->leftJoin('courses_lang', 'courses.course_id', '=', 'courses_lang.course_id')
            ->leftJoin('types_of_status', 'types_of_status.status_type_id', '=', 'groups.status_type_id')
            ->leftJoin('types_of_status_lang', 'types_of_status_lang.status_type_id', '=', 'types_of_status.status_type_id')
            ->select(
                'groups.group_id',
                'groups.group_name',
                'groups.group_description',
                'groups.created_at',
                'groups.started_at',
                'groups.current_price',
                'groups.first_lesson_free',
                'groups.is_legal',
                'course_levels_lang.level_name',
                'courses_lang.course_name',
                'mentor.first_name as mentor_first_name',
                'mentor.last_name as mentor_last_name',
                'mentor.avatar as mentor_avatar',
                'operator.first_name as operator_first_name',
                'operator.last_name as operator_last_name',
                'operator.avatar as operator_avatar',
                DB::raw('(SELECT COUNT(*) FROM group_members WHERE group_members.group_id = groups.group_id) as members_count'),
                'types_of_status.color as status_color',
                'types_of_status_lang.status_type_name'
            )
            ->where('mentor.school_id', '=', auth()->user()->school_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->where('courses_lang.lang_id', '=', $language->lang_id)
            ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
            ->orderBy($sortKey, $sortDirection);

            // Получаем текущего аутентифицированного пользователя
            $auth_user = auth()->user();
            $isOwner = $auth_user->hasRole(['school_owner']);
            $isAdmin = $auth_user->hasRole(['school_admin']);
            $isMentor = $auth_user->hasRole(['mentor']);

            if(!$isOwner && !$isAdmin && $isMentor){
                $groups->where('groups.mentor_id', '=', auth()->user()->user_id);
            }


        $group_name = $request->group_name;
        $course_id = $request->course_id;
        $levels_id = $request->levels;
        $operators_id = $request->operators;
        $mentors_id = $request->mentors;
        $statuses_id = $request->statuses;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;

        if (!empty($group_name)) {
            $groups->where('groups.group_name', 'LIKE', '%' . $group_name . '%');
        }

        if (!empty($course_id)) {
            $groups->where('courses.course_id', $course_id);
        }

        if (!empty($levels_id)) {
            $groups->whereIn('course_levels.level_id', $levels_id);
        }

        if(!empty($operators_id)){
            $groups->whereIn('groups.operator_id', $operators_id);
        }

        if(!empty($mentors_id)){
            $groups->whereIn('groups.mentor_id', $mentors_id);
        }

        // Фильтрация по статусу
        if (!empty($statuses_id)) {
            $groups->whereIn('groups.status_type_id', $statuses_id);
        }

        if ($created_at_from && $created_at_to) {
            $groups->whereBetween('groups.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:00']);
        }

        if ($created_at_from) {
            $groups->where('groups.created_at', '>=', $created_at_from . ' 00:00:00');
        }

        if ($created_at_to) {
            $groups->where('groups.created_at', '<=', $created_at_to . ' 23:59:00');
        }

        return response()->json($groups->paginate($per_page)->onEachSide(1), 200);
    }

    public function get_group(Request $request)
    {
        $auth_user = auth()->user();

        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $group = Group::select(
                'groups.group_id',
                'groups.group_name',
                'groups.group_description',
                'groups.level_id',
                'groups.created_at',
                'groups.started_at',
                'groups.current_price',
                'groups.first_lesson_free',
                'groups.is_legal',
                'groups.mentor_id',
                'groups.operator_id'
            )
            ->where('groups.group_id', '=', $request->group_id)
            ->first();

        $members = GroupMember::where('group_id', '=', $request->group_id)
            ->where('group_members.status_type_id', '=', 1)
            ->leftJoin('users as member', 'group_members.member_id', '=', 'member.user_id')
            ->select(
                'member.user_id',
                'member.last_name',
                'member.first_name',
                'member.avatar'
            )
            ->get();

        $mentor = User::find($group->mentor_id);
        $operator = User::find($group->operator_id);

        $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
        ->where('course_levels.level_id', '=', $group->level_id)
        ->where('course_levels_lang.lang_id', '=', $language->lang_id)
        ->select(
            'course_levels.course_id',
            'course_levels.level_id',
            'course_levels_lang.level_name'
        )
        ->distinct()
        ->first();

        $group->start_date = date('Y-m-d', strtotime($group->started_at));
        $group->start_time = date('H:i', strtotime($group->started_at));

        $group->group_members = $members;
        $group->level = $level;
        $group->mentor = $mentor->only(['last_name', 'first_name', 'avatar']);
        $group->operator = $operator->only(['last_name', 'first_name', 'avatar']);

        $group->schedule = $this->scheduleService->getSchedule($request, $auth_user->user_id, $language->lang_id, false, $group->group_id);

        $days = [];

        foreach ($group->schedule as $conference) {
            // Получаем номер дня недели
            // Carbon::parse(...)->dayOfWeekIso возвращает:
            // 1 = Пн, 2 = Вт, ..., 7 = Вс
            $dayNum = Carbon::parse($conference->start_time)->dayOfWeekIso;

            if($conference->moved === 0){
                // Добавляем, если нет
                if (!in_array($dayNum, $days)) {
                    $days[] = $dayNum;
                }
            }
        }

        // Сортируем
        sort($days);

        $group->days = $days;

        return response()->json($group, 200);
    }

    public function create(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'group_name' => 'required|string|between:3,300',
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'mentor_id' => 'required|numeric',
                'is_legal' => 'required|boolean',
                'lesson_price' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        } 
        elseif ($request->step == 2) {
            $rules = [
                'start_date' => 'required|date|after_or_equal:today',
                'start_time' => 'required|date_format:H:i',
                'selected_days' => 'required|string|min:3',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'members_count' => 'required|numeric|min:1',
                'members' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $mentor = User::find($request->mentor_id);
            $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.level_id', '=', $request->level_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->first();

            if (!$mentor || !$level) {
                return response()->json(['error' => 'Mentor or level not found.'], 404);
            }

            $group_members = json_decode($request->members);
            $already_members = [];

            foreach ($group_members as $member) {
                $searchLevelGroup = GroupMember::leftJoin('groups', 'group_members.group_id', '=', 'groups.group_id')
                ->where('group_members.status_type_id', '=', 1)
                ->where('group_members.member_id', '=', $member->user_id)
                ->where('groups.level_id', '=', $level->level_id)
                ->select(
                    'groups.group_name'
                )
                ->first();

                if(isset($searchLevelGroup)){
                    array_push($already_members, [
                            'user_id' => $member->user_id,
                            'first_name' => $member->first_name,
                            'last_name' => $member->last_name,
                            'group_name' => $searchLevelGroup->group_name
                    ]);
                }
            }

            if(count($already_members) > 0){
                return response()->json(['error' => 'already_members', 'members' => $already_members], 422);
            }

            return response()->json([
                'step' => 3,
                'data' => [
                    'group_name' => $request->group_name,
                    'group_description' => $request->group_description,
                    'level_name' => $level->level_name,
                    'mentor' => $mentor ? $mentor->only(['last_name', 'first_name', 'avatar']) : null,
                    'members' => $request->members
                ]
            ]);
        } elseif ($request->step == 4) {

            $mentor = User::find($request->mentor_id);

            $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.level_id', '=', $request->level_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->first();

            if (!$mentor || !$level) {
                return response()->json(['error' => 'Mentor or level not found.'], 404);
            }

            $group_members = json_decode($request->members);
            $member_names = [];

            if (count($group_members) > 0) {

                $new_group = new Group();
                $new_group->operator_id = auth()->user()->user_id;
                $new_group->mentor_id = $request->mentor_id;
                $new_group->is_legal = $request->is_legal;
                $new_group->group_name = $request->group_name;
                $new_group->group_description = $request->group_description;
                $new_group->level_id = $level->level_id;
                $new_group->started_at = $request->start_date.' '.$request->start_time.':00';
                $new_group->current_price = $request->lesson_price;
                $new_group->first_lesson_free = isset($request->first_lesson_free) ? 1 : 0;
                $new_group->save();

                $this->conferenceService->createConferences($new_group->group_id, $new_group->level_id, $new_group->started_at, $request->selected_days);

                if(isset($request->first_lesson_free)){
                    //Ближайшая конференция
                    $firstLesson = Conference::select('lesson_id')
                    ->where('group_id', $new_group->group_id)
                    ->where('start_time', '>=', date(now()))
                    ->orderBy('start_time', 'asc')
                    ->first();
                }

                foreach ($group_members as $member) {
                    $new_member = new GroupMember();
                    $new_member->group_id = $new_group->group_id;
                    $new_member->member_id = $member->user_id;
                    $new_member->save();

                    if(isset($firstLesson)){
                        $exists = BoughtLesson::where('lesson_id', $firstLesson->lesson_id)
                        ->where('learner_id', $member->user_id)
                        ->exists();

                        if (!$exists) {
                            $new_bought_lesson = new BoughtLesson();
                            $new_bought_lesson->learner_id = $member->user_id;
                            $new_bought_lesson->lesson_id = $firstLesson->lesson_id;
                            $new_bought_lesson->iniciator_id = auth()->user()->user_id;
                            $new_bought_lesson->is_free = 1;
                            $new_bought_lesson->save();
                        }
                    }
                     
                    // Сохранение имен участников
                    $member_names[] = $member->last_name . ' ' . $member->first_name;
                }

                $description = "<p><span>Название группы:</span> <b>{$new_group->group_name}</b></p>
                <p><span>Куратор:</span> <b>{$mentor->last_name} {$mentor->first_name}</b></p>
                <p><span>Категория группы:</span> <b>{$level->level_name}</b></p>
                <p><span>Участники:</span> <b>" . implode(", ", $member_names) . "</b></p>";

                $user_operation = new UserOperation();
                $user_operation->operator_id = auth()->user()->user_id;
                $user_operation->operation_type_id = 3;
                $user_operation->description = $description;
                $user_operation->save();

                return response()->json('success', 200);
            }
        }
    }

    public function update(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();
        $rules = [];

        if ($request->step == 1) {
            $rules = [
                'group_name' => 'required|string|between:3,300',
                'course_id' => 'required|numeric',
                'level_id' => 'required|numeric',
                'mentor_id' => 'required|numeric',
                'is_legal' => 'required|boolean',
                'lesson_price' => 'required|numeric',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 1
            ], 200);
        } 
        elseif ($request->step == 2) {
            $rules = [
                'start_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'selected_days' => 'required|string|min:3',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            return response()->json([
                'step' => 2
            ], 200);
        }
        elseif ($request->step == 3) {
            $rules = [
                'members_count' => 'required|numeric|min:1',
                'members' => 'required',
                'step' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
            }

            $mentor = User::find($request->mentor_id);
            $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.level_id', '=', $request->level_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->first();

            if (!$mentor || !$level) {
                return response()->json(['error' => 'Mentor or level not found.'], 404);
            }

            $group_members = json_decode($request->members);
            $already_members = [];

            foreach ($group_members as $member) {
                $searchLevelGroup = GroupMember::leftJoin('groups', 'group_members.group_id', '=', 'groups.group_id')
                ->where('group_members.status_type_id', '=', 1)
                ->where('group_members.member_id', '=', $member->user_id)
                ->where('groups.level_id', '=', $level->level_id)
                ->where('groups.group_id', '!=', $request->group_id)
                ->select(
                    'groups.group_name'
                )
                ->first();

                if(isset($searchLevelGroup)){
                    array_push($already_members, [
                            'user_id' => $member->user_id,
                            'first_name' => $member->first_name,
                            'last_name' => $member->last_name,
                            'group_name' => $searchLevelGroup->group_name
                    ]);
                }
            }

            if(count($already_members) > 0){
                return response()->json(['error' => 'already_members', 'members' => $already_members], 422);
            }

            return response()->json([
                'step' => 3,
                'data' => [
                    'group_name' => $request->group_name,
                    'group_description' => $request->group_description,
                    'level_name' => $level->level_name,
                    'mentor' => $mentor ? $mentor->only(['last_name', 'first_name', 'avatar']) : null,
                    'members' => $request->members
                ]
            ]);
        } elseif ($request->step == 4) {

            //$isOwner = auth()->user()->hasRole(['super_admin', 'school_owner']);

            $mentor = User::find($request->mentor_id);
            $level = CourseLevel::leftJoin('course_levels_lang', 'course_levels.level_id', '=', 'course_levels_lang.level_id')
            ->where('course_levels.level_id', '=', $request->level_id)
            ->where('course_levels_lang.lang_id', '=', $language->lang_id)
            ->select(
                'course_levels.level_id',
                'course_levels_lang.level_name'
            )
            ->distinct()
            ->first();

            if (!$mentor || !$level) {
                return response()->json(['error' => 'Mentor or level not found.'], 404);
            }

            $edit_group = Group::find($request->group_id);
            $edit_group->operator_id = auth()->user()->user_id;
            $edit_group->mentor_id = $request->mentor_id;
            $edit_group->is_legal = $request->is_legal;
            $edit_group->group_name = $request->group_name;
            $edit_group->group_description = $request->group_description;
            $edit_group->level_id = $request->level_id;            
            $edit_group->started_at = $request->start_date.' '.$request->start_time.':00';
            $edit_group->current_price = $request->lesson_price;
            $edit_group->first_lesson_free = isset($request->first_lesson_free) ? 1 : 0;
            $edit_group->status_type_id = 1; //$isOwner ? 1 : 16;
            $edit_group->save();

            $this->conferenceService->editConferences($edit_group->group_id, $edit_group->level_id, $edit_group->started_at, $request->selected_days);

            if(isset($request->first_lesson_free)){
                //Ближайшая конференция
                $firstLesson = Conference::select('lesson_id')
                ->where('group_id', $edit_group->group_id)
                ->where('start_time', '>=', date(now()))
                ->orderBy('start_time', 'asc')
                ->first();
            }

            // Извлекаем user_id из переданных данных
            $newMemberIds = collect(json_decode($request->members))->pluck('user_id')->toArray();

            // Получаем текущих участников группы
            $currentMembers = GroupMember::where('group_id', $request->group_id)
            ->where('status_type_id', '=', 1)
            ->pluck('member_id')
            ->toArray();

            // Определяем бывших участников (были в группе, но их нет в новом массиве)
            $formerMembers = array_diff($currentMembers, $newMemberIds);

            // Определяем новых участников (есть в новом массиве, но их нет в группе)
            $newMembersToAdd = array_diff($newMemberIds, $currentMembers);

            //Бывших участников пока не удаляем, назначим статус на удаление
            GroupMember::whereIn('member_id', $formerMembers)
            ->where('group_id', $request->group_id)
            ->where('status_type_id', '=', 1)
            ->update(['status_type_id' => 15]);
        
            // Добавляем новых участников
            foreach ($newMembersToAdd as $memberId) {
                $new_member = new GroupMember();
                $new_member->group_id = $edit_group->group_id;
                $new_member->member_id = $memberId;
                $new_member->status_type_id = 1; //$isOwner ? 1 : 12;
                $new_member->save();

                if(isset($firstLesson)){
                    $exists = BoughtLesson::where('lesson_id', $firstLesson->lesson_id)
                    ->where('learner_id', $memberId)
                    ->exists();

                    if (!$exists) {
                        $new_bought_lesson = new BoughtLesson();
                        $new_bought_lesson->learner_id = $memberId;
                        $new_bought_lesson->lesson_id = $firstLesson->lesson_id;
                        $new_bought_lesson->iniciator_id = auth()->user()->user_id;
                        $new_bought_lesson->is_free = 1;
                        $new_bought_lesson->save();
                    }
                }
            }

            // Обрабатываем неизменных участников (если требуется)
            $unchangedMembers = array_intersect($currentMembers, $newMemberIds);
            // foreach ($unchangedMembers as $memberId) {
            //     GroupMember::where('member_id', $memberId)
            //         ->where('group_id', $request->group_id)
            //         ->update(['status_type_id' => 1]);
            // }

            // Извлечение новых участников
            $new_members_name = User::whereIn('users.user_id', $newMembersToAdd)
                ->get()
                ->map(function ($user) {
                    return "{$user->last_name} {$user->first_name}";
                })
                ->toArray();

            // Извлечение бывших участников
            $former_members_name = User::whereIn('users.user_id', $formerMembers)
                ->get()
                ->map(function ($user) {
                    return "{$user->last_name} {$user->first_name}";
                })
                ->toArray();

            // Извлечение неизменных участников
            $unchanged_members_name = User::whereIn('users.user_id', $unchangedMembers)
                ->get()
                ->map(function ($user) {
                    return "{$user->last_name} {$user->first_name}";
                })
                ->toArray();

            // Формирование описания
            $description = "<p><span>Название группы:</span> <b>" . e($request->group_name) . "</b></p>
            <p><span>Куратор:</span> <b>" . $mentor->last_name . " " . $mentor->first_name . "</b></p>
            <p><span>Категория группы:</span> <b>{$level->level_name}</b></p>
            <p><span>Новые участники:</span> <b>" . implode(', ', $new_members_name) . "</b></p>
            <p><span>Бывшие участники:</span> <b>" . implode(', ', $former_members_name) . "</b></p>
            <p><span>Неизменные участники:</span> <b>" . implode(', ', $unchanged_members_name) . "</b></p>";

            $user_operation = new UserOperation();
            $user_operation->operator_id = auth()->user()->user_id;
            $user_operation->operation_type_id = 4;
            $user_operation->description = $description;
            $user_operation->save();

            // $owner =

            // $user_request = new UserRequest();
            // $user_request->operator_id = auth()->user()->user_id;
            // $user_request->recipient_id = 
            // $user_request->request_type_id = 1;
            // $user_request->description = $description;
            // $user_request->save();

            return response()->json('success', 200);
        }
    }

    public function set_free_lessons(Request $request)
    {
        $members = GroupMember::get();

        foreach ($members as $key => $member) {
            $conferences = Conference::where('group_id', '=', $member->group_id)
            ->orderBy('start_time', 'asc')
            ->limit(12)
            ->get();

            foreach ($conferences as $c => $conference) {
                $exists = BoughtLesson::where('lesson_id', $conference->lesson_id)
                ->where('learner_id', $member->member_id)
                ->exists();

                if (!$exists) {
                    BoughtLesson::create([
                        'learner_id' => $member->member_id,
                        'lesson_id'  => $conference->lesson_id,
                        'iniciator_id' => $member->member_id,
                        'is_free'    => 1,
                    ]);
                }
            }
        }

        echo 123;
    }

    public function save_payments(Request $request){
        $validator = Validator::make($request->all(), [
            'payments_count' => 'required|numeric|min:1',
            'payments' => 'required'
        ]);

        if ($validator->fails()) {
                return response()->json($validator->errors(), 422);
        }

        $payments = json_decode($request->payments);

        foreach ($payments as $key => $payment) {

            $exists = BoughtLesson::where('lesson_id', $payment->lesson_id)
            ->where('learner_id', $payment->user_id)
            ->exists();

            if($payment->checked === true){
                if(!$exists){
                    BoughtLesson::create([
                        'learner_id' => $payment->user_id,
                        'lesson_id'  => $payment->lesson_id,
                        'iniciator_id' => auth()->user()->user_id,
                        'is_free'    => 0,
                    ]);
                }
            }
            else{
                if($exists){
                    BoughtLesson::where('lesson_id', $payment->lesson_id)
                    ->where('learner_id', $payment->user_id)
                    ->delete();
                }
            }
        }

        return response()->json('success', 200);
    }
}