@extends('layouts.email')

@section('title', 'Запрос на курс: "{{$mail_body->course_name}} ({{$mail_body->level_name}})"')

@section('content')
<p>Имя: <b>{{$mail_body->name}}</b></p>
<p>Телефон: <a href="tel:{{$mail_body->phone}}"><b>{{$mail_body->phone}}</b></a></p>
<p>Курс: <b>{{$mail_body->course_name}}</b></p>
<p>Уровень: <b>{{$mail_body->level_name}}</b></p>
<p>Предпочитаемый язык общения: <b>{{$mail_body->lang}}</b></p>
@endsection