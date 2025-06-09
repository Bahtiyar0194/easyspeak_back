@extends('layouts.email')

@section('title', '{{$mail_body->school_name}}')

@section('content')
<b>{{$mail_body->first_name}}, Добро пожаловать!</b>
<p>Вас приветствует {{$mail_body->school_name}}.</p>
<p>Ссылка для входа в аккаунт: <a href="{{$mail_body->login_url}}"><b>{{$mail_body->login_url}}</b></a></p>
<p>Домен школы: <b>{{$mail_body->school_domain}}</b></p>
<p>Логин: <b>{{$mail_body->login}}</b></p>
<p>Пароль: <b>{{$mail_body->password}}</b></p>
@endsection