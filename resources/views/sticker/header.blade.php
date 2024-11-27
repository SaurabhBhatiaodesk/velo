<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ __('stickers.pageTitle') }}</title>


    <style>
        .separator1{
            margin: 0;
            color: black;
            height: 0px;
            border: 1px solid;
        }

        @media print {
            .page-break {page-break-after: always;}
        }

        @page {
            margin: 0 !important;
            padding: 0 !important;
            size: {{ $pageWidth }}pt {{ $pageHeight }}pt;
        }

        html,
        body {
            position: relative;
            margin: 0 !important;
            padding:0;
            width: {{ $pageWidth}}pt;
            height: {{ $pageHeight }}pt;
            direction: {{ $rtl ? 'rtl' : 'ltr' }} !important;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
        }

        body * {
            direction: {{ $rtl ? 'rtl' : 'ltr' }} !important;
        }

        @font-face {
            font-family: 'Heebo';
            font-weight: normal;
            font-style: normal;
            font-variant: normal;
            src: url({{ storage_path('fonts/Heebo/heebo.regular.ttf') }});
        }

        @font-face {
            font-family: 'Heebo';
            font-weight: bold;
            font-style: normal;
            font-variant: normal;
            src: url({{ storage_path('fonts/Heebo/heebo.bold.ttf') }});
        }

        html {
            background: #030303;
        }

        .sticker {
            position: relative;
            font-size: 9pt;
            font-weight: normal;
            font-family: "Heebo", sans-serif;
            background: white;
            width: 270pt;
            height: 260pt;
            padding: 5pt;
            text-align: {{ $rtl ? 'right' : 'left' }};
            line-height: 0.85;
            margin: 2pt;
        }

        .sticker-container {
            border: solid 2pt black;
            position: relative;
            width: 100%;
            height: 100%;
            box-sizing: border-box
        }

        .english {
            font-family: "Heebo", sans-serif;
        }

        .english.bold,
        .english .bold {
            font-family: "Heebo", sans-serif;
        }

        .one-line {
            max-width: 100%;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .long-barcode {
            width: 80%;
            margin: auto;
        }

        .short-barcode {
            width: 50%;
            margin: auto;
        }

        .barcode {
            padding-bottom: 2pt;
        }

        .barcode img {
            width: 100%;
            height: 50pt;
            margin: 5pt auto 4pt auto;
        }

        .footer {
            width: 100%;
            position: relative;
            line-height: 1.1;
        }

        .absolute-left {
            position: absolute;
            top: 0;
            left: 0;
        }

        .absolute-right {
            position: absolute;
            top: 0;
            right: 0;
        }

        .absolute-center {
            position: absolute;
            right: 0;
            left: 0;
            top: 0;
            bottom: 0;
            margin: auto;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .logo {
            height: 50pt;
            width: 99pt;
            background-color: #000000;
            background-size: 50pt auto;
            background-position: center center;
            background-repeat: no-repeat;
            visibility: visible;
            -webkit-print-color-adjust: exact;
        }

        .courier-logo {
            height: 50pt;
            width: 70pt;
            background-size: contain;
            background-position: center center;
            background-repeat: no-repeat;
            visibility: visible;
            -webkit-print-color-adjust: exact;
        }

        .sender {
            font-size: 7.5pt;
            padding-left: 108pt;
            line-height: 8.3pt;
            height: 50pt;
            box-sizing: border-box
        }

        .sender-title {
            padding-bottom: 1pt;
        }

        .sender div {
            height: 8.5pt
        }

        .classification {
            font-size: 13pt;
            height: 22pt;
            padding-top: 5pt;
            box-sizing: border-box;
        }

        .receiver {
            font-size: 12pt;
            padding: 5pt 8pt;
        }

        .receiver div {
            height: 14pt
        }

        .receiver .receiver-title {
            height: 11pt;
        }

        .receiver .receiver-name {
            font-size: 17pt;
            height: 20pt;
        }

        .receiver .line2 {
            padding: 3pt 0;
            font-size: 8pt;
            height: 8pt;
        }

        .attribute {
            direction: {{ $rtl ? 'rtl' : 'ltr' }};
            font-size: 9pt;
            padding: 0 8pt;
        }

        .attribute-inner {
            box-sizing: border-box;
            padding-top: 5pt;
            height: 18pt;
            width: 100%;
        }

        .attribute.half {
            display: block;
            float: {{ $rtl ? 'right' : 'left' }};
            vertical-align: middle;
            width: 49%;
            box-sizing: border-box;
        }

        .attribute.timestamp {
            border-{{ $rtl ? 'left' : 'right' }}: 2pt solid black;
        }
    </style>
</head>

<body>
