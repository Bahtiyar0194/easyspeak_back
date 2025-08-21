@extends('layouts.email')

@section('title', '{{$mail_body->school_name}} | Запрос на демонстрацию.')

@section('content')
<p>Имя: <b>{{$mail_body->name}}<b></p>
<p>Телефон: <a href="tel:{{$mail_body->phone}}"><b>{{$mail_body->phone}}</b></a></p>
<p>Предпочитаемый язык общения: <b>{{$mail_body->lang}}</b></p>
@endsection