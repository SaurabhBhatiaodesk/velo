@extends('emails.layout')

@section('title')
<div style="font-size:24px;font-weight:bold">{{ __('emails.venti.delivery_confirmed.subject') }}</div>
@endsection

@section('content')
<div>{{ __('emails.venti.delivery_confirmed.content') }}</div>
@endsection

@section('ps')
@endsection
