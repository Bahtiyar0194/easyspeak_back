<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Cache;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Language;
use App\Models\School;
use App\Models\Course;
use App\Models\SiteConfiguration;
use App\Models\TelegramToken;

use Mail;
use App\Mail\PasswordRecoveryMail;

use App\Services\TwilioWhatsAppService;

class AuthController extends Controller
{
    protected $twilioWhatsAppService;

    public function __construct(Request $request, TwilioWhatsAppService $twilioWhatsAppService)
    {
        //app()->setLocale($request->header('Accept-Language'));
        $this->twilioWhatsAppService = $twilioWhatsAppService;
    }

    public function register(Request $request)
    {
        $rules = [
            [
                'school_name' => 'required|string|between:2,100',
                'full_school_name' => 'required|string|between:2,100',
                'bin' => 'required|string|size:12',
                'school_email' => 'required|string|email|max:100',
                'location_id' => 'required|numeric',
                'street' => 'required|string|between:2,100',
                'house' => 'required|string|between:2,10',
                'school_domain' => 'required|string|between:2,20|regex:/^[a-z]+$/u|unique:schools',
                'lang' => 'required'
            ],
            [
                'first_name' => 'required|string|between:2,100',
                'last_name' => 'required|string|between:2,100',
                'email' => 'required|string|email|max:100',
                'phone' => 'required|regex:/^((?!_).)*$/s',
                'password' => 'required|string|min:8',
                'password_confirmation' => 'required_with:password|same:password|min:8',
                'lang' => 'required'
            ]   
        ];


        $validator = Validator::make($request->all(), $rules[$request->first_registration == 'true' ? $request->step - 1 : 1]);

        app()->setLocale($request->lang);

        $language = Language::where('lang_tag', '=', $request->lang)->first();

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if($request->first_registration == 'true' && $request->step == 1){
            return response()->json([
                'step' => 1
            ], 200);
        }

        if ($request->first_registration == 'true') {
            $school = new School();
            $school->school_domain = str_replace(' ', '', e($request->school_domain));
            $school->school_name = e($request->school_name);
            $school->full_school_name = e($request->full_school_name);
            $school->bin = e($request->bin);
            $school->email = e($request->school_email);
            $school->location_id = e($request->location_id);
            $school->street = e($request->street);
            $school->house = e($request->house);
            $school->school_type_id = 1;
            $school->subscription_expiration_at = date('Y-m-d H:i:s', strtotime('+14 days'));
            $school->save();
        } elseif ($request->first_registration == 'false') {
            $school = School::where('school_domain', e($request->school_domain))->first();

            if (!isset($school)) {
                return response()->json(['registration_failed' => [trans('auth.school_not_found')]], 422);
            }

            $getSchoolUser = User::where('email', e($request->email))
                ->where('school_id', $school->school_id)
                ->first();

            if (isset($getSchoolUser)) {
                return response()->json(['email' => [trans('auth.user_already_exists')]], 422);
            }
        } else {
            return response()->json(['registration_failed' => 'First registration: true or false'], 422);
        }

        //$this->twilioWhatsAppService->sendMessage('register_template', [$request->first_name], $request->phone);

        $site_configuration = SiteConfiguration::find(1);

        $new_user = new User();
        $new_user->first_name = e($request->first_name);
        $new_user->last_name = e($request->last_name);
        $new_user->email = e($request->email);
        $new_user->phone = e($request->phone);
        $new_user->school_id = $school->school_id;
        $new_user->lang_id = $language->lang_id;
        $new_user->free_club_lessons_count = isset($site_configuration) ? $site_configuration->free_club_lessons_count : 3;
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
        $getSchoolUser->status_type_id = 1;
        $getSchoolUser->save();

        return response()->json(['token' => auth()->user()->createToken(Str::random(60))->plainTextToken], 200);
    }

    public function password_recovery(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'school_domain' => 'required',
            'lang' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

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

        $email_hash = Str::random(32);

        $getSchoolUser->email_hash = $email_hash;
        $getSchoolUser->status_type_id = 5;
        $getSchoolUser->save();

        $mail_body = new \stdClass();
        $mail_body->subject = $getSchool->school_name.' | Восстановление пароля.';
        $mail_body->first_name = $getSchoolUser->first_name;
        $mail_body->recovery_url = $request->header('Origin') . '/auth/change-password/'.$email_hash;
        $mail_body->school_name = $getSchool->school_name;

        Mail::to($getSchoolUser->email)->send(new PasswordRecoveryMail($mail_body));

        return response()->json("success", 200);
    }

    public function check_email_hash(Request $request)
    {
        $getUser = User::where('email_hash', '=', $request->hash)
            ->where('status_type_id', '=', 5)
            ->select(
                'first_name'
            )
            ->first();

        if(isset($getUser)){
            return response()->json($getUser, 200);
        }
        else{
            return response()->json(['invalid' => true], 422);
        }
    }

    public function new_password(Request $request){
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required_with:password|same:password|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $getUser = User::where('email_hash', '=', $request->hash)
            ->where('status_type_id', '=', 5)
            ->first();

        if(isset($getUser)){
            $getUser->password = bcrypt($request->password);
            $getUser->status_type_id = 1;
            $getUser->save();

            return response()->json("success", 200);
        }
        else{
            return response()->json(['invalid' => true], 422);
        }
    }

