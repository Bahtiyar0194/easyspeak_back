<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use App\Models\UserOperation;
use App\Models\Language;
use App\Models\School;
use App\Models\RoleType;

use Mail;
use App\Mail\WelcomeMail;

use Illuminate\Http\Request;
use Str;
use Validator;
use DB;

class UserController extends Controller
{

    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function get_roles(Request $request)
    {
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        $roles = DB::table('types_of_user_roles')
            ->leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
            ->where('types_of_user_roles_lang.lang_id', '=', $language->lang_id)
            ->where('types_of_user_roles.role_type_id', '!=', 1)
            ->select(
                'types_of_user_roles.role_type_id',
                'types_of_user_roles_lang.user_role_type_name'
            )
            ->get();

        return response()->json($roles, 200);
    }

    public function get_user_attributes(Request $request)
    {
        // Получаем язык по заголовку Accept-Language
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        // Проверяем роли пользователя
        $isOwner = $auth_user->hasRole(['school_owner']);
        $isAdmin = $auth_user->hasRole(['school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);

        // Получаем статусы пользователя
        $statuses = DB::table('users')
            ->leftJoin('types_of_status', 'users.status_type_id', '=', 'types_of_status.status_type_id')
            ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
            ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
            ->select(
                'users.status_type_id',
                'types_of_status_lang.status_type_name'
            )
            ->groupBy('users.status_type_id', 'types_of_status_lang.status_type_name')
            ->get();

        // Формируем запрос для получения ролей
        $roles = DB::table('types_of_user_roles')
            ->leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
            ->where('types_of_user_roles_lang.lang_id', '=', $language->lang_id)
            ->select(
                'types_of_user_roles.role_type_id',
                'types_of_user_roles_lang.user_role_type_name'
            );

        // Применяем фильтры ролей в зависимости от роли пользователя
        if ($isOwner) {
            $roles->whereIn('types_of_user_roles.role_type_slug', ['school_admin', 'mentor', 'learner']);
        } 
        elseif ($isAdmin) {
            $roles->whereIn('types_of_user_roles.role_type_slug', ['mentor', 'learner']);
        }
        elseif ($isMentor) {
            $roles->whereIn('types_of_user_roles.role_type_slug', ['learner']);
        }

        // Получаем список ролей
        $rolesList = $roles->get();

        // Создаем объект для возвращаемых данных
        $attributes = new \stdClass();
        $attributes->user_statuses = $statuses;
        $attributes->user_roles = $rolesList;

        // Возвращаем данные в JSON-формате
        return response()->json($attributes, 200);
    }

    public function get_users(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        // Проверяем роли авторизованного пользователя
        $isOwner = $auth_user->hasRole(['school_owner']);
        $isAdmin = $auth_user->hasRole(['school_admin']);
        $isMentor = $auth_user->hasRole(['mentor']);

        // Получаем параметры лимита на страницу
        $per_page = $request->per_page ? $request->per_page : 10;
        // Получаем параметры сортировки
        $sortKey = $request->input('sort_key', 'created_at');  // Поле для сортировки по умолчанию
        $sortDirection = $request->input('sort_direction', 'asc');  // Направление по умолчанию

        // Формируем запрос
        $query = User::where('users.school_id', $auth_user->school_id);

        // Если пользователь владелец, исключаем пользователей с ролью владельца
        if ($isOwner) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->where('role_type_slug', 'school_owner');
            });
        }
        // Если пользователь админ, исключаем пользователей с ролями владельца и администратора
        elseif ($isAdmin) {
            $query->whereDoesntHave('roles', function ($q) {
                $q->whereIn('role_type_slug', ['school_owner', 'school_admin']);
            });
        }

        // Возвращаем ID пользователей
        $userIds = $query->distinct()->pluck('user_id')->toArray();

