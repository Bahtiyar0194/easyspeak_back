@extends('layouts.email')

@section('title', '{{$mail_body->school_name}}')

@section('content')
<b>{{$mail_body->first_name}}, Добро пожаловать!</b>
<p>Вас приветствует {{$mail_body->school_name}}.</p>
<p>Для активации Вашего аккаунта просим Вас перейти по данной <a href="{{$mail_body->activation_url}}">ссылке</a></p>
@endsection