    public function redirect_to_google(Request $request)
    {
        $request->validate([
            'return_url' => 'required|url',
            'school_id' => 'required',
            'lang_tag' => 'required|string'
        ]);

        // Упаковываем return_url и school_id в state
        $state_data = base64_encode(json_encode([
            'return_url' => $request->query('return_url'),
            'school_id' => $request->query('school_id'),
            'lang_tag' => $request->query('lang_tag')
        ]));

        return Socialite::driver('google')
            ->stateless()
            ->with(['state' => $state_data])
            ->redirect();
    }

    public function google_callback(Request $request)
    {
        $site_configuration = SiteConfiguration::find(1);

        $state_data = json_decode(base64_decode($request->state), true);
        
        $return_url = $state_data['return_url'] ?? 'https://easyspeak.kz';
        $school_id = $state_data['school_id'] ?? null;
        $lang_tag = $state_data['lang_tag'] ?? 'ru';

        $language = Language::where('lang_tag', '=', $lang_tag)->first();

        if (!$school_id) {
            return response()->json(['message' => 'Не указан ID школы'], 400);
        }

        try {
            // Вход без передачи аргумента в user()
            $google_user = Socialite::driver('google')
            ->stateless()
            ->user();

            // Поиск пользователя
            $user = User::where('school_id', $school_id)
            ->where(function ($query) use ($google_user) {
                $query->where('google_id', $google_user->getId())
                ->orWhere('email', $google_user->getEmail());
            })
            ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $google_user->getId(),
                    ]);
                }

                if(!$user->avatar){
                    $user->update([
                        'avatar' => $google_user->getAvatar(),
                    ]);
                }
            } else {
                $fullName = $google_user->getName();
                $nameParts = explode(' ', $fullName, 2);

                $user = User::create([
                    'school_id'       => $school_id,
                    'first_name'      => $google_user->user['given_name'] ?? ($nameParts[0] ?? ''),
                    'last_name'       => $google_user->user['family_name'] ?? ($nameParts[1] ?? ''),
                    'email'           => $google_user->getEmail(),
                    'google_id'       => $google_user->getId(),
                    'avatar'          => $google_user->getAvatar(),
                    'lang_id'         => $language->lang_id,
                    'current_role_id' => 5,
                    'status_type_id'  => 1,
                    'free_club_lessons_count' => isset($site_configuration) ? $site_configuration->free_club_lessons_count : 3
                ]);

                $new_user_role = new UserRole();
                $new_user_role->user_id = $user->user_id;
                $new_user_role->role_type_id = 5;
                $new_user_role->save();
            }

            $one_time_code = Str::random(40);
            Cache::put('auth_code_'.$one_time_code, $user->user_id, now()->addSeconds(60));

            return redirect()->away($return_url . '/auth/login?gcode=' . $one_time_code);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка авторизации: '.$e->getMessage(),
            ], 401);
        }
    }

    public function exchange_google_code(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'lang' => 'required|string'
        ]);

        app()->setLocale($request->lang);

        // Достаем ID пользователя и СРАЗУ удаляем код из кэша (одноразовость)
        $user_id = Cache::pull('auth_code_'.$request->code);

        if (!$user_id) {
            return response()->json(['message' => trans('auth.wrong_google_code')], 401);
        }

        $user = User::findOrFail($user_id);
        $school = School::findOrFail($user->school_id);
        $token = $user->createToken(Str::random(60))->plainTextToken;

        return response()->json([
            'token' => $token,
            'school_domain' => $school->school_domain
        ]);
    }

    public function telegram_callback(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'school_id' => 'required|numeric',
            'lang' => 'required|string'
        ]);

        $site_configuration = SiteConfiguration::find(1);
        
        $telegram_data = (object) $request->data;
        $school_id = $request->school_id ?? null;
        $lang_tag = $request->lang_tag ?? 'ru';

        $language = Language::where('lang_tag', '=', $lang_tag)->first();

        if (!$school_id) {
            return response()->json(['message' => 'Не указан ID школы'], 400);
        }

        try {
            // Поиск пользователя
            $user = User::where('school_id', $school_id)
            ->where('telegram_id', $telegram_data->id)
            ->first();

            if ($user) {
                if(!$user->avatar){
                    $user->update([
                        'avatar' => $telegram_data->photo_url ?? null,
                    ]);
                }
            } else {
                $user = User::create([
                    'school_id'       => $school_id,
                    'first_name'      => $telegram_data->last_name ?? '',
                    'last_name'       => $telegram_data->first_name ?? '',
                    'telegram_id'     => $telegram_data->id,
                    'avatar'          => $telegram_data->photo_url ?? null,
                    'lang_id'         => $language->lang_id,
                    'current_role_id' => 5,
                    'status_type_id'  => 1,
                    'free_club_lessons_count' => isset($site_configuration) ? $site_configuration->free_club_lessons_count : 3
                ]);

                $new_user_role = new UserRole();
                $new_user_role->user_id = $user->user_id;
                $new_user_role->role_type_id = 5;
                $new_user_role->save();
            }

            $school = School::findOrFail($user->school_id);
            $token = $user->createToken(Str::random(60))->plainTextToken;

            return response()->json([
                'token' => $token,
                'school_domain' => $school->school_domain
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка авторизации: '.$e->getMessage(),
            ], 401);
        }
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

        $telegram_token = TelegramToken::where('user_id', $user->user_id)
        ->first();

        $user->telegram = $telegram_token;

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

        $telegram_token = TelegramToken::where('user_id', $user->user_id)
        ->first();

        if(isset($telegram_token)){
            $telegram_token->lang_id = $language->lang_id;
            $telegram_token->save();
        }

        return response()->json('User language change successful', 200);
    }

    public function logout()
    {
        auth()->user()->tokens()->delete();
        return response()->json('Logout successful', 200);
    }
}
