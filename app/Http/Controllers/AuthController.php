<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Language;
use App\Models\School;

class AuthController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100',
            'phone' => 'required|regex:/^((?!_).)*$/s',
            'first_registration' => 'required',
            'school_name' => 'nullable|required_if:first_registration,true|string|between:2,100',
            'school_domain' => 'nullable|required_if:first_registration,true|string|between:2,20|regex:/^[a-z]+$/u|unique:schools',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required_with:password|same:password|min:6',
            'lang' => 'required'
        ]);

        app()->setLocale($request->lang);

        $language = Language::where('lang_tag', '=', $request->lang)->first();

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->first_registration == 'true') {
            $school = new School();
            $school->school_domain = str_replace(' ', '', $request->school_domain);
            $school->school_name = $request->school_name;
            $school->school_type_id = 1;
            $school->subscription_expiration_at = date('Y-m-d H:i:s', strtotime('+14 days'));
            $school->save();
        } elseif ($request->first_registration == 'false') {
            $school = School::where('school_domain', $request->school_domain)->first();

            if (!isset($school)) {
                return response()->json(['registration_failed' => [trans('auth.school_not_found')]], 422);
            }

            $getSchoolUser = User::where('email', $request->email)
                ->where('school_id', $school->school_id)
                ->first();

            if (isset($getSchoolUser)) {
                return response()->json(['email' => [trans('auth.user_already_exists')]], 422);
            }
        } else {
            return response()->json(['registration_failed' => 'First registration: true or false'], 422);
        }

        $new_user = new User();
        $new_user->first_name = $request->first_name;
        $new_user->last_name = $request->last_name;
        $new_user->email = $request->email;
        $new_user->phone = $request->phone;
        $new_user->school_id = $school->school_id;
        $new_user->lang_id = $language->lang_id;
        $new_user->password = bcrypt($request->password);
        $new_user->status_type_id = 1;

        if ($request->first_registration == 'true') {
            $new_user->current_role_id = 2;
        } elseif ($request->first_registration == 'false') {
            $new_user->current_role_id = 5;
        }

        $new_user->save();

        if ($request->first_registration == 'true') {
            $new_user_role = new UserRole();
            $new_user_role->user_id = $new_user->user_id;
            $new_user_role->role_type_id = 2;
            $new_user_role->save();

            $new_user_role = new UserRole();
            $new_user_role->user_id = $new_user->user_id;
            $new_user_role->role_type_id = 3;
            $new_user_role->save();

            $new_user_role = new UserRole();
            $new_user_role->user_id = $new_user->user_id;
            $new_user_role->role_type_id = 4;
            $new_user_role->save();
        }

        $new_user_role = new UserRole();
        $new_user_role->user_id = $new_user->user_id;
        $new_user_role->role_type_id = 5;
        $new_user_role->save();

        return response()->json($school, 200);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'school_domain' => 'required',
            'lang' => 'required',
        ]);

        app()->setLocale($request->lang);
        $language = Language::where('lang_tag', '=', $request->lang)->first();

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $getSchool = School::where('school_domain', $request->school_domain)->first();

        if (!isset($getSchool)) {
            return response()->json(['school_domain' => [trans('auth.school_not_found')]], 422);
        }

        $getSchoolUser = User::where('email', $request->email)
            ->where('school_id', $getSchool->school_id)
            ->first();

        if (!isset($getSchoolUser)) {
            return response()->json(['email' => [trans('auth.not_found')]], 401);
        }

        $userdata = array(
            'school_id' => $getSchoolUser->school_id,
            'email' => $request->email,
            'password' => $request->password,
        );

        if (!Auth::attempt($userdata)) {
            return response()->json(['auth_failed' => [trans('auth.failed')]], 401);
        }

        if (auth()->user()->user_status_id == 2) {
            return response()->json(['auth_failed' => [trans('auth.banned')]], 401);
        }

        $getSchoolUser->lang_id = $language->lang_id;
        $getSchoolUser->save();

        return response()->json(['token' => auth()->user()->createToken(Str::random(60))->plainTextToken], 200);
    }


    public function google_login()
    {
        return response()->json(Socialite::driver('google')->stateless()->redirect()->getTargetUrl(), 200);
    }

    public function google_callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = User::updateOrCreate(
            [
                'email' => $googleUser->getEmail(),
            ],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]
        );

        $token = $user->createToken(Str::random(60))->plainTextToken;

        return response()->json(['token' => $token], 200);
    }

    public function me(Request $request)
    {
        $user = auth()->user();

        $language = Language::where('lang_id', '=', $user->lang_id)->first();

        $roles = UserRole::leftJoin('types_of_user_roles', 'users_roles.role_type_id', '=', 'types_of_user_roles.role_type_id')
            ->leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
            ->where('users_roles.user_id', '=', $user->user_id)
            ->where('types_of_user_roles_lang.lang_id', '=', $language->lang_id)
            ->select(
                'users_roles.role_type_id',
                'types_of_user_roles.role_type_slug',
                'types_of_user_roles_lang.user_role_type_name'
            )
            ->orderBy('users_roles.role_type_id', 'asc')
            ->get();

        foreach ($roles as $role) {
            if ($role->role_type_id == $user->current_role_id) {
                $user->current_role_name = $role->user_role_type_name;
                break;
            }
        }

        $user->roles = $roles;

        return response()->json($user, 200);
    }

    public function change_mode(Request $request)
    {
        $user = auth()->user();
        $role_found = false;

        $roles = UserRole::where('user_id', $user->user_id)
            ->select('role_type_id')->get();

        foreach ($roles as $value) {
            if ($value->role_type_id == $request->role_type_id) {
                $role_found = true;
                break;
            }
        }

        if ($role_found === true) {
            $change_user = User::find($user->user_id);
            $change_user->current_role_id = $request->role_type_id;
            $change_user->save();

            return response()->json('User mode change successful', 200);
        } else {
            return response()->json('Access denied', 403);
        }
    }

    public function change_language(Request $request)
    {
        $user = auth()->user();

        $language = Language::where('lang_tag', '=', $request->lang_tag)->first();

        $findUser = User::find($user->user_id);
        $findUser->lang_id = $language->lang_id;
        $findUser->save();

        return response()->json('User language change successful', 200);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        return response()->json('Logout successful', 200);
    }
}
