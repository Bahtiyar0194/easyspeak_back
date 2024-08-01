<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Config;
use Validator;
use Str;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Language;

class AuthController extends Controller{
    public function __construct(Request $request){
        app()->setLocale($request->header('Accept-Language'));
    }

    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|between:2,100',
            'last_name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required_with:password|same:password|min:6',
            'lang' => 'required'
        ]);

        app()->setLocale($request->lang);

        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $new_user = new User();
        $new_user->first_name = $request->first_name;
        $new_user->last_name = $request->last_name;
        $new_user->email = $request->email;
        $new_user->password = bcrypt($request->password);
        $new_user->status_type_id = 1;
        $new_user->save();

        $new_user_role = new UserRole();
        $new_user_role->user_id = $new_user->user_id;
        $new_user_role->role_type_id = 1;
        $new_user_role->save();

        $new_user_role = new UserRole();
        $new_user_role->user_id = $new_user->user_id;
        $new_user_role->role_type_id = 2;
        $new_user_role->save();

        $new_user_role = new UserRole();
        $new_user_role->user_id = $new_user->user_id;
        $new_user_role->role_type_id = 3;
        $new_user_role->save();

        return response()->json(['token' => $new_user->createToken(Str::random(60))->plainTextToken], 200);
    }

    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
            'lang' => 'required',
        ]);

        app()->setLocale($request->lang);

        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $find_user = User::where('email', '=', $request->email)->first();

        if(isset($find_user)){
            $userdata = array(
                'email' => $request->email,
                'password' => $request->password,
            );

            if (!Auth::attempt($userdata)) {
             return response()->json(['auth_failed' => trans('auth.failed')], 401);
         }

         if(auth()->user()->user_status_id == 2){
            return response()->json(['auth_failed' => trans('auth.banned')], 401);
        }

        return response()->json(['token' => auth()->user()->createToken(Str::random(60))->plainTextToken], 200);
    }
    else{
        return response()->json(['auth_failed' => trans('auth.failed')], 401);
    }
}


public function google_login(){
    return response()->json(Socialite::driver('google')->stateless()->redirect()->getTargetUrl(), 200);
}

public function google_callback(){
    $googleUser = Socialite::driver('google')->stateless()->user();
    $user = User::updateOrCreate([
        'email' => $googleUser->getEmail(),
    ], 
    [
        'name' => $googleUser->getName(),
        'google_id' => $googleUser->getId(),
        'avatar' => $googleUser->getAvatar(),
    ]);

    $token = $user->createToken(Str::random(60))->plainTextToken;

    return response()->json(['token' => $token], 200);
}

public function me(Request $request){
    $user = auth()->user();

    $language = Language::where('lang_tag', '=', $request->header('Accept-Language'))->first();

        // $roles = UserRole::leftJoin('types_of_user_roles', 'users_roles.role_type_id', '=', 'types_of_user_roles.role_type_id')
        // ->leftJoin('types_of_user_roles_lang', 'types_of_user_roles.role_type_id', '=', 'types_of_user_roles_lang.role_type_id')
        // ->where('users_roles.user_id', $user->user_id)
        // ->where('types_of_user_roles_lang.lang_id', $language->lang_id)
        // ->select(
        //     'users_roles.role_type_id',
        //     'types_of_user_roles.role_type_slug',
        //     'types_of_user_roles_lang.user_role_type_name'
        // )
        // ->get();

        // foreach ($roles as $key => $role) {
        //     if($role->role_type_id == $user->current_role_id){
        //         $user->current_role_name = $role->user_role_type_name;
        //         break;
        //     }
        // }

        // $user->roles = $roles;

    return response()->json($user, 200);
}

public function logout(){
    auth()->user()->tokens()->delete();
    return response()->json('Logout successful', 200);
}
}