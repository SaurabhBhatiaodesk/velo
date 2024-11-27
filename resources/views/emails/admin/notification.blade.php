@extends('emails.layout')

@section('title')
<div>New Notification</div>
@endsection

@section('content')
<pre>{!! json_encode($notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</pre>
@endsection
