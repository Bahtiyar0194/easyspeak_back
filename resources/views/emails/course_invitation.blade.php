@extends('layouts.email')

@section('title', 'Приглашение на курс "{{$mail_body->course_name}}"')

@section('content')
<p>Вас приветствует {{$mail_body->subject}}.</p>
<p>Вы приглашены на курс "{{$mail_body->course_name}}". Для принятия приглашения просим Вас перейти по данной <a href="{{$mail_body->invitation_url}}">ссылке</a></p>
@endsection