        // Основной запрос для получения пользователей
        $users = User::leftJoin('users_roles', 'users.user_id', '=', 'users_roles.user_id')
            ->leftJoin('types_of_user_roles', 'users_roles.role_type_id', '=', 'types_of_user_roles.role_type_id')
            ->leftJoin('types_of_status', 'users.status_type_id', '=', 'types_of_status.status_type_id')
            ->leftJoin('types_of_status_lang', 'types_of_status.status_type_id', '=', 'types_of_status_lang.status_type_id')
            ->select(
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.avatar',
                'users.created_at',
                'types_of_status_lang.status_type_name',
                'types_of_status.color as status_color'
            )
            ->whereIn('users.user_id', $userIds)
            ->where('types_of_status_lang.lang_id', '=', $language->lang_id)
            ->groupBy(
                'users.user_id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.avatar',
                'users.created_at',
                'types_of_status_lang.status_type_name',
                'types_of_status.color'
            )
            ->orderBy($sortKey, $sortDirection);

        // Если это не владелец, не админ но ментор то присоединяем таблицы для фильтрации по группам ментора. (Показываем пользователей которые состоят в группе ментора)
        if(!$isOwner && !$isAdmin && $isMentor){
            $users->leftJoin('group_members', 'users.user_id', '=', 'group_members.member_id')
            ->leftJoin('groups', 'groups.group_id', '=', 'group_members.group_id')
            ->where('groups.mentor_id', '=', $auth_user->user_id);
        }

        // Применяем фильтрацию по параметрам из запроса
        $user_fio = $request->user;
        $email = $request->email;
        $phone = $request->phone;
        $statuses_id = $request->statuses;
        $roles_id = $request->roles;
        $created_at_from = $request->created_at_from;
        $created_at_to = $request->created_at_to;

        // Фильтрация по ФИО пользователя
        if (!empty($user_fio)) {
            $users->whereRaw("CONCAT(users.last_name, ' ', users.first_name) LIKE ?", ['%' . $user_fio . '%']);
        }

        // Фильтрация по email
        if (!empty($email)) {
            $users->where('users.email', 'LIKE', '%' . $email . '%');
        }

        // Фильтрация по телефону
        if (!empty($phone)) {
            $users->where('users.phone', 'LIKE', '%' . $phone . '%');
        }

        // Фильтрация по статусу
        if (!empty($statuses_id)) {
            $users->whereIn('users.status_type_id', $statuses_id);
        }

        // Фильтрация по роли
        if (!empty($roles_id)) {
            $users->whereIn('users_roles.role_type_id', $roles_id);
        }

        // Фильтрация по дате создания
        if ($created_at_from && $created_at_to) {
            $users->whereBetween('users.created_at', [$created_at_from . ' 00:00:00', $created_at_to . ' 23:59:59']);
        } elseif ($created_at_from) {
            $users->where('users.created_at', '>=', $created_at_from . ' 00:00:00');
        } elseif ($created_at_to) {
            $users->where('users.created_at', '<=', $created_at_to . ' 23:59:59');
        }

