@extends('emails.layout')

@section('title')
<div style="font-size:24px;font-weight:bold">Hi, {{ $user->first_name }}!</div>
@endsection

@section('content')
<div>{{ __('emails.otp.otp') }}</div>
<div style="padding:20px 0">
    <div style="padding:20px 0;display:block;margin:auto;text-align:center;font-size:24px;font-weight:bold;letter-spacing:10px">
        <div style="display:inline-block;text-decoration:none;color:#ffffff;background-color:#0b60ff;border-radius:4px;-webkit-border-radius:4px;-moz-border-radius:4px;width:auto; width:auto;;border-top:1px solid #3AAEE0;border-right:1px solid #3AAEE0;border-bottom:1px solid #3AAEE0;border-left:1px solid #3AAEE0;padding:10px 20px;text-align:center;mso-border-alt:none;word-break:keep-all">
            <div style="padding-left:10px">{{ $user->token }}</div>
        </div>
    </div>
</div>
@endsection
