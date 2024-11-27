@extends('emails.layout')

@section('title')
<div>New Error Alert</div>
@endsection

@section('content')
<pre>{!! json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</pre>
@endsection
