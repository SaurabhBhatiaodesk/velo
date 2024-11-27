@extends('admin.layout')

@section('body')
    <pre><code>{!! json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}</code></pre>
