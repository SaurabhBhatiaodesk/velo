@extends('emails.layout')

@section('title')
<div style="font-size:24px;font-weight:bold">Welcome to Velo!</div>
@endsection

@section('content')
<div>We're happy you came along.</div>
<div>Please verify your email address by clicking the button below:</div>
<div style="padding:20px 0">
    <a href="{{ $link }}" style="padding:20px 0;display:block;margin:auto;text-align:center;">
        <div style="display:inline-block;text-decoration:none;color:#ffffff;background-color:#3AAEE0;border-radius:4px;-webkit-border-radius:4px;-moz-border-radius:4px;width:auto; width:auto;;border-top:1px solid #3AAEE0;border-right:1px solid #3AAEE0;border-bottom:1px solid #3AAEE0;border-left:1px solid #3AAEE0;padding:10px 20px;text-align:center;mso-border-alt:none;word-break:keep-all">
            Verify Email Address
        </div>
    </a>
</div>
@endsection

@section('ps')
<div>
    <div>If you can't click links directly from your email, you can go to this url instead:</div>
    <div style="text-align:center;font-weight:bold">{{ $link }}</div>
</div>
@endsection
