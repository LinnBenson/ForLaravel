@props([ 'size' => 128, 'config' => [] ])
@php
    $rid = 'QRCode-'.uniqid(); // 唯一标识符
@endphp
<span id="{{ $rid }}"></span><script>Core.QRCode( $( 'span#{{$rid}}' ), `{{$slot}}`, {{is_numeric( $size ) ? $size : 128}}, @json( $config ) );</script>