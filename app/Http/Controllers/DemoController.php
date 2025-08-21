<?php

namespace App\Http\Controllers;

use Mail;
use App\Mail\DemoMail;

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


        $mail_body = new \stdClass();
        $mail_body->subject = 'Запрос на демонстрацию';
        $mail_body->name = $request->name;
        $mail_body->phone = $request->phone;
        $mail_body->lang = $request->lang;

        Mail::to(env('MANAGER_MAIL'))->send(new DemoMail($mail_body));
        return response()->json('success', 200);
    }
}
