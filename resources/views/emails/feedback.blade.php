@extends('layouts.email')

@section('title', '{{$mail_body->subject}}')

@section('content')
<p><b>Поступила заявка с формы обратной связи.</b></p>
<p>Имя: {{$mail_body->first_name}}.</p>
<p>Почта: {{$mail_body->email}}.</p>
<p>Телефон: {{$mail_body->phone}}.</p>
<p>Вопрос: {{$mail_body->question}}.</p>
@endsection