        // Возвращаем пагинированный результат
        return response()->json($users->paginate($per_page)->onEachSide(1), 200);
    }


    public function get_user(Request $request)
    {
        // Получаем пользователя по school_id и user_id
        $user = User::where('school_id', '=', auth()->user()->school_id)
            ->where('user_id', '=', $request->user_id)
            ->first();

        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        // Проверяем роли авторизованного пользователя
        $isOwner = $auth_user->hasRole(['super_admin', 'school_owner']);
        $isAdmin = $auth_user->hasRole(['school_admin']);

        // Формируем запрос на получение ролей
        $rolesQuery = DB::table('types_of_user_roles')
            ->leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
            ->where('types_of_user_roles_lang.lang_id', '=', $language->lang_id)
            ->select(
                'types_of_user_roles.role_type_id',
                'types_of_user_roles.role_type_slug',
                'types_of_user_roles_lang.user_role_type_name'
            );

        // Применяем фильтры ролей в зависимости от роли авторизованного пользователя
        if ($isOwner) {
            $rolesQuery->whereNotIn('types_of_user_roles.role_type_slug', ['super_admin']);
        } elseif ($isAdmin) {
            $rolesQuery->whereIn('types_of_user_roles.role_type_slug', ['mentor', 'learner']);
        }

        // Выполняем запрос на получение списка ролей
        $rolesList = $rolesQuery->get();

        $available_roles = [];

        // Присваиваем флаг "selected" в зависимости от наличия роли у пользователя
        foreach ($rolesList as $role) {
            $find_user_role = UserRole::where('role_type_id', '=', $role->role_type_id)
                ->where('user_id', '=', $user->user_id)
                ->first();

            if (isset($find_user_role)) {
                $role->selected = true;
                array_push($available_roles, $role);
            } else {
                $role->selected = false;
            }
        }

        // Присваиваем имя текущей роли пользователя
        foreach ($rolesList as $role) {
            if ($role->role_type_id == $user->current_role_id) {
                $user->current_role_name = $role->user_role_type_name;
                break;
            }
        }

        // Добавляем список ролей в ответ
        $user->roles = $rolesList;
        $user->available_roles = $available_roles;

        return response()->json($user, 200);
    }

    public function invite_user(Request $request)
    {
        // Получаем язык из заголовка
        $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // Получаем текущего аутентифицированного пользователя
        $auth_user = auth()->user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100',
            'phone' => 'required|regex:/^((?!_).)*$/s',
            'roles_count' => 'required|numeric|min:1',
            'roles' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $find_email = User::where('school_id', '=', $auth_user->school_id)
            ->where('email', '=', $request->email)
            ->first();

        if (isset($find_email)) {
            $email_error = ['email' => [trans('auth.user_already_exists')]];
            return response()->json($email_error, 422);
        }

        $email_hash = Str::random(32);

        $new_user = new User();
        $new_user->first_name = $request->first_name;
        $new_user->last_name = $request->last_name;
        $new_user->email = $request->email;
        $new_user->phone = $request->phone;
        $new_user->school_id = $auth_user->school_id;
        $new_user->current_role_id = $request->roles[0];
        $new_user->status_type_id = 4;
        $new_user->email_hash = $email_hash;
        $new_user->save();

        $role_names = RoleType::leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
            ->where('types_of_user_roles_lang.lang_id', '=', $language->lang_id)
            ->whereIn('types_of_user_roles.role_type_id', $request->roles)
            ->select('types_of_user_roles_lang.user_role_type_name')
            ->get()
            ->pluck('user_role_type_name')
            ->toArray();

        foreach ($request->roles as $value) {
            $user_role = new UserRole();
            $user_role->user_id = $new_user->user_id;
            $user_role->role_type_id = $value;
            $user_role->save();
        }

        $description = "Имя: {$new_user->last_name} {$new_user->first_name};\n E-Mail: {$request->email};\n Телефон: {$request->phone};\n Роли: " . implode(",", $role_names) . ".";

        $user_operation = new UserOperation();
        $user_operation->operator_id = $auth_user->user_id;
        $user_operation->operation_type_id = 1;
        $user_operation->description = $description;
        $user_operation->save();

        $getSchool = School::find(auth()->user()->school_id);

        $mail_body = new \stdClass();
        $mail_body->subject = $getSchool->school_name;
        $mail_body->first_name = $request->first_name;
        $mail_body->activation_url = $request->header('Origin') . '/activation/' . $email_hash;
        $mail_body->school_name = $getSchool->school_name;

        Mail::to($new_user->email)->send(new WelcomeMail($mail_body));
        return response()->json($new_user, 200);
    }

    public function update_user(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100',
            'phone' => 'required|regex:/^((?!_).)*$/s',
            'roles_count' => 'required|numeric|min:1',
            'roles' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('school_id', '=', auth()->user()->school_id)
            ->where('user_id', '=', $request->user_id)
            ->first();

        if (isset($user)) {
            if ($user->email != $request->email) {
                $find_email = User::where('school_id', '=', auth()->user()->school_id)
                    ->where('email', '=', $request->email)
                    ->first();

                if (isset($find_email)) {
                    $email_error = ['email' => [trans('auth.user_already_exists')]];
                    return response()->json($email_error, 422);
                }
            }
        }

        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->current_role_id = $request->roles[0];
        $user->save();

        UserRole::where('user_id', $user->user_id)
            ->delete();

        foreach ($request->roles as $value) {
            $user_role = new UserRole();
            $user_role->user_id = $user->user_id;
            $user_role->role_type_id = $value;
            $user_role->save();
        }

        return response()->json($user, 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}