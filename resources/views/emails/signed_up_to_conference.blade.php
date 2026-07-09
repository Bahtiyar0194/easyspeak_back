@extends('layouts.email')

@section('title', '{{$mail_body->subject}}')

@section('content')
@if($mail_body->for_moderator === true)
    <p>
        <b>
            {{trans('app.bot.conference.signed_up_for_moderator', [
            'name' => $mail_body->learner_name,
            ])}}
        </b>
    </p>
@endif
    <p>{{trans('app.bot.conference.subject')}}: <b>{{$mail_body->conference->topic}}</b></p>
    <p>{{trans('app.bot.conference.start_time')}}: <b>{{$mail_body->start_time}}</b></p>
    <p>{{trans('app.bot.conference.your_link')}}: <a href="{{$mail_body->conf_url}}"><b>{{$mail_body->conf_url}}</b></a></p>
    @if($mail_body->for_moderator === false)
        <p>{{trans('app.bot.conference.moderator')}}: <b>{{$mail_body->moderator_name}}</b></p>
    @endif
@endsection