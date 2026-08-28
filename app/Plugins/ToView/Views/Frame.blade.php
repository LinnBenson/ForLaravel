@php
    $frame = \App\Plugins\ToView\Support\FrameService::renderFrame();
    $themeStyle = '';
    foreach( $frame->theme as $key => $value ) {
        if( str_starts_with( $key, '--' ) ) {
            $themeStyle .= "{$key}: {$value}; ";
        }
    }
@endphp
<html lang="{{$frame->locale}}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
        <meta http-equiv="Cache-Control" content="no-siteapp">
        <link rel="icon" href="{{setting( 'app.icon' )}}" type="image/x-icon" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{setting( 'app.icon' )}}" />
        <link rel="apple-touch-icon-precomposed" href="{{setting( 'app.icon' )}}" />
        <meta name="msapplication-TileImage" content="{{setting( 'app.icon' )}}" />
        <link rel="shortcut icon" href="{{setting( 'app.icon' )}}" />
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-touch-fullscreen" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="full-screen" content="yes">
        <meta name="browsermode" content="application">
        <meta name="x5-fullscreen" content="true">
        <meta name="x5-page-mode" content="app">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="{{route( 'plugins.to-view.asset', [ 'path' => 'css/rely.css' ])}}?v={{$frame->version}}">
        <link rel="stylesheet" href="{{route( 'plugins.to-view.asset', [ 'path' => 'css/unit.css' ])}}?v={{$frame->version}}">
        <link rel="stylesheet" href="{{route( 'plugins.to-view.asset', [ 'path' => 'css/BootstrapIcons.css' ])}}?v={{$frame->version}}">
        @if( isset( $frame->theme['css'] ) && is_string( $frame->theme['css'] ) )
            <link rel="stylesheet" href="{{$frame->theme['css']}}">
        @endif
        <script src="{{route( 'plugins.to-view.asset', [ 'path' => 'js/jquery-4.0.0.min.js' ])}}?v={{$frame->version}}"></script>
        <script src="{{route( 'plugins.to-view.asset', [ 'path' => 'js/jquery.cookie.js' ])}}?v={{$frame->version}}"></script>
        <script src="{{route( 'plugins.to-view.asset', [ 'path' => 'js/clipboard.min.js' ])}}?v={{$frame->version}}"></script>
        <script src="{{route( 'plugins.to-view.asset', [ 'path' => 'js/qrcode.min.js' ])}}?v={{$frame->version}}"></script>
        <title>@hasSection( 'title' )@yield( 'title' ) - @endif{{ setting( 'app.title' ) }}</title>
        <style>
            :root { {{$themeStyle}} }
        </style>
        <script>
            window['setting'] = { 'rid': `{{request()->attributes->get('rid')}}`, 'locale': `{{str_replace( '_', '-', $frame->locale )}}`, 'langs': `@yield( 'langs', '' )` };
        </script>
        <script src="{{route( 'plugins.to-view.asset', [ 'path' => 'js/Core.js' ])}}?v={{$frame->version}}"></script>
        @stack( 'head' )
    </head>
    <body>
        <div id="toview-unit-box">
            <div class="toview-unit toview-unit-full toview-unit-popup">
            </div>
            <div class="toview-unit toview-unit-full toview-unit-loading">
                <div class="toview-unit-loading-box center">
                    <div class="toview-unit-loading"></div>
                </div>
            </div>
            <div class="toview-unit toview-unit-full toview-unit-toast">
                <div class="toview-unit-toast-box">
                    <div class="toview-unit-toast-item toview-unit-toast-icon">
                        <i class=""></i>
                    </div>
                    <div class="toview-unit-toast-item toview-unit-toast-text">
                        <div class="toview-unit-toast-text-title more"></div>
                        <div class="toview-unit-toast-text-content p6"></div>
                    </div>
                    <div class="toview-unit-toast-item toview-unit-toast-close">
                        <i class="bi block bi-x-lg" onClick="Core.toast( false )"></i>
                    </div>
                </div>
            </div>
        </div>
        @yield( 'body' )
        @stack( 'footer', '' )
    </body>
</html>