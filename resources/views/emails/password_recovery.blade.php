@extends('layouts.email')

@section('title', '{{$mail_body->school_name}}')

@section('content')
<b>{{$mail_body->first_name}}, Добро пожаловать!</b>
<p>Вас приветствует {{$mail_body->school_name}}.</p>
<p>Для восстановления пароля Вашего аккаунта просим Вас перейти по данной <a href="{{$mail_body->verification_url}}">ссылке</a>. Код восстановления: <b>{{$mail_body->verification_code}}</b></p>
@endsection