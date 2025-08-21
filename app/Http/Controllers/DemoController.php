<?php

namespace App\Http\Controllers;

use Mail;
use App\Mail\WelcomeMail;

use Illuminate\Http\Request;
use Validator;

class DemoController extends Controller
{
    public function __construct(Request $request)
    {
        app()->setLocale($request->header('Accept-Language'));
    }

    public function request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'phone' => 'required|regex:/^((?!_).)*$/s',
            'lang' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }


        
    }
}
